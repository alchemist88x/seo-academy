<?php

namespace App\Domain\Commerce\Models;

use App\Domain\Academies\Models\Academy;
use App\Domain\Tenants\Concerns\BelongsToAcademy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommerceCoupon extends Model
{
    use BelongsToAcademy, HasFactory;

    protected $table = 'commerce_coupons';

    protected $fillable = [
        'academy_id',
        'code',
        'description',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'min_purchase_amount',
        'currency',
        'starts_at',
        'expires_at',
        'global_usage_limit',
        'per_student_usage_limit',
        'usage_count',
        'first_purchase_only',
        'eligible_course_ids',
        'eligible_bundle_ids',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'min_purchase_amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'global_usage_limit' => 'integer',
        'per_student_usage_limit' => 'integer',
        'usage_count' => 'integer',
        'first_purchase_only' => 'boolean',
        'eligible_course_ids' => 'array',
        'eligible_bundle_ids' => 'array',
        'is_active' => 'boolean',
    ];

    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CommerceCouponRedemption::class, 'coupon_id');
    }

    public function isValidFor(float $subtotal, string|int $userId, string $type, string|int $id, ?string &$reason = null): bool
    {
        if (! $this->is_active) {
            $reason = 'Coupon is not active.';

            return false;
        }

        $now = now();
        if ($this->starts_at && $this->starts_at > $now) {
            $reason = 'Coupon is not yet active.';

            return false;
        }

        if ($this->expires_at && $this->expires_at < $now) {
            $reason = 'Coupon has expired.';

            return false;
        }

        if ($this->min_purchase_amount && $subtotal < (float) $this->min_purchase_amount) {
            $reason = "Minimum purchase amount of {$this->currency} {$this->min_purchase_amount} required.";

            return false;
        }

        if ($this->global_usage_limit !== null && $this->usage_count >= $this->global_usage_limit) {
            $reason = 'Coupon global usage limit reached.';

            return false;
        }

        // Student usage limit
        $userRedemptions = $this->redemptions()->where('user_id', $userId)->count();
        if ($this->per_student_usage_limit !== null && $userRedemptions >= $this->per_student_usage_limit) {
            $reason = 'You have already reached the redemption limit for this coupon.';

            return false;
        }

        // First purchase only
        if ($this->first_purchase_only) {
            $priorCompletedOrders = CommerceOrder::where('user_id', $userId)
                ->whereIn('payment_status', ['paid', 'partially_paid'])
                ->count();
            if ($priorCompletedOrders > 0) {
                $reason = 'Coupon is only valid for your first purchase.';

                return false;
            }
        }

        // Item eligibility
        if ($type === 'course' && ! empty($this->eligible_course_ids)) {
            if (! in_array($id, (array) $this->eligible_course_ids)) {
                $reason = 'This coupon is not valid for this course.';

                return false;
            }
        }

        if ($type === 'bundle' && ! empty($this->eligible_bundle_ids)) {
            if (! in_array($id, (array) $this->eligible_bundle_ids)) {
                $reason = 'This coupon is not valid for this bundle.';

                return false;
            }
        }

        return true;
    }

    public function calculateDiscount(float $subtotal): float
    {
        $discount = 0.00;
        if ($this->discount_type === 'percentage') {
            $discount = ($subtotal * (float) $this->discount_value) / 100;
        } else {
            $discount = (float) $this->discount_value;
        }

        if ($this->max_discount_amount !== null && (float) $this->max_discount_amount > 0) {
            $discount = min($discount, (float) $this->max_discount_amount);
        }

        return min($subtotal, max(0.00, round($discount, 2)));
    }
}
