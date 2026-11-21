<?php

namespace App\Domain\Commerce\Models;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Enrollments\Models\Enrollment;
use App\Domain\Tenants\Concerns\BelongsToAcademy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommerceOrder extends Model
{
    use BelongsToAcademy, HasFactory;

    protected $table = 'commerce_orders';

    protected $fillable = [
        'academy_id',
        'user_id',
        'order_number',
        'orderable_type',
        'orderable_id',
        'currency',
        'base_amount',
        'discount_amount',
        'coupon_id',
        'coupon_code',
        'coupon_discount_amount',
        'owned_courses_discount',
        'total_amount',
        'paid_amount',
        'outstanding_balance',
        'payment_status', // unpaid, partially_paid, paid, complimentary, refunded
        'payment_method',
        'installment_plan_id',
        'is_installment',
        'ownership_policy_applied',
        'price_snapshot',
        'terms_snapshot',
        'assigned_by_user_id',
        'assignment_reason',
        'notes',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'coupon_discount_amount' => 'decimal:2',
        'owned_courses_discount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'is_installment' => 'boolean',
        'price_snapshot' => 'array',
        'terms_snapshot' => 'array',
    ];

    public static function generateOrderNumber(): string
    {
        return 'ORD-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -6));
    }

    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(CommerceCoupon::class, 'coupon_id');
    }

    public function installmentPlan(): BelongsTo
    {
        return $this->belongsTo(CommerceInstallmentPlan::class, 'installment_plan_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(CommerceOrderInstallment::class, 'order_id')->orderBy('installment_number');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CommerceTransaction::class, 'order_id')->latest();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'order_id');
    }

    public function getTargetItemAttribute(): ?Model
    {
        if ($this->orderable_type === 'course') {
            return Course::find($this->orderable_id);
        }
        if ($this->orderable_type === 'bundle') {
            return CourseBundle::find($this->orderable_id);
        }

        return null;
    }

    public function getTargetTitleAttribute(): string
    {
        $item = $this->getTargetItemAttribute();

        return $item ? $item->title : ucfirst($this->orderable_type).' #'.$this->orderable_id;
    }

    public function recalculateBalances(): void
    {
        $paid = (float) $this->transactions()
            ->where('status', 'successful')
            ->whereIn('transaction_type', ['payment', 'waiver'])
            ->sum('amount');

        $refunded = (float) $this->transactions()
            ->where('status', 'successful')
            ->where('transaction_type', 'refund')
            ->sum('amount');

        $netPaid = max(0.00, round($paid - $refunded, 2));
        $this->paid_amount = $netPaid;
        $this->outstanding_balance = max(0.00, round((float) $this->total_amount - $netPaid, 2));

        if ($this->payment_method === 'complimentary' || $this->payment_method === 'scholarship') {
            $this->payment_status = 'complimentary';
            $this->outstanding_balance = 0.00;
        } elseif ($this->outstanding_balance <= 0.00 && (float) $this->total_amount > 0) {
            $this->payment_status = 'paid';
        } elseif ($netPaid > 0.00) {
            $this->payment_status = 'partially_paid';
        } else {
            $this->payment_status = 'unpaid';
        }

        $this->save();
    }
}
