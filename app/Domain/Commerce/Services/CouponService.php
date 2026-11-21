<?php

namespace App\Domain\Commerce\Services;

use App\Domain\Commerce\Models\CommerceCoupon;
use App\Domain\Commerce\Models\CommerceCouponRedemption;
use App\Domain\Commerce\Models\CommerceOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CouponService
{
    /**
     * Validate coupon code for given item and user.
     */
    public function validate(string $code, float $subtotal, string|int $userId, string $type, string|int $itemId): array
    {
        $code = trim(strtoupper($code));
        if ($code === '') {
            return ['valid' => false, 'error' => 'Coupon code is required.', 'discount' => 0.00];
        }

        $coupon = CommerceCoupon::where('code', $code)->first();
        if (! $coupon) {
            return ['valid' => false, 'error' => 'Coupon code not found.', 'discount' => 0.00];
        }

        $reason = null;
        if (! $coupon->isValidFor($subtotal, $userId, $type, $itemId, $reason)) {
            return ['valid' => false, 'error' => $reason ?: 'Coupon cannot be applied.', 'discount' => 0.00];
        }

        $discount = $coupon->calculateDiscount($subtotal);

        return [
            'valid' => true,
            'coupon' => $coupon,
            'discount' => $discount,
            'error' => null,
        ];
    }

    /**
     * Atomically redeem coupon for an order.
     */
    public function redeem(CommerceCoupon $coupon, CommerceOrder $order, User $user, float $discount): CommerceCouponRedemption
    {
        return DB::transaction(function () use ($coupon, $order, $user, $discount) {
            // Lock coupon record for concurrency safety
            $lockedCoupon = CommerceCoupon::where('id', $coupon->id)->lockForUpdate()->first();
            $lockedCoupon->increment('usage_count');

            return CommerceCouponRedemption::create([
                'academy_id' => $order->academy_id,
                'coupon_id' => $coupon->id,
                'user_id' => $user->id,
                'order_id' => $order->id,
                'discount_amount' => $discount,
                'currency' => $order->currency,
                'redeemed_at' => now(),
            ]);
        });
    }
}
