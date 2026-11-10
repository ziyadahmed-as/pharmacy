<?php

namespace App\Livewire\Shifts;

use App\Actions\Shifts\OpenShift;
use App\Exceptions\ShiftAlreadyOpenException;
use App\Exceptions\TillInactiveException;
use App\Exceptions\UserAlreadyHasOpenShiftException;
use App\Models\Till;
use App\Services\CurrentShift;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Open Shift')]
class Open extends Component
{
    public ?int $selectedTillId = null;

    public float|int|string $openingFloat = 0;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasPermission('shifts.open'), 403);

        $freeTill = Till::where('is_active', true)
            ->whereDoesntHave('activeShift')
            ->first();

        if ($freeTill) {
            $this->selectedTillId = $freeTill->id;
        }
    }

    public function selectTill(int $tillId): void
    {
        $this->selectedTillId = $tillId;
        $this->errorMessage = null;
    }

    public function openShift(OpenShift $action): void
    {
        $this->errorMessage = null;

        $this->validate([
            'selectedTillId' => ['required', 'integer', 'exists:tills,id'],
            'openingFloat' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $openingFloatSantim = (int) round(((float) $this->openingFloat) * 100);

            $action->execute(
                Auth::user(),
                $this->selectedTillId,
                $openingFloatSantim
            );

            session()->flash('status', __('shifts.shift_opened_successfully'));
            $this->redirect(route('dashboard'), navigate: true);
        } catch (ShiftAlreadyOpenException|UserAlreadyHasOpenShiftException|TillInactiveException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render(): View
    {
        $user = Auth::user();
        $currentShift = $user ? CurrentShift::forUser($user) : null;

        $tills = Till::with(['activeShift.user'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.shifts.open', [
            'currentShift' => $currentShift,
            'tills' => $tills,
        ]);
    }
}
