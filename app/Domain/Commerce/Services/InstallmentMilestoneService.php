<?php

namespace App\Domain\Commerce\Services;

use App\Domain\Commerce\Models\CommerceOrder;
use App\Domain\Commerce\Models\CommerceOrderInstallment;
use App\Domain\Learning\Services\LearningProgressService;
use App\Domain\Notifications\Models\AcademyNotification;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InstallmentMilestoneService
{
    public function __construct(
        protected LearningProgressService $progressService,
    ) {}

    /**
     * Compute current learning progress for an order.
     */
    public function calculateOrderProgress(CommerceOrder $order): float
    {
        $profile = StudentProfile::where('user_id', $order->user_id)->first();
        if (! $profile) {
            return 0.0;
        }

        if ($order->orderable_type === 'course') {
            $enrollment = Enrollment::where('student_profile_id', $profile->id)
                ->where('course_id', $order->orderable_id)
                ->first();

            if (! $enrollment) {
                return 0.0;
            }

            $summary = $this->progressService->getCourseProgressSummary($enrollment);

            return (float) ($summary['completion_percentage'] ?? 0.0);
        }

        if ($order->orderable_type === 'bundle') {
            $enrollments = Enrollment::where('student_profile_id', $profile->id)
                ->where('bundle_id', $order->orderable_id)
                ->get();

            if ($enrollments->isEmpty()) {
                return 0.0;
            }

            $plan = $order->installmentPlan;
            $basis = $plan?->bundle_completion_basis ?? 'average';

            $percentages = [];
            foreach ($enrollments as $enr) {
                $summary = $this->progressService->getCourseProgressSummary($enr);
                $percentages[] = (float) ($summary['completion_percentage'] ?? 0.0);
            }

            if ($basis === 'all_courses') {
                return min($percentages);
            }
            if ($basis === 'primary_course') {
                return $percentages[0] ?? 0.0;
            }

            // Default: average
            return round(array_sum($percentages) / count($percentages), 2);
        }

        return 0.0;
    }

    /**
     * Check and trigger milestone installment if progress meets or exceeds threshold.
     * Returns true if triggered in this call, false otherwise.
     */
    public function evaluateAndTriggerMilestone(CommerceOrder $order): bool
    {
        if (! $order->is_installment) {
            return false;
        }

        // Find upcoming milestone installment
        $milestoneInstallment = $order->installments()
            ->where('due_condition', 'completion_milestone')
            ->where('status', 'upcoming')
            ->whereNull('milestone_triggered_at')
            ->first();

        if (! $milestoneInstallment) {
            return false;
        }

        $threshold = (float) ($milestoneInstallment->milestone_percentage ?? 60.00);
        $currentProgress = $this->calculateOrderProgress($order);

        // Strict boundary check: >= threshold triggers (e.g. 60.00% or 61.00%), < threshold does not (e.g. 59.99%)
        if ($currentProgress < $threshold) {
            return false;
        }

        return DB::transaction(function () use ($order, $milestoneInstallment, $threshold, $currentProgress) {
            // Re-check with row lock for idempotency
            $locked = CommerceOrderInstallment::where('id', $milestoneInstallment->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->status !== 'upcoming' || $locked->milestone_triggered_at !== null) {
                return false;
            }

            $graceDays = $order->installmentPlan?->grace_period_days ?? 7;
            $dueDate = now()->addDays($graceDays);

            $locked->update([
                'status' => 'due',
                'milestone_triggered_at' => now(),
                'due_date' => $dueDate,
                'notes' => "Triggered by reaching {$currentProgress}% course completion milestone (threshold: {$threshold}%).",
            ]);

            // Notify student
            try {
                AcademyNotification::create([
                    'academy_id' => $order->academy_id,
                    'type' => 'installment_milestone_due',
                    'target' => (string) $order->user_id,
                    'title' => "Installment Due: {$threshold}% Completion Milestone Reached",
                    'message' => "Congratulations on reaching {$currentProgress}% completion! Your remaining balance of {$locked->currency} {$locked->amount} is now due by ".$dueDate->toFormattedDateString().'.',
                    'data' => [
                        'order_id' => $order->id,
                        'installment_id' => $locked->id,
                        'amount' => $locked->amount,
                        'currency' => $locked->currency,
                        'due_date' => $dueDate->toIso8601String(),
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to dispatch installment notification: '.$e->getMessage());
            }

            return true;
        });
    }

    /**
     * Scan all active orders with milestone or date installments and process them.
     */
    public function processAllActiveInstallments(): array
    {
        $triggeredCount = 0;
        $overdueCount = 0;

        // 1. Process milestone completions
        $milestoneOrders = CommerceOrder::where('is_installment', true)
            ->whereHas('installments', function ($q) {
                $q->where('due_condition', 'completion_milestone')
                    ->where('status', 'upcoming');
            })
            ->get();

        foreach ($milestoneOrders as $order) {
            if ($this->evaluateAndTriggerMilestone($order)) {
                $triggeredCount++;
            }
        }

        // 2. Process overdue installments past due_date
        $overdueInstallments = CommerceOrderInstallment::whereIn('status', ['due', 'partially_paid'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->get();

        foreach ($overdueInstallments as $inst) {
            $inst->status = 'overdue';
            $inst->save();
            $overdueCount++;

            // Apply access restriction if policy requires
            $order = $inst->order;
            if ($order && $order->installmentPlan?->access_policy === 'restrict_when_overdue') {
                $profile = StudentProfile::where('user_id', $order->user_id)->first();
                if ($profile) {
                    Enrollment::where('student_profile_id', $profile->id)
                        ->where('order_id', $order->id)
                        ->where('status', Enrollment::STATUS_ACTIVE)
                        ->update([
                            'status' => Enrollment::STATUS_SUSPENDED,
                            'suspended_at' => now(),
                            'suspension_reason' => 'Installment payment overdue.',
                        ]);
                }
            }
        }

        return [
            'milestones_triggered' => $triggeredCount,
            'marked_overdue' => $overdueCount,
        ];
    }
}
