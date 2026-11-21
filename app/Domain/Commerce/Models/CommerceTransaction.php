<?php

namespace App\Domain\Commerce\Models;

use App\Domain\Academies\Models\Academy;
use App\Domain\Tenants\Concerns\BelongsToAcademy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceTransaction extends Model
{
    use BelongsToAcademy, HasFactory;

    protected $table = 'commerce_transactions';

    protected $fillable = [
        'academy_id',
        'order_id',
        'installment_id',
        'user_id',
        'transaction_type', // payment, refund, waiver
        'amount',
        'currency',
        'payment_gateway', // razorpay, manual, cash, bank_transfer, waiver, scholarship
        'gateway_order_id',
        'gateway_payment_id',
        'gateway_signature',
        'gateway_response',
        'status', // successful, failed, pending, refunded
        'reference_number',
        'notes',
        'recorded_by_user_id',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'paid_at' => 'datetime',
    ];

    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CommerceOrder::class, 'order_id');
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(CommerceOrderInstallment::class, 'installment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
