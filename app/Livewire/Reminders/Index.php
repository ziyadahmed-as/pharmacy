<?php

namespace App\Livewire\Reminders;

use App\Actions\Reminders\BuildRefillReminders;
use App\Actions\Reminders\SendRefillReminder;
use App\Enums\Role as RoleEnum;
use App\Models\Reminder;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Staff review of due refill reminders, bulk sending, and failed retry queue.
 *
 * @uc UC-27
 *
 * @fr FR-5.5
 */
#[Layout('layouts.app')]
#[Title('Refill Reminders')]
class Index extends Component
{
    use WithPagination;

    public string $activeTab = 'due'; // 'due', 'failed', 'sent'

    /** @var array<int, int> */
    public array $selectedReminders = [];

    public bool $selectAll = false;

    public string $search = '';

    public int $lookaheadDays = 3;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user, 403);
        abort_if($user->hasRole(RoleEnum::SystemAdmin), 403);
        abort_unless($user->hasAnyRole([RoleEnum::PharmacyStaff, RoleEnum::PharmacyManager, RoleEnum::Owner]), 403);
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['due', 'failed', 'sent'], true) ? $tab : 'due';
        $this->selectedReminders = [];
        $this->selectAll = false;
        $this->resetPage();
    }

    public function updatedSelectAll(bool $value): void
    {
        if ($value && $this->activeTab === 'due') {
            $ids = Reminder::queued()
                ->due(now()->copy()->addDays($this->lookaheadDays))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->toArray();
            $this->selectedReminders = $ids;
        } else {
            $this->selectedReminders = [];
        }
    }

    public function checkDue(BuildRefillReminders $action): void
    {
        $created = $action->execute(lookaheadDays: $this->lookaheadDays);
        $count = $created->count();

        $this->statusMessage = __('reminders.checked_due_success', ['count' => $count]);
        $this->resetPage();
    }

    public function sendSingle(int $reminderId, SendRefillReminder $action): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        $reminder = Reminder::with('customer')->findOrFail($reminderId);
        $updated = $action->execute(Auth::user(), $reminder);

        if ($updated->isSent()) {
            $this->statusMessage = __('reminders.sent_success', ['name' => $reminder->customer->name]);
        } else {
            $this->errorMessage = __('reminders.sent_failed', [
                'name' => $reminder->customer->name,
                'error' => $updated->error_message,
            ]);
        }
    }

    public function sendSelected(SendRefillReminder $action): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        if (empty($this->selectedReminders)) {
            $this->errorMessage = __('reminders.no_reminders_selected');

            return;
        }

        $reminders = Reminder::with('customer')->whereIn('id', $this->selectedReminders)->get();
        $successCount = 0;
        $failedCount = 0;

        foreach ($reminders as $reminder) {
            $updated = $action->execute(Auth::user(), $reminder);
            if ($updated->isSent()) {
                $successCount++;
            } else {
                $failedCount++;
            }
        }

        $this->selectedReminders = [];
        $this->selectAll = false;

        $this->statusMessage = __('reminders.batch_sent_summary', [
            'success' => $successCount,
            'failed' => $failedCount,
        ]);
    }

    public function markAsCalled(int $reminderId): void
    {
        $reminder = Reminder::with('customer')->findOrFail($reminderId);
        $reminder->update([
            'status' => Reminder::STATUS_CALLED,
            'sent_at' => now(),
            'sent_by' => Auth::id(),
            'error_message' => null,
        ]);

        AuditLogger::record(
            action: 'reminder.called',
            subject: $reminder,
            old: ['status' => Reminder::STATUS_FAILED],
            new: ['status' => Reminder::STATUS_CALLED],
            metadata: [
                'customer_id' => $reminder->customer_id,
                'phone' => $reminder->customer->phone,
            ],
            user: Auth::user(),
            description: "Refill reminder customer {$reminder->customer->name} contacted via phone call"
        );

        $this->statusMessage = __('reminders.marked_as_called_success', ['name' => $reminder->customer->name]);
    }

    public function render()
    {
        $query = Reminder::with(['customer', 'prescriptionLine.prescription'])
            ->when($this->search, function ($q) {
                $q->whereHas('customer', function ($cq) {
                    $cq->where('name', 'like', "%{$this->search}%")
                        ->orWhere('phone', 'like', "%{$this->search}%");
                });
            });

        if ($this->activeTab === 'due') {
            $query->queued()->due(now()->copy()->addDays($this->lookaheadDays))->orderBy('due_date', 'asc');
        } elseif ($this->activeTab === 'failed') {
            $query->failed()->orderBy('updated_at', 'desc');
        } else {
            $query->whereIn('status', [Reminder::STATUS_SENT, Reminder::STATUS_CALLED])->orderBy('sent_at', 'desc');
        }

        $reminders = $query->paginate(20);

        $counts = [
            'due' => Reminder::queued()->due(now()->copy()->addDays($this->lookaheadDays))->count(),
            'failed' => Reminder::failed()->count(),
            'sent' => Reminder::whereIn('status', [Reminder::STATUS_SENT, Reminder::STATUS_CALLED])->count(),
        ];

        return view('livewire.reminders.index', [
            'reminders' => $reminders,
            'counts' => $counts,
        ]);
    }
}
