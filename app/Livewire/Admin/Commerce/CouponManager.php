<?php

namespace App\Livewire\Admin\Commerce;

use App\Domain\Academies\Models\Academy;
use App\Domain\Commerce\Models\CommerceCoupon;
use App\Domain\Commerce\Models\CourseBundle;
use App\Domain\Commerce\Services\CurrencyAndPricingService;
use App\Domain\Courses\Models\Course;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Discount Coupons Management')]
class CouponManager extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    public bool $showModal = false;

    public ?int $editingCouponId = null;

    // Form fields
    public string $code = '';

    public string $description = '';

    public string $discount_type = 'percentage';

    public float $discount_value = 10.00;

    public ?float $max_discount_amount = null;

    public ?float $min_purchase_amount = null;

    public string $currency = 'INR';

    public ?string $starts_at = null;

    public ?string $expires_at = null;

    public ?int $global_usage_limit = null;

    public int $per_student_usage_limit = 1;

    public bool $first_purchase_only = false;

    public bool $is_active = true;

    public array $eligibleCourseIds = [];

    public array $eligibleBundleIds = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->reset([
            'editingCouponId', 'code', 'description', 'discount_type',
            'discount_value', 'max_discount_amount', 'min_purchase_amount',
            'starts_at', 'expires_at', 'global_usage_limit',
            'per_student_usage_limit', 'first_purchase_only',
            'eligibleCourseIds', 'eligibleBundleIds',
        ]);
        $this->discount_type = 'percentage';
        $this->discount_value = 10.00;
        $this->currency = app(CurrencyAndPricingService::class)->getAcademyCurrency();
        $this->per_student_usage_limit = 1;
        $this->is_active = true;
        $this->showModal = true;
    }

    public function editCoupon(int $id): void
    {
        $coupon = CommerceCoupon::findOrFail($id);
        $this->editingCouponId = $coupon->id;
        $this->code = $coupon->code;
        $this->description = $coupon->description ?? '';
        $this->discount_type = $coupon->discount_type;
        $this->discount_value = (float) $coupon->discount_value;
        $this->max_discount_amount = $coupon->max_discount_amount ? (float) $coupon->max_discount_amount : null;
        $this->min_purchase_amount = $coupon->min_purchase_amount ? (float) $coupon->min_purchase_amount : null;
        $this->currency = $coupon->currency ?: app(CurrencyAndPricingService::class)->getAcademyCurrency();
        $this->starts_at = $coupon->starts_at ? $coupon->starts_at->format('Y-m-d\TH:i') : null;
        $this->expires_at = $coupon->expires_at ? $coupon->expires_at->format('Y-m-d\TH:i') : null;
        $this->global_usage_limit = $coupon->global_usage_limit;
        $this->per_student_usage_limit = $coupon->per_student_usage_limit ?? 1;
        $this->first_purchase_only = (bool) $coupon->first_purchase_only;
        $this->is_active = (bool) $coupon->is_active;
        $this->eligibleCourseIds = ! empty($coupon->eligible_course_ids) ? (array) $coupon->eligible_course_ids : [];
        $this->eligibleBundleIds = ! empty($coupon->eligible_bundle_ids) ? (array) $coupon->eligible_bundle_ids : [];
        $this->showModal = true;
    }

    public function saveCoupon(): void
    {
        $this->validate([
            'code' => 'required|string|max:50',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0.01',
            'currency' => 'required|string|max:3',
            'per_student_usage_limit' => 'required|integer|min:1',
        ]);

        $code = strtoupper(trim($this->code));
        $academyId = app(TenantContext::class)->getAcademy()?->id ?: Academy::first()?->id;

        // Check unique code per academy
        $existing = CommerceCoupon::where('code', $code)->where('academy_id', $academyId);
        if ($this->editingCouponId) {
            $existing->where('id', '!=', $this->editingCouponId);
        }
        if ($existing->exists()) {
            $this->addError('code', 'A coupon with this code already exists.');

            return;
        }

        $data = [
            'academy_id' => $academyId,
            'code' => $code,
            'description' => $this->description,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'max_discount_amount' => $this->max_discount_amount,
            'min_purchase_amount' => $this->min_purchase_amount,
            'currency' => strtoupper($this->currency),
            'starts_at' => $this->starts_at ? $this->starts_at : null,
            'expires_at' => $this->expires_at ? $this->expires_at : null,
            'global_usage_limit' => $this->global_usage_limit,
            'per_student_usage_limit' => $this->per_student_usage_limit,
            'first_purchase_only' => $this->first_purchase_only,
            'is_active' => $this->is_active,
            'eligible_course_ids' => ! empty($this->eligibleCourseIds) ? array_map('intval', $this->eligibleCourseIds) : null,
            'eligible_bundle_ids' => ! empty($this->eligibleBundleIds) ? array_map('intval', $this->eligibleBundleIds) : null,
        ];

        if ($this->editingCouponId) {
            $coupon = CommerceCoupon::findOrFail($this->editingCouponId);
            $coupon->update($data);
        } else {
            CommerceCoupon::create($data);
        }

        $this->showModal = false;
        session()->flash('success', 'Coupon saved successfully.');
    }

    public function toggleStatus(int $id): void
    {
        $coupon = CommerceCoupon::findOrFail($id);
        $coupon->is_active = ! $coupon->is_active;
        $coupon->save();
        session()->flash('success', "Coupon {$coupon->code} ".($coupon->is_active ? 'activated' : 'deactivated').'.');
    }

    public function deleteCoupon(int $id): void
    {
        $coupon = CommerceCoupon::findOrFail($id);
        $coupon->delete();
        session()->flash('success', 'Coupon deleted successfully.');
    }

    public function render(): View
    {
        $query = CommerceCoupon::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('code', 'like', '%'.$this->search.'%')
                    ->orWhere('description', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $coupons = $query->latest()->paginate(10);
        $courses = Course::published()->orderBy('title')->get();
        $bundles = CourseBundle::where('status', 'published')->orderBy('title')->get();

        return view('livewire.admin.commerce.coupon-manager', [
            'coupons' => $coupons,
            'courses' => $courses,
            'bundles' => $bundles,
        ]);
    }
}
