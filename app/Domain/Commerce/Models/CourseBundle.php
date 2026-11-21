<?php

namespace App\Domain\Commerce\Models;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Tenants\Concerns\BelongsToAcademy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CourseBundle extends Model
{
    use BelongsToAcademy, HasFactory;

    protected $table = 'course_bundles';

    protected $fillable = [
        'academy_id',
        'title',
        'slug',
        'description',
        'thumbnail_path',
        'price',
        'currency',
        'original_total_price',
        'discount_price',
        'discount_percentage',
        'discount_starts_at',
        'discount_expires_at',
        'regional_prices',
        'status',
        'is_coupon_eligible',
        'installment_plan_id',
        'ownership_policy',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'original_total_price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'discount_starts_at' => 'datetime',
        'discount_expires_at' => 'datetime',
        'regional_prices' => 'array',
        'is_coupon_eligible' => 'boolean',
    ];

    public static function generateUniqueSlug(string $title, ?int $ignoreId = null, string|int|null $academyId = null): string
    {
        $slug = Str::slug($title);
        $original = $slug;
        $counter = 1;

        $query = static::where('slug', $slug);
        if ($academyId) {
            $query->where('academy_id', $academyId);
        }
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        while ($query->exists()) {
            $slug = $original.'-'.$counter++;
            $query = static::where('slug', $slug);
            if ($academyId) {
                $query->where('academy_id', $academyId);
            }
            if ($ignoreId) {
                $query->where('id', '!=', $ignoreId);
            }
        }

        return $slug;
    }

    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_bundle_courses', 'bundle_id', 'course_id')
            ->withPivot('display_order')
            ->orderByPivot('display_order')
            ->withTimestamps();
    }

    public function installmentPlan(): BelongsTo
    {
        return $this->belongsTo(CommerceInstallmentPlan::class, 'installment_plan_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(CommerceOrder::class, 'orderable_id')->where('orderable_type', 'bundle');
    }

    public function getEffectivePriceAttribute(): float
    {
        $now = now();
        if ($this->discount_price !== null && $this->discount_price > 0) {
            if (($this->discount_starts_at === null || $this->discount_starts_at <= $now) &&
                ($this->discount_expires_at === null || $this->discount_expires_at >= $now)) {
                return (float) $this->discount_price;
            }
        }

        if ($this->discount_percentage !== null && $this->discount_percentage > 0) {
            if (($this->discount_starts_at === null || $this->discount_starts_at <= $now) &&
                ($this->discount_expires_at === null || $this->discount_expires_at >= $now)) {
                $discount = ($this->price * $this->discount_percentage) / 100;

                return max(0.00, round((float) ($this->price - $discount), 2));
            }
        }

        return (float) $this->price;
    }

    public function calculateCalculatedSavings(): float
    {
        $original = (float) ($this->original_total_price ?: $this->courses()->sum('price'));
        $effective = $this->getEffectivePriceAttribute();

        return max(0.00, round($original - $effective, 2));
    }
}
