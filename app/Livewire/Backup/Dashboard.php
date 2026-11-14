<?php

namespace App\Livewire\Backup;

use App\Actions\Backup\RequestRestore;
use App\Models\BackupRun;
use App\Models\RestoreRequest;
use App\Services\BackupService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Backup & Restore Monitoring')]
class Dashboard extends Component
{
    use WithPagination;

    public bool $showRestoreModal = false;

    public string $selectedFilename = '';

    public string $restoreReason = '';

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    public function mount(): void
    {
        $user = Auth::user();
        abort_if(! $user || ! $user->isSystemAdmin(), 403);
    }

    public function runBackupNow(BackupService $service): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        try {
            $run = $service->createBackup(BackupRun::TYPE_FULL);
            $this->successMessage = __('backup.backup_created_success', ['file' => $run->filename]);
        } catch (\Throwable $e) {
            $this->errorMessage = __('backup.backup_failed_msg', ['error' => $e->getMessage()]);
        }
    }

    public function openRestoreModal(string $filename): void
    {
        $this->selectedFilename = $filename;
        $this->restoreReason = '';
        $this->errorMessage = null;
        $this->showRestoreModal = true;
    }

    public function closeRestoreModal(): void
    {
        $this->showRestoreModal = false;
        $this->selectedFilename = '';
        $this->restoreReason = '';
        $this->errorMessage = null;
    }

    public function submitRestoreRequest(RequestRestore $action): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $this->errorMessage = null;

        try {
            $restore = $action->execute($user, [
                'backup_filename' => $this->selectedFilename,
                'reason' => $this->restoreReason,
            ]);

            $this->showRestoreModal = false;
            $this->successMessage = __('backup.restore_requested_success', ['id' => $restore->id]);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render(BackupService $service): View
    {
        $lastSuccess = BackupRun::successful()->latest('completed_at')->first();
        $lastFailure = BackupRun::failed()->latest('started_at')->first();

        $backups = BackupRun::latest('started_at')->paginate(10);
        $recentRestores = RestoreRequest::with(['requestedBy', 'approvedBy'])->latest()->take(5)->get();

        return view('livewire.backup.dashboard', [
            'lastSuccess' => $lastSuccess,
            'lastFailure' => $lastFailure,
            'backups' => $backups,
            'recentRestores' => $recentRestores,
            'isStale' => $service->checkStaleness(),
        ]);
    }
}
