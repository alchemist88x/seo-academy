<?php

namespace App\Domain\Commerce\Services;

use App\Domain\Commerce\Models\CommerceInstallmentPlan;
use App\Domain\Commerce\Models\CommerceOrder;
use App\Domain\Commerce\Models\CommerceOrderInstallment;
use App\Domain\Commerce\Models\CommerceTransaction;
use App\Domain\Commerce\Models\CourseBundle;
use App\Domain\Courses\Models\Course;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderAndEnrollmentService
{
    public function __construct(
        protected CurrencyAndPricingService $pricingService,
        protected CouponService $couponService,
    ) {}

    /**
     * Get list of courses already owned / enrolled by the student.
     */
    public function getStudentEnrolledCourseIds(string|int $userId): array
    {
        $profile = StudentProfile::where('user_id', $userId)->first();
        if (! $profile) {
            return [];
        }

        return Enrollment::where('student_profile_id', $profile->id)
            ->whereIn('status', [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_COMPLETED])
            ->pluck('course_id')
            ->toArray();
    }

    /**
     * Compute checkout calculation before creating an order.
     */
    public function calculateCheckout(
        User $user,
        Course|CourseBundle $item,
        ?string $currency = null,
        ?string $couponCode = null,
        ?CommerceInstallmentPlan $installmentPlan = null
    ): array {
        $currency = strtoupper($currency ?: $this->pricingService->resolveCurrency());
        $pricing = $this->pricingService->getItemPrice($item, $currency);
        $basePrice = $pricing['original_price'];
        $promoDiscount = $pricing['discount_amount'];
        $priceAfterPromo = $pricing['final_price'];

        $ownedDiscount = 0.00;
        $ownedCourseIds = [];
        $policyApplied = null;

        // Bundle ownership policy check
        if ($item instanceof CourseBundle) {
            $studentOwnedCourses = $this->getStudentEnrolledCourseIds($user->id);
            $bundleCourseIds = $item->courses()->pluck('courses.id')->toArray();
            $ownedCourseIds = array_values(array_intersect($bundleCourseIds, $studentOwnedCourses));

            $policyApplied = $item->ownership_policy ?: 'full_bundle_price';
            if ($policyApplied === 'exclude_owned_courses' && ! empty($ownedCourseIds)) {
                // Calculate proportional discount or sum of owned courses price
                $ownedCourses = Course::whereIn('id', $ownedCourseIds)->get();
                foreach ($ownedCourses as $c) {
                    $cPrice = $this->pricingService->getItemPrice($c, $currency)['final_price'];
                    $ownedDiscount += $cPrice;
                }
                // Cap discount at bundle price
                $ownedDiscount = min($priceAfterPromo, round($ownedDiscount, 2));
            }
        }

        $subtotalAfterOwned = max(0.00, round($priceAfterPromo - $ownedDiscount, 2));

        // Coupon calculation
        $coupon = null;
        $couponDiscount = 0.00;
        $couponError = null;

        if ($couponCode && $item->is_coupon_eligible) {
            $type = $item instanceof Course ? 'course' : 'bundle';
            $couponResult = $this->couponService->validate($couponCode, $subtotalAfterOwned, $user->id, $type, $item->id);
            if ($couponResult['valid']) {
                $coupon = $couponResult['coupon'];
                $couponDiscount = $couponResult['discount'];
            } else {
                $couponError = $couponResult['error'];
            }
        }

        $totalAmount = max(0.00, round($subtotalAfterOwned - $couponDiscount, 2));

        // Installment schedule
        $installments = [];
        $depositDueNow = $totalAmount;
        if ($installmentPlan && $totalAmount > 0.00) {
            $installments = $installmentPlan->computeSchedule($totalAmount, $currency);
            $depositDueNow = $installments[0]['amount'] ?? $totalAmount;
        }

        return [
            'item' => $item,
            'currency' => $currency,
            'base_amount' => $basePrice,
            'promo_discount' => $promoDiscount,
            'owned_courses_discount' => $ownedDiscount,
            'owned_course_ids' => $ownedCourseIds,
            'ownership_policy' => $policyApplied,
            'coupon' => $coupon,
            'coupon_code' => $coupon?->code,
            'coupon_discount' => $couponDiscount,
            'coupon_error' => $couponError,
            'total_amount' => $totalAmount,
            'installment_plan' => $installmentPlan,
            'installments' => $installments,
            'deposit_due_now' => $depositDueNow,
        ];
    }

    /**
     * Create an order from checkout data.
     */
    public function createOrder(
        User $user,
        Course|CourseBundle $item,
        ?string $currency = null,
        ?string $couponCode = null,
        ?CommerceInstallmentPlan $installmentPlan = null,
        ?string $paymentMethod = null
    ): CommerceOrder {
        if (! $user->hasRole('student')) {
            throw new AuthorizationException('Only students may make purchases. Administrators and trainers cannot purchase courses directly.');
        }

        $currency = strtoupper($currency ?: $this->pricingService->resolveCurrency());
        $calc = $this->calculateCheckout($user, $item, $currency, $couponCode, $installmentPlan);

        return DB::transaction(function () use ($user, $item, $calc, $paymentMethod) {
            $snapshot = $this->pricingService->createSnapshot($item, $calc['currency'], [
                'promo_discount' => $calc['promo_discount'],
                'owned_courses_discount' => $calc['owned_courses_discount'],
                'owned_course_ids' => $calc['owned_course_ids'],
                'coupon_code' => $calc['coupon_code'],
                'coupon_discount' => $calc['coupon_discount'],
            ]);

            $order = CommerceOrder::create([
                'academy_id' => $item->academy_id,
                'user_id' => $user->id,
                'order_number' => CommerceOrder::generateOrderNumber(),
                'orderable_type' => $item instanceof Course ? 'course' : 'bundle',
                'orderable_id' => $item->id,
                'currency' => $calc['currency'],
                'base_amount' => $calc['base_amount'],
                'discount_amount' => $calc['promo_discount'],
                'coupon_id' => $calc['coupon']?->id,
                'coupon_code' => $calc['coupon_code'],
                'coupon_discount_amount' => $calc['coupon_discount'],
                'owned_courses_discount' => $calc['owned_courses_discount'],
                'total_amount' => $calc['total_amount'],
                'paid_amount' => 0.00,
                'outstanding_balance' => $calc['total_amount'],
                'payment_status' => $calc['total_amount'] <= 0.00 ? 'paid' : 'unpaid',
                'payment_method' => $paymentMethod,
                'installment_plan_id' => $calc['installment_plan']?->id,
                'is_installment' => $calc['installment_plan'] !== null,
                'ownership_policy_applied' => $calc['ownership_policy'],
                'price_snapshot' => $snapshot,
                'terms_snapshot' => [
                    'installment_plan' => $calc['installment_plan']?->title,
                    'milestone_percentage' => $calc['installment_plan']?->completion_milestone_percentage,
                    'access_policy' => $calc['installment_plan']?->access_policy,
                ],
            ]);

            // Save installments
            if (! empty($calc['installments'])) {
                foreach ($calc['installments'] as $inst) {
                    CommerceOrderInstallment::create([
                        'academy_id' => $item->academy_id,
                        'order_id' => $order->id,
                        'installment_number' => $inst['installment_number'],
                        'amount' => $inst['amount'],
                        'paid_amount' => 0.00,
                        'currency' => $calc['currency'],
                        'due_condition' => $inst['due_condition'],
                        'due_date' => $inst['due_date'],
                        'milestone_percentage' => $inst['milestone_percentage'],
                        'status' => $inst['status'],
                    ]);
                }
            }

            // If coupon applied, record redemption
            if ($calc['coupon'] && $calc['coupon_discount'] > 0.00) {
                $this->couponService->redeem($calc['coupon'], $order, $user, $calc['coupon_discount']);
            }

            // Free item -> grant enrollment immediately
            if ($calc['total_amount'] <= 0.00) {
                $this->grantEnrollments($order);
            }

            return $order;
        });
    }

    /**
     * Record a verified payment against an order.
     */
    public function recordPayment(
        CommerceOrder $order,
        float $amount,
        string $paymentGateway,
        array $gatewayData = [],
        ?CommerceOrderInstallment $installment = null,
        ?User $recordedBy = null
    ): CommerceTransaction {
        return DB::transaction(function () use ($order, $amount, $paymentGateway, $gatewayData, $installment, $recordedBy) {
            $transaction = CommerceTransaction::create([
                'academy_id' => $order->academy_id,
                'order_id' => $order->id,
                'installment_id' => $installment?->id,
                'user_id' => $order->user_id,
                'transaction_type' => 'payment',
                'amount' => $amount,
                'currency' => $order->currency,
                'payment_gateway' => $paymentGateway,
                'gateway_order_id' => $gatewayData['gateway_order_id'] ?? null,
                'gateway_payment_id' => $gatewayData['gateway_payment_id'] ?? null,
                'gateway_signature' => $gatewayData['gateway_signature'] ?? null,
                'gateway_response' => $gatewayData,
                'status' => 'successful',
                'reference_number' => $gatewayData['reference_number'] ?? ('TXN-'.strtoupper(Str::random(10))),
                'notes' => $gatewayData['notes'] ?? null,
                'recorded_by_user_id' => $recordedBy?->id,
                'paid_at' => now(),
            ]);

            // Update installment if supplied or find next due installment
            $targetInstallment = $installment;
            if (! $targetInstallment && $order->is_installment) {
                $targetInstallment = $order->installments()
                    ->whereIn('status', ['due', 'upcoming', 'partially_paid'])
                    ->first();
            }

            if ($targetInstallment) {
                $newPaid = round((float) $targetInstallment->paid_amount + $amount, 2);
                $targetInstallment->paid_amount = $newPaid;
                if ($newPaid >= (float) $targetInstallment->amount) {
                    $targetInstallment->status = 'paid';
                    $targetInstallment->paid_at = now();
                } else {
                    $targetInstallment->status = 'partially_paid';
                }
                $targetInstallment->save();
            }

            // Update order payment method if empty
            if (empty($order->payment_method)) {
                $order->payment_method = $paymentGateway;
            }

            $order->recalculateBalances();

            // Check access policy to grant enrollments
            $shouldGrant = false;
            if ($order->payment_status === 'paid') {
                $shouldGrant = true;
            } elseif ($order->is_installment) {
                $policy = $order->installmentPlan?->access_policy ?? 'immediate_on_deposit';
                if ($policy === 'immediate_on_deposit' && (float) $order->paid_amount > 0) {
                    $shouldGrant = true;
                }
            }

            if ($shouldGrant) {
                $this->grantEnrollments($order);
            }

            return $transaction;
        });
    }

    /**
     * Direct Admin Assignment without online payment (cash, bank transfer, complimentary, scholarship).
     */
    public function adminDirectAssign(
        User $student,
        Course|CourseBundle $item,
        string $paymentMethod,
        ?float $amountPaid = null,
        ?string $reason = null,
        ?string $notes = null,
        ?User $admin = null
    ): CommerceOrder {
        return DB::transaction(function () use ($student, $item, $paymentMethod, $amountPaid, $reason, $notes, $admin) {
            $currency = $item->currency ?: $this->pricingService->getAcademyCurrency();
            $pricing = $this->pricingService->getItemPrice($item, $currency);
            $totalAmount = $pricing['final_price'];

            $isComplimentary = in_array($paymentMethod, ['complimentary', 'scholarship', 'sponsored'], true);

            $order = CommerceOrder::create([
                'academy_id' => $item->academy_id,
                'user_id' => $student->id,
                'order_number' => CommerceOrder::generateOrderNumber(),
                'orderable_type' => $item instanceof Course ? 'course' : 'bundle',
                'orderable_id' => $item->id,
                'currency' => $currency,
                'base_amount' => $pricing['original_price'],
                'discount_amount' => $pricing['discount_amount'],
                'total_amount' => $totalAmount,
                'paid_amount' => $isComplimentary ? 0.00 : (float) ($amountPaid ?? $totalAmount),
                'outstanding_balance' => $isComplimentary ? 0.00 : max(0.00, round($totalAmount - (float) ($amountPaid ?? $totalAmount), 2)),
                'payment_status' => $isComplimentary ? 'complimentary' : ((float) ($amountPaid ?? $totalAmount) >= $totalAmount ? 'paid' : 'partially_paid'),
                'payment_method' => $paymentMethod,
                'assigned_by_user_id' => $admin?->id,
                'assignment_reason' => $reason,
                'notes' => $notes,
                'price_snapshot' => $this->pricingService->createSnapshot($item, $currency, ['admin_assigned' => true]),
            ]);

            // If offline cash/bank transfer payment was collected, record transaction
            if (! $isComplimentary && ($amountPaid ?? 0) > 0) {
                CommerceTransaction::create([
                    'academy_id' => $item->academy_id,
                    'order_id' => $order->id,
                    'user_id' => $student->id,
                    'transaction_type' => 'payment',
                    'amount' => (float) $amountPaid,
                    'currency' => $currency,
                    'payment_gateway' => $paymentMethod,
                    'status' => 'successful',
                    'reference_number' => 'OFFLINE-'.strtoupper(Str::random(8)),
                    'notes' => $notes ?: "Offline payment via {$paymentMethod}",
                    'recorded_by_user_id' => $admin?->id,
                    'paid_at' => now(),
                ]);
            }

            // Grant enrollments immediately
            $this->grantEnrollments($order, $admin);

            return $order;
        });
    }

    /**
     * Grant enrollments for courses covered by the order.
     * Preserves existing enrollments, progress, submissions, grades and certificates.
     */
    public function grantEnrollments(CommerceOrder $order, ?User $assignedBy = null): Collection
    {
        $user = $order->user;
        $profile = StudentProfile::firstOrCreate(
            ['user_id' => $user->id, 'academy_id' => $order->academy_id],
            ['student_code' => 'STU-'.strtoupper(substr(uniqid(), -6))]
        );

        $coursesToEnroll = [];
        $bundleId = null;

        if ($order->orderable_type === 'course') {
            $course = Course::find($order->orderable_id);
            if ($course) {
                $coursesToEnroll[] = $course;
            }
        } elseif ($order->orderable_type === 'bundle') {
            $bundle = CourseBundle::find($order->orderable_id);
            if ($bundle) {
                $bundleId = $bundle->id;
                $coursesToEnroll = $bundle->courses()->get();
            }
        }

        $enrollments = collect();

        foreach ($coursesToEnroll as $course) {
            $existing = Enrollment::where('student_profile_id', $profile->id)
                ->where('course_id', $course->id)
                ->first();

            if ($existing) {
                // If existing enrollment is suspended or cancelled, restore it without touching academic records
                if (in_array($existing->status, [Enrollment::STATUS_SUSPENDED, Enrollment::STATUS_CANCELLED])) {
                    $existing->status = Enrollment::STATUS_ACTIVE;
                    $existing->suspended_at = null;
                    $existing->suspension_reason = null;
                }
                $existing->order_id = $order->id;
                if ($bundleId) {
                    $existing->bundle_id = $bundleId;
                }
                $existing->payment_status = $order->payment_status;
                $existing->save();
                $enrollments->push($existing);
            } else {
                // Create new active enrollment
                $newEnrollment = Enrollment::create([
                    'academy_id' => $order->academy_id,
                    'student_profile_id' => $profile->id,
                    'course_id' => $course->id,
                    'order_id' => $order->id,
                    'bundle_id' => $bundleId,
                    'payment_status' => $order->payment_status,
                    'status' => Enrollment::STATUS_ACTIVE,
                    'enrolled_at' => now(),
                    'starts_at' => now(),
                    'assigned_by' => $assignedBy?->id ?: $order->assigned_by_user_id,
                ]);
                $enrollments->push($newEnrollment);
            }
        }

        return $enrollments;
    }
}
