<?php

namespace App\Livewire\Admin\Commerce;

use App\Domain\Academies\Models\Academy;
use App\Domain\Commerce\Models\CommerceInstallmentPlan;
use App\Domain\Commerce\Models\CourseBundle;
use App\Domain\Commerce\Services\CurrencyAndPricingService;
use App\Domain\Courses\Models\Course;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Course Bundles Management')]
class CourseBundlesManager extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    public bool $showModal = false;

    public ?int $editingBundleId = null;

    // Form fields
    public string $title = '';

    public string $slug = '';

    public string $description = '';

    public float $price = 0.00;

    public string $currency = 'INR';

    public ?float $original_total_price = null;

    public ?float $discount_price = null;

    public ?float $discount_percentage = null;

    public ?string $discount_starts_at = null;

    public ?string $discount_expires_at = null;

    public string $status = 'published';

    public bool $is_coupon_eligible = true;

    public ?int $installment_plan_id = null;

    public string $ownership_policy = 'full_bundle_price'; // full_bundle_price, exclude_owned_courses

    public array $selectedCourseIds = [];

    public string $regional_prices_json = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTitle(): void
    {
        if (! $this->editingBundleId) {
            $this->slug = Str::slug($this->title);
        }
    }

    public function updatedSelectedCourseIds(): void
    {
        if (! empty($this->selectedCourseIds)) {
            $sum = Course::whereIn('id', $this->selectedCourseIds)->sum('price');
            $this->original_total_price = (float) $sum;
            if ($this->price <= 0.00) {
                // Default bundle price with 20% discount if not manually specified
                $this->price = round($sum * 0.8, 2);
            }
        }
    }

    public function openCreateModal(): void
    {
        $this->reset([
            'editingBundleId', 'title', 'slug', 'description', 'price',
            'original_total_price', 'discount_price', 'discount_percentage',
            'discount_starts_at', 'discount_expires_at', 'status',
            'is_coupon_eligible', 'installment_plan_id', 'selectedCourseIds',
            'regional_prices_json',
        ]);
        $this->currency = app(CurrencyAndPricingService::class)->getAcademyCurrency();
        $this->status = 'published';
        $this->ownership_policy = 'full_bundle_price';
        $this->is_coupon_eligible = true;
        $this->showModal = true;
    }

    public function editBundle(int $id): void
    {
        $bundle = CourseBundle::findOrFail($id);
        $this->editingBundleId = $bundle->id;
        $this->title = $bundle->title;
        $this->slug = $bundle->slug;
        $this->description = $bundle->description ?? '';
        $this->price = (float) $bundle->price;
        $this->currency = $bundle->currency ?: app(CurrencyAndPricingService::class)->getAcademyCurrency();
        $this->original_total_price = $bundle->original_total_price ? (float) $bundle->original_total_price : null;
        $this->discount_price = $bundle->discount_price ? (float) $bundle->discount_price : null;
        $this->discount_percentage = $bundle->discount_percentage ? (float) $bundle->discount_percentage : null;
        $this->discount_starts_at = $bundle->discount_starts_at ? $bundle->discount_starts_at->format('Y-m-d\TH:i') : null;
        $this->discount_expires_at = $bundle->discount_expires_at ? $bundle->discount_expires_at->format('Y-m-d\TH:i') : null;
        $this->status = $bundle->status;
        $this->is_coupon_eligible = (bool) $bundle->is_coupon_eligible;
        $this->installment_plan_id = $bundle->installment_plan_id;
        $this->ownership_policy = $bundle->ownership_policy ?: 'full_bundle_price';
        $this->selectedCourseIds = $bundle->courses()->pluck('courses.id')->map(fn ($id) => (int) $id)->toArray();
        $this->regional_prices_json = ! empty($bundle->regional_prices) ? json_encode($bundle->regional_prices, JSON_PRETTY_PRINT) : '';
        $this->showModal = true;
    }

    public function saveBundle(): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|max:3',
            'selectedCourseIds' => 'required|array|min:1',
            'ownership_policy' => 'required|in:full_bundle_price,exclude_owned_courses',
        ]);

        $regionalPrices = null;
        if (! empty(trim($this->regional_prices_json))) {
            $decoded = json_decode($this->regional_prices_json, true);
            if (is_array($decoded)) {
                $regionalPrices = $decoded;
            }
        }

        $academyId = app(TenantContext::class)->getAcademy()?->id ?: Academy::first()?->id;

        $data = [
            'academy_id' => $academyId,
            'title' => $this->title,
            'slug' => CourseBundle::generateUniqueSlug($this->slug, $this->editingBundleId, $academyId),
            'description' => $this->description,
            'price' => $this->price,
            'currency' => strtoupper($this->currency),
            'original_total_price' => $this->original_total_price,
            'discount_price' => $this->discount_price,
            'discount_percentage' => $this->discount_percentage,
            'discount_starts_at' => $this->discount_starts_at ? $this->discount_starts_at : null,
            'discount_expires_at' => $this->discount_expires_at ? $this->discount_expires_at : null,
            'regional_prices' => $regionalPrices,
            'status' => $this->status,
            'is_coupon_eligible' => $this->is_coupon_eligible,
            'installment_plan_id' => $this->installment_plan_id,
            'ownership_policy' => $this->ownership_policy,
        ];

        if ($this->editingBundleId) {
            $bundle = CourseBundle::findOrFail($this->editingBundleId);
            $bundle->update($data);
        } else {
            $bundle = CourseBundle::create($data);
        }

        // Sync courses
        $syncData = [];
        foreach ($this->selectedCourseIds as $idx => $courseId) {
            $syncData[$courseId] = ['display_order' => $idx + 1];
        }
        $bundle->courses()->sync($syncData);

        $this->showModal = false;
        session()->flash('success', 'Course bundle saved successfully.');
    }

    public function deleteBundle(int $id): void
    {
        $bundle = CourseBundle::findOrFail($id);
        $bundle->courses()->detach();
        $bundle->delete();
        session()->flash('success', 'Course bundle deleted successfully.');
    }

    public function render(): View
    {
        $query = CourseBundle::with(['courses', 'installmentPlan']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%'.$this->search.'%')
                    ->orWhere('description', 'like', '%'.$this->search.'%')
                    ->orWhere('slug', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $bundles = $query->latest()->paginate(10);
        $availableCourses = Course::published()->orderBy('title')->get();
        $availablePlans = CommerceInstallmentPlan::where('is_active', true)->get();

        return view('livewire.admin.commerce.course-bundles-manager', [
            'bundles' => $bundles,
            'availableCourses' => $availableCourses,
            'availablePlans' => $availablePlans,
        ]);
    }
}
