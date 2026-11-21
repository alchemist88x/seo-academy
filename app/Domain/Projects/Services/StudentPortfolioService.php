<?php

namespace App\Domain\Projects\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Platform\Services\AuditLogService;
use App\Domain\Projects\Models\ProjectSubmission;
use App\Domain\Projects\Models\StudentPortfolio;
use App\Domain\Projects\Models\StudentPortfolioItem;
use App\Domain\Students\Models\StudentProfile;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StudentPortfolioService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Get or initialize a student portfolio record.
     */
    public function getOrCreatePortfolio(StudentProfile $studentProfile): StudentPortfolio
    {
        return StudentPortfolio::firstOrCreate(
            [
                'student_profile_id' => $studentProfile->id,
            ],
            [
                'id' => (string) Str::uuid(),
                'user_id' => $studentProfile->user_id,
                'title' => sprintf('%s - SEO Portfolio', $studentProfile->user?->name ?? 'Student'),
                'headline' => 'Certified SEO Specialist & Practical Campaign Portfolio',
                'bio' => 'Showcasing realistic SEO campaigns, technical audits, keyword research, and on-page optimization deliverables.',
                'custom_skills' => ['Technical SEO', 'Keyword Research', 'On-page SEO', 'Content Optimization', 'SEO Auditing'],
                'is_public' => false,
                'public_token' => null,
                'public_identity_consent' => false,
            ]
        );
    }

    /**
     * Update top-level portfolio metadata.
     */
    public function updatePortfolio(StudentPortfolio $portfolio, array $data): StudentPortfolio
    {
        $portfolio->update([
            'title' => ! empty($data['title']) ? trim($data['title']) : $portfolio->title,
            'headline' => array_key_exists('headline', $data) ? $data['headline'] : $portfolio->headline,
            'bio' => array_key_exists('bio', $data) ? $data['bio'] : $portfolio->bio,
            'custom_skills' => array_key_exists('custom_skills', $data) ? $data['custom_skills'] : $portfolio->custom_skills,
        ]);

        return $portfolio;
    }

    /**
     * Add an approved practical SEO project to student's portfolio.
     *
     * @param array{
     *     title?: ?string,
     *     case_study?: ?string,
     *     skills_demonstrated?: array<int, string>,
     *     is_featured?: bool,
     *     included_attachments?: array<int, string>
     * } $data
     */
    public function addProjectToPortfolio(StudentPortfolio $portfolio, ProjectSubmission $submission, array $data = []): StudentPortfolioItem
    {
        if ((string) $submission->user_id !== (string) $portfolio->user_id) {
            throw new DomainException('Cannot add another student’s submission to your portfolio.');
        }

        if (! $submission->isApproved()) {
            throw new DomainException('Only approved practical projects can be showcased in your portfolio.');
        }

        $existing = StudentPortfolioItem::query()
            ->where('portfolio_id', $portfolio->id)
            ->where('submission_id', $submission->id)
            ->first();

        if ($existing) {
            return $this->updatePortfolioItem($existing, $data);
        }

        $project = $submission->project;
        $maxOrder = StudentPortfolioItem::where('portfolio_id', $portfolio->id)->max('display_order') ?? 0;

        $skills = ! empty($data['skills_demonstrated'])
            ? $data['skills_demonstrated']
            : array_filter([$project?->categoryLabel(), $project?->difficultyLabel()]);

        return StudentPortfolioItem::create([
            'id' => (string) Str::uuid(),
            'portfolio_id' => $portfolio->id,
            'submission_id' => $submission->id,
            'title' => ! empty($data['title']) ? trim($data['title']) : ($project?->title ?? 'SEO Practical Project'),
            'case_study' => $data['case_study'] ?? ($submission->content ? Str::limit($submission->content, 300) : 'Demonstrated practical mastery of core SEO campaign deliverables.'),
            'skills_demonstrated' => $skills,
            'display_order' => $maxOrder + 1,
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'is_visible' => true,
            'included_attachments' => $data['included_attachments'] ?? $submission->attachments()->pluck('id')->all(),
        ]);
    }

    /**
     * Update an individual portfolio item.
     */
    public function updatePortfolioItem(StudentPortfolioItem $item, array $data): StudentPortfolioItem
    {
        $item->update([
            'title' => ! empty($data['title']) ? trim($data['title']) : $item->title,
            'case_study' => array_key_exists('case_study', $data) ? $data['case_study'] : $item->case_study,
            'skills_demonstrated' => array_key_exists('skills_demonstrated', $data) ? $data['skills_demonstrated'] : $item->skills_demonstrated,
            'is_featured' => array_key_exists('is_featured', $data) ? (bool) $data['is_featured'] : $item->is_featured,
            'is_visible' => array_key_exists('is_visible', $data) ? (bool) $data['is_visible'] : $item->is_visible,
            'included_attachments' => array_key_exists('included_attachments', $data) ? $data['included_attachments'] : $item->included_attachments,
        ]);

        return $item;
    }

    /**
     * Remove an item from the portfolio.
     */
    public function removePortfolioItem(StudentPortfolioItem $item): void
    {
        $item->delete();
    }

    /**
     * Reorder portfolio items.
     *
     * @param  array<int, string>  $orderedIds
     */
    public function reorderPortfolioItems(StudentPortfolio $portfolio, array $orderedIds): void
    {
        DB::transaction(function () use ($portfolio, $orderedIds) {
            foreach ($orderedIds as $index => $id) {
                StudentPortfolioItem::query()
                    ->where('portfolio_id', $portfolio->id)
                    ->where('id', $id)
                    ->update(['display_order' => $index + 1]);
            }
        });
    }

    /**
     * Enable public sharing with explicit student consent. Generates an opaque revocable token.
     */
    public function enablePublicSharing(StudentPortfolio $portfolio, bool $withConsent = true): string
    {
        if (! $withConsent) {
            throw new InvalidArgumentException('Explicit consent is required to enable public portfolio sharing.');
        }

        $now = Carbon::now();
        $token = bin2hex(random_bytes(32)); // 64-char cryptographically secure token

        $portfolio->update([
            'is_public' => true,
            'public_token' => $token,
            'public_token_generated_at' => $now,
            'public_identity_consent' => true,
            'consent_recorded_at' => $now,
        ]);

        $studentUser = $portfolio->user;
        $academy = Academy::current();
        if ($academy && $studentUser) {
            $this->auditLogService->record(
                $academy,
                $studentUser,
                'portfolio_public_sharing_enabled',
                StudentPortfolio::class,
                $portfolio->id,
                ['public_identity_consent' => true]
            );
        }

        return $token;
    }

    /**
     * Revoke public portfolio sharing immediately.
     */
    public function revokePublicSharing(StudentPortfolio $portfolio): void
    {
        $portfolio->revokePublicToken();

        $studentUser = $portfolio->user;
        $academy = Academy::current();
        if ($academy && $studentUser) {
            $this->auditLogService->record(
                $academy,
                $studentUser,
                'portfolio_public_sharing_revoked',
                StudentPortfolio::class,
                $portfolio->id,
                ['is_public' => false]
            );
        }
    }

    /**
     * Safely resolve public portfolio by opaque token. Returns null if not public or invalid.
     */
    public function resolvePublicPortfolio(string $token): ?StudentPortfolio
    {
        if (empty($token) || strlen($token) !== 64) {
            return null;
        }

        return StudentPortfolio::query()
            ->where('is_public', true)
            ->where('public_token', $token)
            ->with([
                'user:id,name',
                'visibleItems.submission.project',
                'visibleItems.submission.attachments',
                'visibleItems.submission.achievement',
            ])
            ->first();
    }
}
