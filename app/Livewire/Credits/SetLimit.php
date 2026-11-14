<?php

namespace App\Livewire\Credits;

use App\Actions\Credits\SetCreditLimit;
use App\Enums\Role;
use App\Models\CreditAccount;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Set or edit credit limit for a customer.
 *
 * @uc UC-26
 */
class SetLimit extends Component
{
    #[Locked]
    public int $customerId;

    public Customer $customer;

    public string $limitEtb = '';

    public string $notes = '';

    public string $successMessage = '';

    public string $errorMessage = '';

    public function mount(Customer $customer): void
    {
        abort_unless(
            auth()->user()?->hasAnyRole([Role::PharmacyManager, Role::Owner]),
            403
        );
        $this->customerId = $customer->id;
        $this->customer = $customer;

        $existing = CreditAccount::where('customer_id', $customer->id)->first();
        if ($existing) {
            $this->limitEtb = number_format($existing->credit_limit / 100, 2, '.', '');
            $this->notes = $existing->notes ?? '';
        }
    }

    public function saveLimit(SetCreditLimit $action): void
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        $this->validate([
            'limitEtb' => 'required|numeric|min:0.01',
        ]);

        try {
            $limitSantim = (int) round((float) $this->limitEtb * 100);

            $action->execute(
                actor: Auth::user(),
                customer: $this->customer,
                limitSantim: $limitSantim,
                notes: $this->notes ?: null
            );

            $this->successMessage = __('credits.limit_saved_successfully');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        $account = CreditAccount::where('customer_id', $this->customerId)->first();

        return view('livewire.credits.set-limit', [
            'account' => $account,
        ])->layout('layouts.app');
    }
}
