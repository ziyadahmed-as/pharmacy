<?php

namespace App\Livewire\Credits;

use App\Actions\Credits\RecordCreditPayment;
use App\Enums\Role;
use App\Models\CreditAccount;
use App\Models\CreditPayment;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Single credit account view with statement and collection form.
 *
 * @uc UC-26
 */
class Show extends Component
{
    #[Locked]
    public int $accountId;

    public CreditAccount $account;

    public string $paymentAmount = '';

    public string $paymentMethod = 'cash';

    public string $paymentReference = '';

    public string $paymentNotes = '';

    public string $successMessage = '';

    public string $errorMessage = '';

    public function mount(CreditAccount $account): void
    {
        abort_unless(
            auth()->user()?->hasAnyRole([Role::PharmacyManager, Role::Owner]),
            403
        );
        $this->accountId = $account->id;
        $this->account = $account->load('customer');
    }

    public function recordPayment(RecordCreditPayment $action): void
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        $this->validate([
            'paymentAmount' => 'required|numeric|min:0.01',
            'paymentMethod' => 'required|string|in:cash,mobile_money,telebirr,cbe_birr,card,bank_transfer',
        ]);

        try {
            $amountSantim = (int) round((float) $this->paymentAmount * 100);

            $action->execute(
                actor: Auth::user(),
                account: $this->account,
                amountSantim: $amountSantim,
                method: $this->paymentMethod,
                reference: $this->paymentReference ?: null,
                notes: $this->paymentNotes ?: null
            );

            $this->account = CreditAccount::with('customer')->findOrFail($this->accountId);
            $this->paymentAmount = '';
            $this->paymentReference = '';
            $this->paymentNotes = '';
            $this->successMessage = __('credits.payment_recorded_successfully');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        $transactions = CreditPayment::where('credit_account_id', $this->accountId)
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return view('livewire.credits.show', [
            'transactions' => $transactions,
        ])->layout('layouts.app');
    }
}
