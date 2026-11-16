<?php

namespace App\Console\Commands;

use App\Domain\Certificates\Models\Certificate;
use App\Domain\Certificates\Services\CertificatePdfService;
use Illuminate\Console\Command;

class ProcessCertificatePdfsCommand extends Command
{
    protected $signature = 'certificates:process-pdfs {--retry : Retry previously failed generations} {--limit=20 : Maximum certificates to process in this run}';

    protected $description = 'Process pending (or failed) certificate PDF generations for shared-hosting cron execution';

    public function handle(CertificatePdfService $pdfService): int
    {
        $query = Certificate::query();

        if ($this->option('retry')) {
            $query->whereIn('pdf_generation_status', ['pending', 'failed']);
        } else {
            $query->where('pdf_generation_status', 'pending');
        }

        $certificates = $query->limit((int) $this->option('limit'))->get();

        if ($certificates->isEmpty()) {
            $this->info('No pending certificate PDFs to process.');

            return self::SUCCESS;
        }

        $this->info("Processing {$certificates->count()} certificate PDF(s)...");

        $successCount = 0;
        $failCount = 0;

        foreach ($certificates as $cert) {
            try {
                $cert->update(['pdf_generation_status' => 'generating']);
                $pdfService->generatePdf($cert, true);
                $this->line("  [OK] Certificate {$cert->certificate_number} processed.");
                $successCount++;
            } catch (\Throwable $e) {
                $cert->update([
                    'pdf_generation_status' => 'failed',
                    'pdf_generation_error' => $e->getMessage(),
                ]);
                $this->error("  [FAIL] Certificate {$cert->certificate_number}: {$e->getMessage()}");
                $failCount++;
            }
        }

        $this->info("Done: {$successCount} succeeded, {$failCount} failed.");

        return self::SUCCESS;
    }
}
