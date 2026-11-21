<?php

namespace App\Http\Controllers;

use App\Domain\Projects\Services\StudentPortfolioService;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PortfolioExportController extends Controller
{
    public function __construct(
        protected StudentPortfolioService $portfolioService
    ) {}

    /**
     * Export student's SEO portfolio as a structured PDF document.
     */
    public function exportPdf(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->is_active || ! $user->hasRole('student')) {
            abort(403, 'Unauthorized.');
        }

        $profile = $user->studentProfile;
        if (! $profile) {
            abort(404, 'Student profile not found.');
        }

        $portfolio = $this->portfolioService->getOrCreatePortfolio($profile);
        $portfolio->load([
            'visibleItems.submission.project',
            'visibleItems.submission.achievement',
            'visibleItems.submission.attachments',
        ]);

        $html = view('reports.portfolio-pdf-template', [
            'portfolio' => $portfolio,
            'student' => $user,
            'profile' => $profile,
            'exportedAt' => now()->format('Y-m-d H:i:s'),
        ])->render();

        $options = new Options;
        $options->set('defaultFont', 'Helvetica');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $fileName = sprintf('SEO_Portfolio_%s_%s.pdf', preg_replace('/[^a-zA-Z0-9_-]/', '_', $user->name), date('Ymd'));

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Cache-Control' => 'private, no-transform, no-store, must-revalidate',
        ]);
    }
}
