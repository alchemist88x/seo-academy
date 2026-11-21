<?php

namespace App\Domain\Commerce\Models;

use App\Domain\Academies\Models\Academy;
use App\Domain\Tenants\Concerns\BelongsToAcademy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceCouponRedemption extends Model
{
    use BelongsToAcademy, HasFactory;

    protected $table = 'commerce_coupon_redemptions';

    protected $fillable = [
        'academy_id',
        'coupon_id',
        'user_id',
        'order_id',
        'discount_amount',
        'currency',
        'redeemed_at',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'redeemed_at' => 'datetime',
    ];

    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(CommerceCoupon::class, 'coupon_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CommerceOrder::class, 'order_id');
    }
}
