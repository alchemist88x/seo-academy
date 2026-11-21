<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Academies\Models\Academy;
use App\Domain\Certificates\Models\CertificateTemplate;
use App\Domain\Certificates\Services\CertificatePdfService;
use App\Domain\Certificates\Services\CertificateTemplateService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CertificatePreviewController extends Controller
{
    public function __construct(
        protected CertificatePdfService $pdfService,
        protected CertificateTemplateService $templateService
    ) {}

    /**
     * Preview sample PDF for a template in browser.
     */
    public function previewPdf(Request $request, CertificateTemplate $template): Response
    {
        $this->authorizeAdmin($request);

        $academy = $template->academy ?? Academy::current();
        $sampleData = $this->templateService->getSamplePreviewData($academy);
        $designData = $template->getActiveDesignData();

        $pdfBinary = $this->pdfService->renderDesignerPdf($designData, $sampleData, $academy);

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="certificate-preview-'.$template->slug.'.pdf"',
        ]);
    }

    /**
     * Download sample PDF for a template.
     */
    public function downloadSamplePdf(Request $request, CertificateTemplate $template): Response
    {
        $this->authorizeAdmin($request);

        $academy = $template->academy ?? Academy::current();
        $sampleData = $this->templateService->getSamplePreviewData($academy);
        $designData = $template->getActiveDesignData();

        $pdfBinary = $this->pdfService->renderDesignerPdf($designData, $sampleData, $academy);

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="certificate-sample-'.$template->slug.'.pdf"',
        ]);
    }

    /**
     * Stream preview for custom design JSON sent via POST.
     */
    public function previewCustomJson(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'design_data' => 'required|array',
            'design_data.canvas' => 'required|array',
            'design_data.elements' => 'present|array',
        ]);

        $academy = Academy::current();
        $sampleData = $this->templateService->getSamplePreviewData($academy);

        $pdfBinary = $this->pdfService->renderDesignerPdf($validated['design_data'], $sampleData, $academy);

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="certificate-draft-preview.pdf"',
        ]);
    }

    protected function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (! $user || ! $user->is_active || ($user->role !== 'admin' && ! $user->hasRole('admin'))) {
            abort(403, 'Unauthorized. Only administrators may preview certificate templates.');
        }
    }
}
