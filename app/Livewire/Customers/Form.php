<?php

namespace App\Livewire\Customers;

use App\Actions\Customers\SaveCustomer;
use App\Enums\Role;
use App\Exceptions\ConsentRequiredException;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Customer Form')]
class Form extends Component
{
    public ?Customer $customer = null;

    public string $name = '';

    public string $phone = '';

    public string $address = '';

    public string $birth_date = '';

    public bool $consent_given = false;

    public string $allergies = '';

    public string $chronic_conditions = '';

    public ?string $duplicateWarning = null;

    public ?string $errorMessage = null;

    public function mount(?Customer $customer = null): void
    {
        $user = Auth::user();
        abort_if(! $user || $user->hasRole(Role::SystemAdmin), 403);
        abort_unless($user->hasPermission('customers.manage'), 403);

        if ($customer && $customer->exists) {
            $this->customer = $customer;
            $this->name = $customer->name;
            $this->phone = $customer->phone;
            $this->address = (string) $customer->address;
            $this->birth_date = $customer->birth_date ? $customer->birth_date->format('Y-m-d') : '';
            $this->consent_given = (bool) $customer->consent_given;
            $this->allergies = (string) $customer->allergies;
            $this->chronic_conditions = (string) $customer->chronic_conditions;
        }
    }

    public function updatedPhone(): void
    {
        $cleanPhone = trim($this->phone);
        if ($cleanPhone === '') {
            $this->duplicateWarning = null;

            return;
        }

        $existing = Customer::where('phone', $cleanPhone)
            ->when($this->customer, fn ($q) => $q->where('id', '!=', $this->customer->id))
            ->first();

        if ($existing) {
            $this->duplicateWarning = __('customers.duplicate_phone_warning', ['name' => $existing->name]);
        } else {
            $this->duplicateWarning = null;
        }
    }

    public function updatedConsentGiven(): void
    {
        if (! $this->consent_given) {
            $this->allergies = '';
            $this->chronic_conditions = '';
        }
    }

    public function save(): void
    {
        $this->errorMessage = null;

        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        try {
            $customer = app(SaveCustomer::class)->execute(
                $user,
                [
                    'name' => $this->name,
                    'phone' => $this->phone,
                    'address' => $this->address ?: null,
                    'birth_date' => $this->birth_date ?: null,
                    'consent_given' => $this->consent_given,
                    'allergies' => $this->allergies ?: null,
                    'chronic_conditions' => $this->chronic_conditions ?: null,
                ],
                $this->customer
            );

            session()->flash('success', __('customers.customer_saved_successfully'));
            $this->redirect(route('customers.show', $customer), navigate: true);
        } catch (ConsentRequiredException|InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render(): View
    {
        return view('livewire.customers.form');
    }
}
