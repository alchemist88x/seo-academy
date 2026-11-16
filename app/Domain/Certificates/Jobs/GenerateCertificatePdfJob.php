<?php

namespace App\Domain\Certificates\Jobs;

use App\Domain\Certificates\Models\Certificate;
use App\Domain\Certificates\Services\CertificatePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateCertificatePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly string $certificateId,
        public readonly bool $force = false,
    ) {}

    public function handle(CertificatePdfService $pdfService): void
    {
        $certificate = Certificate::find($this->certificateId);

        if (! $certificate) {
            return;
        }

        if ($certificate->isRevoked() || $certificate->isSuperseded()) {
            return;
        }

        try {
            $certificate->update([
                'pdf_generation_status' => 'generating',
                'pdf_generation_error' => null,
            ]);

            $filename = $pdfService->generatePdf($certificate, $this->force);

            $certificate->update([
                'pdf_path' => $filename,
                'pdf_generation_status' => 'completed',
                'pdf_generated_at' => now(),
                'pdf_generation_error' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Certificate PDF generation failed', [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage(),
            ]);

            $certificate->update([
                'pdf_generation_status' => 'failed',
                'pdf_generation_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
