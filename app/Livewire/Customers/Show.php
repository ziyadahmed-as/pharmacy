<?php

namespace App\Livewire\Customers;

use App\Actions\Customers\RecordConsent;
use App\Actions\Customers\ViewHealthFields;
use App\Enums\Role;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Customer Details')]
class Show extends Component
{
    public Customer $customer;

    public string $activeTab = 'profile';

    public bool $healthDataRevealed = false;

    /** @var array{allergies: ?string, chronic_conditions: ?string, consent_given: bool, consent_at: ?string}|null */
    public ?array $healthData = null;

    public function mount(Customer $customer): void
    {
        $user = Auth::user();
        abort_if(! $user || $user->hasRole(Role::SystemAdmin), 403);
        abort_unless($user->hasPermission('customers.view'), 403);

        $this->customer = $customer;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;

        // If switching to health tab and user has permission, reveal health fields and log access
        if ($tab === 'health' && $this->canViewHealth() && ! $this->healthDataRevealed) {
            $this->revealHealthData();
        }
    }

    public function canViewHealth(): bool
    {
        $user = Auth::user();

        return $user && ! $user->hasRole(Role::SystemAdmin) && $user->hasPermission('customers.health.view');
    }

    public function revealHealthData(): void
    {
        $user = Auth::user();
        abort_unless($this->canViewHealth(), 403);

        $this->healthData = app(ViewHealthFields::class)->execute(
            $user,
            $this->customer,
            request()->ip()
        );
        $this->healthDataRevealed = true;
    }

    public function toggleConsent(): void
    {
        $user = Auth::user();
        abort_unless($user && $user->hasPermission('customers.manage'), 403);

        $newConsent = ! $this->customer->consent_given;
        $this->customer = app(RecordConsent::class)->execute($user, $this->customer, $newConsent);

        if ($this->healthDataRevealed) {
            $this->healthData = [
                'allergies' => $this->customer->allergies,
                'chronic_conditions' => $this->customer->chronic_conditions,
                'consent_given' => $this->customer->consent_given,
                'consent_at' => $this->customer->consent_at?->toIso8601String(),
            ];
        }

        session()->flash('success', __('customers.consent_updated_successfully'));
    }

    public function render(): View
    {
        $sales = $this->customer->sales()
            ->with(['lines.product', 'lines.batch'])
            ->latest('completed_at')
            ->get();

        $healthViews = $this->customer->healthViews()
            ->with('user')
            ->latest('viewed_at')
            ->take(20)
            ->get();

        return view('livewire.customers.show', [
            'sales' => $sales,
            'healthViews' => $healthViews,
            'canManage' => Auth::user()?->hasPermission('customers.manage') ?? false,
            'canViewHealth' => $this->canViewHealth(),
        ]);
    }
}

