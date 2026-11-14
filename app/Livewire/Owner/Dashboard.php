<?php

namespace App\Livewire\Owner;

use App\Actions\Owner\SendDailySummary;
use App\Enums\Role;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Executive Owner Dashboard with lazy-loaded metric tiles and daily summary triggers (UC-32).
 *
 * @uc UC-32
 *
 * @fr FR-7.7, FR-9.2
 *
 * @nfr NFR-5
 */
#[Layout('layouts.app')]
#[Title('Owner Dashboard')]
class Dashboard extends Component
{
    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user, 403);
        abort_unless($user->hasRole(Role::Owner), 403);
    }

    public function sendSummary(SendDailySummary $action): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        try {
            $result = $action->execute(Auth::user(), 'sms');
            if ($result['success']) {
                $this->statusMessage = __('dashboard.owner.summary_sent_success', ['recipient' => $result['recipient']]);
            } else {
                $this->errorMessage = __('dashboard.owner.summary_sent_failed', ['error' => $result['error']]);
            }
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render(): View
    {
        return view('livewire.owner.dashboard');
    }
}
