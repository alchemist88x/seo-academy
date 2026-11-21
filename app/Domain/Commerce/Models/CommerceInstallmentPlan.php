<?php

namespace App\Domain\Commerce\Models;

use App\Domain\Academies\Models\Academy;
use App\Domain\Tenants\Concerns\BelongsToAcademy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommerceInstallmentPlan extends Model
{
    use BelongsToAcademy, HasFactory;

    protected $table = 'commerce_installment_plans';

    protected $fillable = [
        'academy_id',
        'title',
        'plan_type', // milestone_completion, fixed_dates, custom
        'initial_deposit_type', // percentage, fixed
        'initial_deposit_value',
        'number_of_installments',
        'completion_milestone_percentage', // default 60.00
        'bundle_completion_basis', // average, all_courses, primary_course
        'access_policy', // immediate_on_deposit, full_payment_only, restrict_when_overdue
        'grace_period_days',
        'is_active',
    ];

    protected $casts = [
        'initial_deposit_value' => 'decimal:2',
        'number_of_installments' => 'integer',
        'completion_milestone_percentage' => 'decimal:2',
        'grace_period_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(CommerceOrder::class, 'installment_plan_id');
    }

    /**
     * Compute breakdown of installments for a given total order amount.
     * Guaranteed deterministic rounding: sum of all installment amounts equals total.
     */
    public function computeSchedule(float $totalAmount, string $currency = 'USD'): array
    {
        $installments = [];
        $numInstallments = max(2, $this->number_of_installments);

        // Initial deposit
        $deposit = 0.00;
        if ($this->initial_deposit_type === 'percentage') {
            $deposit = round(($totalAmount * (float) $this->initial_deposit_value) / 100, 2);
        } else {
            $deposit = round(min($totalAmount, (float) $this->initial_deposit_value), 2);
        }
        $deposit = min($totalAmount, max(0.01, $deposit));

        $installments[] = [
            'installment_number' => 1,
            'amount' => $deposit,
            'due_condition' => 'initial_deposit',
            'due_date' => now(),
            'milestone_percentage' => null,
            'status' => 'due',
        ];

        $remainingBalance = round($totalAmount - $deposit, 2);
        $subsequentCount = $numInstallments - 1;

        if ($subsequentCount === 1) {
            // Special single milestone installment (e.g. 60% completion plan)
            $installments[] = [
                'installment_number' => 2,
                'amount' => $remainingBalance,
                'due_condition' => $this->plan_type === 'milestone_completion' ? 'completion_milestone' : 'calendar_date',
                'due_date' => $this->plan_type === 'milestone_completion' ? null : now()->addDays(30),
                'milestone_percentage' => $this->plan_type === 'milestone_completion' ? (float) $this->completion_milestone_percentage : null,
                'status' => 'upcoming',
            ];
        } else {
            $baseSubsequent = floor(($remainingBalance / $subsequentCount) * 100) / 100;
            $allocated = 0.00;
            for ($i = 2; $i <= $numInstallments; $i++) {
                if ($i === $numInstallments) {
                    $instAmount = round($remainingBalance - $allocated, 2);
                } else {
                    $instAmount = $baseSubsequent;
                    $allocated = round($allocated + $instAmount, 2);
                }
                $installments[] = [
                    'installment_number' => $i,
                    'amount' => $instAmount,
                    'due_condition' => 'calendar_date',
                    'due_date' => now()->addDays(($i - 1) * 30),
                    'milestone_percentage' => null,
                    'status' => 'upcoming',
                ];
            }
        }

        return $installments;
    }
}
