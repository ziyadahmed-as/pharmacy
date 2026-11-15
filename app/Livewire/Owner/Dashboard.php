<?php

namespace App\Livewire\Owner;

use App\Actions\Owner\SendDailySummary;
use App\Enums\Role;
use App\Models\Staff;
use App\Models\Till;
use App\Services\OwnerCommandCentreService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Executive Owner Command Centre (UC-32 v2).
 * Fast (< 3s), cached queries with 60-second refresh, multi-dimension filters,
 * 12 core KPIs with previous-period comparison, 7 interactive charts with table toggle & CSV,
 * Needs-Attention panel, live activity feed, and deep drill-down links.
 *
 * @uc UC-32
 *
 * @fr FR-7.7, FR-9.2
 */
#[Layout('layouts.app')]
#[Title('Owner Command Centre')]
class Dashboard extends Component
{
    #[Url]
    public string $period = 'today'; // 'today', 'yesterday', '7_days', 'this_month', 'custom'

    #[Url]
    public ?string $dateFrom = null;

    #[Url]
    public ?string $dateTo = null;

    #[Url]
    public ?int $tillId = null;

    #[Url]
    public ?int $staffId = null;

    public array $showTable = [];

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public bool $forceFresh = false;

    public function mount(OwnerCommandCentreService $service): void
    {
        $user = Auth::user();
        abort_unless($user, 403);
        abort_unless($user->hasRole(Role::Owner), 403);

        if ($this->period !== 'custom' || empty($this->dateFrom)) {
            [$from, $to] = $service->resolveDateRanges($this->period, $this->dateFrom, $this->dateTo);
            $this->dateFrom = $from->toDateString();
            $this->dateTo = $to->toDateString();
        }
    }

    public function updatedPeriod(string $value, OwnerCommandCentreService $service): void
    {
        if ($value !== 'custom') {
            [$from, $to] = $service->resolveDateRanges($value);
            $this->dateFrom = $from->toDateString();
            $this->dateTo = $to->toDateString();
        }
        $this->forceFresh = true;
    }

    public function updatedDateFrom(): void
    {
        $this->period = 'custom';
        $this->forceFresh = true;
    }

    public function updatedDateTo(): void
    {
        $this->period = 'custom';
        $this->forceFresh = true;
    }

    public function updatedTillId(): void
    {
        $this->forceFresh = true;
    }

    public function updatedStaffId(): void
    {
        $this->forceFresh = true;
    }

    public function toggleTable(string $chartKey): void
    {
        $this->showTable[$chartKey] = ! ($this->showTable[$chartKey] ?? false);
    }

    public function refreshDashboard(): void
    {
        $this->forceFresh = true;
        $this->statusMessage = __('dashboard.owner.refreshed_success');
    }

    public function sendSummary(SendDailySummary $action, string $channel = 'sms'): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        try {
            $result = $action->execute(Auth::user(), $channel);
            if ($result['success']) {
                $this->statusMessage = __('dashboard.owner.summary_sent_success', ['recipient' => $result['recipient']]);
            } else {
                $this->errorMessage = __('dashboard.owner.summary_sent_failed', ['error' => $result['error'] ?? 'Unknown error']);
            }
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function exportChartCsv(string $chartKey, OwnerCommandCentreService $service): StreamedResponse
    {
        $data = $service->getDashboardData(
            $this->period,
            $this->dateFrom,
            $this->dateTo,
            $this->tillId,
            $this->staffId
        );

        $chart = $data['charts'][$chartKey] ?? null;
        abort_unless($chart, 404);

        $filename = "owner_{$chartKey}_{$this->period}_".now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($chart) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Label', 'Revenue_ETB', 'Count_or_Quantity', 'Percentage_of_Total']);

            foreach ($chart['data'] as $item) {
                fputcsv($handle, [
                    $item['label'],
                    number_format(($item['value'] ?? 0) / 100, 2, '.', ''),
                    $item['count'] ?? 0,
                    $item['percentage'] ?? 0,
                ]);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function render(OwnerCommandCentreService $service): View
    {
        $data = $service->getDashboardData(
            $this->period,
            $this->dateFrom,
            $this->dateTo,
            $this->tillId,
            $this->staffId,
            $this->forceFresh
        );

        $this->forceFresh = false;

        $tills = Till::where('is_active', true)->orderBy('name')->get();
        $staffMembers = Staff::where('status', '!=', Staff::ENDED)->orderBy('name')->get();

        return view('livewire.owner.dashboard', [
            'data' => $data,
            'tills' => $tills,
            'staffMembers' => $staffMembers,
        ]);
    }
}
