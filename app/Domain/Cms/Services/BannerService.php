<?php

namespace App\Domain\Cms\Services;

use App\Domain\Cms\Models\CmsBanner;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class BannerService
{
    /**
     * Get active, scheduled banners for a placement in display order.
     *
     * @return Collection<int, CmsBanner>
     */
    public function getActiveBanners(string $academyId, string $placement): Collection
    {
        return CmsBanner::where('academy_id', $academyId)
            ->active()
            ->placement($placement)
            ->current()
            ->orderBy('display_order', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Validate that a CTA URL is safe against XSS schemes.
     */
    public function isSafeUrl(?string $url): bool
    {
        if (empty($url)) {
            return true;
        }

        $url = trim($url);

        // Disallow dangerous URI schemes
        $disallowed = ['javascript:', 'data:', 'vbscript:', 'file:'];
        $lower = strtolower($url);

        foreach ($disallowed as $scheme) {
            if (str_starts_with($lower, $scheme)) {
                return false;
            }
        }

        // Must start with /, #, http://, or https://
        return str_starts_with($url, '/')
            || str_starts_with($url, '#')
            || str_starts_with($url, 'http://')
            || str_starts_with($url, 'https://');
    }

    /**
     * Assert safe URL or throw InvalidArgumentException.
     */
    public function assertSafeUrl(?string $url, string $fieldName = 'CTA URL'): void
    {
        if (! $this->isSafeUrl($url)) {
            throw new InvalidArgumentException("The {$fieldName} contains an unsafe or invalid URL protocol.");
        }
    }

    /**
     * Create a new banner with safe validation.
     *
     * @param  array<string, mixed>  $data
     */
    public function createBanner(string $academyId, array $data): CmsBanner
    {
        if (isset($data['primary_cta_url'])) {
            $this->assertSafeUrl($data['primary_cta_url'], 'Primary CTA URL');
        }

        if (isset($data['secondary_cta_url'])) {
            $this->assertSafeUrl($data['secondary_cta_url'], 'Secondary CTA URL');
        }

        $data['academy_id'] = $academyId;

        return CmsBanner::create($data);
    }

    /**
     * Update an existing banner.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateBanner(CmsBanner $banner, array $data): CmsBanner
    {
        if (isset($data['primary_cta_url'])) {
            $this->assertSafeUrl($data['primary_cta_url'], 'Primary CTA URL');
        }

        if (isset($data['secondary_cta_url'])) {
            $this->assertSafeUrl($data['secondary_cta_url'], 'Secondary CTA URL');
        }

        $banner->update($data);

        return $banner;
    }

    /**
     * Duplicate a banner.
     */
    public function duplicateBanner(CmsBanner $banner): CmsBanner
    {
        $replica = $banner->replicate();
        $replica->name = $banner->name.' (Copy)';
        $replica->is_active = false; // default to inactive copy
        $replica->save();

        return $replica;
    }

    /**
     * Reorder banners within an academy and placement.
     *
     * @param  array<int, string>  $bannerIds  Ordered array of banner UUIDs
     */
    public function reorder(string $academyId, array $bannerIds): void
    {
        foreach ($bannerIds as $order => $id) {
            CmsBanner::where('academy_id', $academyId)
                ->where('id', $id)
                ->update(['display_order' => $order]);
        }
    }
}
