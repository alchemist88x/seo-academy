<?php

namespace App\Services\Pdf;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Lessons\Models\Lesson;
use App\Services\Pdf\Exceptions\OversizedLessonContentException;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LessonMaterialPdfService
{
    public const MAX_PAGES = 1;

    /**
     * Render the lesson material as a strictly one-page PDF binary.
     *
     * @throws OversizedLessonContentException if content exceeds the one-page limit and $enforceSinglePage is true.
     */
    public function renderPdf(
        Lesson $lesson,
        ?Course $course = null,
        ?Academy $academy = null,
        bool $enforceSinglePage = true
    ): string {
        $course ??= $lesson->module?->course;
        $academy ??= $course?->academy ?? Academy::current();

        $html = $this->renderHtml($lesson, $course, $academy);

        $options = new Options;
        $options->set('defaultFont', 'Helvetica');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('dpi', 96);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $pageCount = (int) $canvas->get_page_count();

        if ($enforceSinglePage && $pageCount > self::MAX_PAGES) {
            throw new OversizedLessonContentException(
                pageCount: $pageCount,
                lesson: $lesson,
                maxPages: self::MAX_PAGES
            );
        }

        return $dompdf->output();
    }

    /**
     * Determine whether the given lesson content fits on exactly one A4 portrait page.
     */
    public function fitsOnSinglePage(Lesson $lesson, ?Course $course = null, ?Academy $academy = null): bool
    {
        return $this->getPageCount($lesson, $course, $academy) === self::MAX_PAGES;
    }

    /**
     * Get the exact page count when rendering the lesson material.
     */
    public function getPageCount(Lesson $lesson, ?Course $course = null, ?Academy $academy = null): int
    {
        $course ??= $lesson->module?->course;
        $academy ??= $course?->academy ?? Academy::current();

        $html = $this->renderHtml($lesson, $course, $academy);

        $options = new Options;
        $options->set('defaultFont', 'Helvetica');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('dpi', 96);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (int) $dompdf->getCanvas()->get_page_count();
    }

    /**
     * Render the Blade view HTML for the lesson material template.
     */
    public function renderHtml(Lesson $lesson, ?Course $course = null, ?Academy $academy = null): string
    {
        $course ??= $lesson->module?->course;
        $academy ??= $course?->academy ?? Academy::current();

        $branding = $this->resolveBranding($academy);
        $sanitizedContent = $this->sanitizeAndFormatContent($lesson->content);
        $sanitizedDescription = $this->sanitizePlainText($lesson->description);

        return view('pdf.lesson-material', [
            'lesson' => $lesson,
            'course' => $course,
            'module' => $lesson->module,
            'academy' => $academy,
            'branding' => $branding,
            'formattedContent' => $sanitizedContent,
            'formattedDescription' => $sanitizedDescription,
            'durationFormatted' => $this->formatDuration($lesson->duration_seconds),
            'generatedAt' => now()->format('M d, Y'),
        ])->render();
    }

    /**
     * Generate a safe, consistent PDF filename for download.
     */
    public function generateFileName(Course $course, Lesson $lesson): string
    {
        $courseSlug = Str::slug($course->title ?: 'Course');
        $lessonSlug = Str::slug($lesson->title ?: 'Lesson');

        return sprintf('SEO-Academy-%s-%s-Notes.pdf', $courseSlug, $lessonSlug);
    }

    /**
     * Format duration seconds into a human-readable string (e.g., "12 mins", "45 secs").
     */
    public function formatDuration(?int $seconds): ?string
    {
        if ($seconds === null || $seconds <= 0) {
            return null;
        }

        if ($seconds < 60) {
            return "{$seconds} secs";
        }

        $minutes = round($seconds / 60);

        return "{$minutes} mins";
    }

    /**
     * Resolve dynamic branding with fallbacks for PDF template rendering.
     *
     * @return array<string, mixed>
     */
    public function resolveBranding(?Academy $academy): array
    {
        $name = $academy?->name ? trim($academy->name) : 'SEO Academy';
        $tagline = $academy?->setting('tagline') ? trim((string) $academy->setting('tagline')) : 'Digital Marketing';
        $primaryColor = $academy?->primary_color ? trim($academy->primary_color) : '#4f46e5';
        $secondaryColor = $academy?->secondary_color ? trim($academy->secondary_color) : '#06b6d4';
        $websiteUrl = $academy?->website_url ? trim($academy->website_url) : config('app.url', 'https://seoacademy.local');

        // Resolve logo as inline base64 if local file exists
        $logoDataUri = null;
        if ($academy?->logo_path) {
            $logoDataUri = $this->resolveLogoDataUri($academy->logo_path);
        }

        return [
            'name' => $name,
            'tagline' => $tagline,
            'primary_color' => $primaryColor,
            'secondary_color' => $secondaryColor,
            'website_url' => $websiteUrl,
            'logo_data_uri' => $logoDataUri,
        ];
    }

    /**
     * Convert Markdown content into safe, formatted HTML.
     */
    public function sanitizeAndFormatContent(?string $content): string
    {
        if ($content === null || trim($content) === '') {
            return '';
        }

        // Convert Markdown to HTML with strict options
        $html = Str::markdown($content, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return $this->sanitizeHtml($html);
    }

    /**
     * Sanitize HTML to prevent XSS, scripts, and unsafe injections.
     */
    public function sanitizeHtml(string $html): string
    {
        // Remove script tags and contents
        $html = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);

        // Remove style tags and contents
        $html = preg_replace('#<style(.*?)>(.*?)</style>#is', '', $html);

        // Remove dangerous embedding tags
        $html = preg_replace('#<(iframe|object|embed|applet|form|input|button|meta|link|base)[^>]*>#is', '', $html);
        $html = preg_replace('#</(iframe|object|embed|applet|form|input|button|meta|link|base)>#is', '', $html);

        // Remove on* event handlers (e.g. onerror=, onclick=, onload=)
        $html = preg_replace('#\s+on[a-zA-Z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);

        // Remove javascript: and data:text/html links
        $html = preg_replace('#href\s*=\s*["\'](javascript|data:text/html)[^"\']*["\']#i', 'href="#"', $html);
        $html = preg_replace('#src\s*=\s*["\'](javascript|data:text/html)[^"\']*["\']#i', '', $html);

        return trim($html);
    }

    /**
     * Sanitize plain text string.
     */
    public function sanitizePlainText(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        return e(trim($text));
    }

    /**
     * Convert an academy logo image file into a base64 Data URI for Dompdf.
     */
    protected function resolveLogoDataUri(string $path): ?string
    {
        $filePath = null;

        if (Storage::disk('public')->exists($path)) {
            $filePath = Storage::disk('public')->path($path);
        } elseif (file_exists(public_path($path))) {
            $filePath = public_path($path);
        } elseif (file_exists($path)) {
            $filePath = $path;
        }

        if ($filePath && file_exists($filePath) && is_readable($filePath)) {
            $mimeType = mime_content_type($filePath) ?: 'image/png';
            $data = base64_encode((string) file_get_contents($filePath));

            return "data:{$mimeType};base64,{$data}";
        }

        return null;
    }
}
