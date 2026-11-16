<?php

namespace App\Livewire\Admin;

use App\Domain\Platform\Models\BackupRecord;
use App\Domain\Platform\Services\BackupService;
use App\Domain\Platform\Services\FileManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

#[Layout('layouts.app')]
#[Title('Backups & Recovery')]
class BackupsIndex extends Component
{
    use WithPagination;

    public ?string $restoreCandidateId = null;

    public string $restoreConfirmationText = '';

    public ?string $operationStatus = null;

    public ?string $operationMessage = null;

    public function createDatabaseBackup(BackupService $backupService): void
    {
        $this->ensureAdmin();

        try {
            $record = $backupService->createDatabaseBackup();
            session()->flash('success', "Database backup [{$record->filename}] created and verified successfully.");
        } catch (Throwable $e) {
            session()->flash('error', "Database backup failed: {$e->getMessage()}");
        }
    }

    public function createFilesBackup(BackupService $backupService): void
    {
        $this->ensureAdmin();

        try {
            $record = $backupService->createFilesBackup();
            session()->flash('success', "Files backup [{$record->filename}] created and verified successfully.");
        } catch (Throwable $e) {
            session()->flash('error', "Files backup failed: {$e->getMessage()}");
        }
    }

    public function createFullBackupSet(BackupService $backupService): void
    {
        $this->ensureAdmin();

        try {
            $result = $backupService->createFullBackupSet();
            session()->flash('success', "Full backup set [{$result['backup_set_id']}] created: Database ({$result['database']->filename}) and Private Media ({$result['files']->filename}).");
        } catch (Throwable $e) {
            session()->flash('error', "Full backup set creation failed: {$e->getMessage()}");
        }
    }

    public function applyRetention(BackupService $backupService): void
    {
        $this->ensureAdmin();

        try {
            $deleted = $backupService->applyRetention(5);
            session()->flash('success', "Retention cleanup completed. {$deleted} expired backup(s) pruned. (Guaranteed at least 1 known-good backup remains).");
        } catch (Throwable $e) {
            session()->flash('error', "Retention cleanup failed: {$e->getMessage()}");
        }
    }

    public function validateBackup(string $backupId, BackupService $backupService): void
    {
        $this->ensureAdmin();

        $record = BackupRecord::find($backupId);
        if (! $record) {
            return;
        }

        $isValid = $backupService->validateBackup($record);
        if ($isValid) {
            session()->flash('success', "Backup [{$record->filename}] passed integrity and checksum verification.");
        } else {
            session()->flash('error', "Backup [{$record->filename}] failed integrity check!");
        }
    }

    public function confirmRestore(string $backupId): void
    {
        $this->ensureAdmin();
        $this->restoreCandidateId = $backupId;
        $this->restoreConfirmationText = '';
        $this->operationStatus = null;
        $this->operationMessage = null;
    }

    public function cancelRestore(): void
    {
        $this->restoreCandidateId = null;
        $this->restoreConfirmationText = '';
    }

    public function executeRestore(BackupService $backupService): void
    {
        $this->ensureAdmin();

        if (strtoupper(trim($this->restoreConfirmationText)) !== 'RESTORE') {
            session()->flash('error', 'Please type RESTORE exactly to confirm the restoration procedure.');

            return;
        }

        $record = BackupRecord::find($this->restoreCandidateId);
        if (! $record) {
            session()->flash('error', 'Selected backup record no longer exists.');

            return;
        }

        try {
            $result = $backupService->restoreBackup($record, createSafetyBackup: true);
            $this->restoreCandidateId = null;
            $this->restoreConfirmationText = '';
            session()->flash('success', $result['message'].' (A pre-restore safety snapshot was created automatically).');
        } catch (Throwable $e) {
            session()->flash('error', "Restoration aborted: {$e->getMessage()}");
        }
    }

    public function restoreBackupSet(string $backupSetId, BackupService $backupService): void
    {
        $this->ensureAdmin();

        try {
            $result = $backupService->restoreBackupSet($backupSetId);
            session()->flash('success', $result['message'].' (Pre-restore safety backups were created for both database and files).');
        } catch (Throwable $e) {
            session()->flash('error', "Backup set restoration aborted: {$e->getMessage()}");
        }
    }

    public function deleteBackup(string $backupId, BackupService $backupService): void
    {
        $this->ensureAdmin();

        $record = BackupRecord::find($backupId);
        if ($record) {
            $backupService->deleteBackupRecordFile($record);
            $record->delete();
            session()->flash('success', "Backup [{$record->filename}] removed.");
        }
    }

    private function ensureAdmin(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasRole('admin')) {
            abort(403, 'Unauthorized.');
        }
    }

    public function render(FileManagementService $fileService): View
    {
        $backups = BackupRecord::query()
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $restoreCandidate = $this->restoreCandidateId
            ? BackupRecord::find($this->restoreCandidateId)
            : null;

        return view('livewire.admin.backups-index', [
            'backups' => $backups,
            'restoreCandidate' => $restoreCandidate,
            'fileService' => $fileService,
        ]);
    }
}
