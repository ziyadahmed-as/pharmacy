<?php

namespace App\Livewire\Finance;

use App\Enums\Role;
use App\Queries\CashSummaryQuery;
use App\Queries\RevenueSummaryQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Cash & Revenue Summaries')]
class CashRevenue extends Component
{
    #[Url]
    public string $mode = 'day'; // 'day' | 'period'

    #[Url]
    public string $selectedDate = '';

    #[Url]
    public string $startDate = '';

    #[Url]
    public string $endDate = '';

    public function mount(): void
    {
        $user = Auth::user();
        abort_if(! $user || $user->hasRole(Role::SystemAdmin), 403);
        abort_unless($user->hasRole(Role::Owner) || $user->hasRole(Role::PharmacyManager), 403);

        $today = now()->toDateString();
        if ($this->selectedDate === '') {
            $this->selectedDate = $today;
        }

        if ($this->startDate === '') {
            $this->startDate = now()->startOfMonth()->toDateString();
        }

        if ($this->endDate === '') {
            $this->endDate = $today;
        }
    }

    public function setMode(string $mode): void
    {
        $this->mode = $mode === 'period' ? 'period' : 'day';
    }

    public function setToday(): void
    {
        $this->mode = 'day';
        $this->selectedDate = now()->toDateString();
    }

    public function setYesterday(): void
    {
        $this->mode = 'day';
        $this->selectedDate = now()->subDay()->toDateString();
    }

    public function setThisWeek(): void
    {
        $this->mode = 'period';
        $this->startDate = now()->startOfWeek()->toDateString();
        $this->endDate = now()->toDateString();
    }

    public function setThisMonth(): void
    {
        $this->mode = 'period';
        $this->startDate = now()->startOfMonth()->toDateString();
        $this->endDate = now()->toDateString();
    }

    public function setLastMonth(): void
    {
        $this->mode = 'period';
        $this->startDate = now()->subMonth()->startOfMonth()->toDateString();
        $this->endDate = now()->subMonth()->endOfMonth()->toDateString();
    }

    public function getEffectiveDateRange(): array
    {
        if ($this->mode === 'day') {
            $date = $this->selectedDate ?: now()->toDateString();

            return [$date, $date];
        }

        $start = $this->startDate ?: now()->startOfMonth()->toDateString();
        $end = $this->endDate ?: now()->toDateString();

        if (Carbon::parse($start)->isAfter(Carbon::parse($end))) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }

    public function render(): View
    {
        [$start, $end] = $this->getEffectiveDateRange();

        $revenue = (new RevenueSummaryQuery($start, $end))->get();
        $cash = (new CashSummaryQuery($start, $end))->get();

        return view('livewire.finance.cash-revenue', [
            'revenue' => $revenue,
            'cash' => $cash,
            'startDate' => $start,
            'endDate' => $end,
            'currency' => setting('profile.currency', 'ETB'),
        ]);
    }
}
