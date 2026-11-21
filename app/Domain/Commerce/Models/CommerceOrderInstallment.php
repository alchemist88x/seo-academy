<?php

namespace App\Domain\Commerce\Models;

use App\Domain\Academies\Models\Academy;
use App\Domain\Tenants\Concerns\BelongsToAcademy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommerceOrderInstallment extends Model
{
    use BelongsToAcademy, HasFactory;

    protected $table = 'commerce_order_installments';

    protected $fillable = [
        'academy_id',
        'order_id',
        'installment_number',
        'amount',
        'paid_amount',
        'currency',
        'due_condition', // initial_deposit, completion_milestone, calendar_date
        'due_date',
        'milestone_percentage',
        'milestone_triggered_at',
        'status', // upcoming, due, partially_paid, paid, overdue, waived, cancelled
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_date' => 'datetime',
        'milestone_percentage' => 'decimal:2',
        'milestone_triggered_at' => 'datetime',
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

    public function transactions(): HasMany
    {
        return $this->hasMany(CommerceTransaction::class, 'installment_id');
    }

    public function getRemainingBalanceAttribute(): float
    {
        return max(0.00, round((float) $this->amount - (float) $this->paid_amount, 2));
    }
}
