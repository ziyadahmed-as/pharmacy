<?php

namespace App\Livewire\Reports;

use App\Enums\Role;
use App\Jobs\ProcessQueuedReportExport;
use App\Models\Category;
use App\Models\Product;
use App\Models\ReportQueuedExport;
use App\Models\ReportRunsLog;
use App\Models\ReportSavedFilter;
use App\Models\ReportSchedule;
use App\Models\Till;
use App\Models\User;
use App\Reports\ReportRegistry;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Enhanced Report Run Component (UC-30).
 * Features:
 * - Common filters: date range, till, staff, category, product, search.
 * - Saved filter presets per user.
 * - Sorting, pagination, column visibility.
 * - Totals / summary rows.
 * - Export formats: CSV, Excel, PDF, Print, plus background queueing.
 * - Schedule report modal for owners (daily, weekly, monthly).
 */
#[Layout('layouts.app')]
#[Title('Run Report')]
class Run extends Component
{
    public string $type = '';

    #[Url]
    public string $mode = 'month'; // 'day' | 'week' | 'month' | 'year' | 'custom'

    #[Url]
    public string $startDate = '';

    #[Url]
    public string $endDate = '';

    #[Url]
    public string $groupBy = 'day';

    #[Url]
    public string $search = '';

    #[Url]
    public string $categoryId = '';

    #[Url]
    public string $userId = '';

    #[Url]
    public string $tillId = '';

    #[Url]
    public string $productId = '';

    #[Url]
    public string $status = '';

    public int $pageSize = 25;

    public int $page = 1;

    public ?int $sortCol = null;

    public string $sortDir = 'asc';

    public array $visibleColumns = [];

    // Saved filter presets
    public string $filterPresetName = '';

    public string $selectedPreset = '';

    public ?string $feedbackMessage = null;

    // Scheduling modal state (Owner only)
    public bool $showScheduleModal = false;

    public string $scheduleFrequency = 'daily';

    public string $scheduleFormat = 'pdf';

    public string $scheduleRecipients = '';

    public float $renderDurationMs = 0.0;

    public function mount(string $type, ReportRegistry $registry): void
    {
        $this->type = $type;
        /** @var User|null $user */
        $user = Auth::user();

        abort_if(! $user || $user->hasRole(Role::SystemAdmin) || ! $user->canAccessBusinessData(), 403);

        $definition = $registry->get($type);
        abort_unless($definition && $definition->canView($user), 403);

        $tz = config('pms.timezone', 'Africa/Addis_Ababa');

        if ($this->startDate === '') {
            $this->startDate = now()->timezone($tz)->startOfMonth()->format('Y-m-d');
        }

        if ($this->endDate === '') {
            $this->endDate = business_date();
        }

        // Initialize all columns as visible
        $headers = $definition->headers($user);
        $this->visibleColumns = array_fill(0, count($headers), true);
    }

    public function setToday(): void
    {
        $this->mode = 'day';
        $this->startDate = business_date();
        $this->endDate = business_date();
        $this->groupBy = 'day';
        $this->page = 1;
    }

    public function setYesterday(): void
    {
        $this->mode = 'day';
        $this->startDate = business_date(now()->subDay());
        $this->endDate = business_date(now()->subDay());
        $this->groupBy = 'day';
        $this->page = 1;
    }

    public function setThisWeek(): void
    {
        $tz = config('pms.timezone', 'Africa/Addis_Ababa');
        $this->mode = 'week';
        $this->startDate = now()->timezone($tz)->startOfWeek()->format('Y-m-d');
        $this->endDate = business_date();
        $this->groupBy = 'day';
        $this->page = 1;
    }

    public function setThisMonth(): void
    {
        $tz = config('pms.timezone', 'Africa/Addis_Ababa');
        $this->mode = 'month';
        $this->startDate = now()->timezone($tz)->startOfMonth()->format('Y-m-d');
        $this->endDate = business_date();
        $this->groupBy = 'day';
        $this->page = 1;
    }

    public function setThisYear(): void
    {
        $tz = config('pms.timezone', 'Africa/Addis_Ababa');
        $this->mode = 'year';
        $this->startDate = now()->timezone($tz)->startOfYear()->format('Y-m-d');
        $this->endDate = business_date();
        $this->groupBy = 'month';
        $this->page = 1;
    }

    public function sortBy(int $colIndex): void
    {
        if ($this->sortCol === $colIndex) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortCol = $colIndex;
            $this->sortDir = 'asc';
        }
    }

    public function toggleColumn(int $index): void
    {
        $this->visibleColumns[$index] = ! ($this->visibleColumns[$index] ?? true);
    }

    public function saveCurrentFilter(): void
    {
        if (trim($this->filterPresetName) === '') {
            return;
        }

        $userId = Auth::id();
        ReportSavedFilter::updateOrCreate(
            [
                'user_id' => $userId,
                'report_key' => $this->type,
                'name' => trim($this->filterPresetName),
            ],
            [
                'filters' => [
                    'start_date' => $this->startDate,
                    'end_date' => $this->endDate,
                    'mode' => $this->mode,
                    'group_by' => $this->groupBy,
                    'search' => $this->search,
                    'category_id' => $this->categoryId,
                    'user_id' => $this->userId,
                    'till_id' => $this->tillId,
                    'product_id' => $this->productId,
                    'status' => $this->status,
                ],
            ]
        );

        $this->feedbackMessage = "Filter preset '{$this->filterPresetName}' saved.";
        $this->filterPresetName = '';
    }

    public function loadSavedFilter(string $name): void
    {
        $userId = Auth::id();
        $preset = ReportSavedFilter::where('user_id', $userId)
            ->where('report_key', $this->type)
            ->where('name', $name)
            ->first();

        if ($preset && is_array($preset->filters)) {
            $f = $preset->filters;
            $this->startDate = $f['start_date'] ?? $this->startDate;
            $this->endDate = $f['end_date'] ?? $this->endDate;
            $this->mode = $f['mode'] ?? $this->mode;
            $this->groupBy = $f['group_by'] ?? $this->groupBy;
            $this->search = $f['search'] ?? '';
            $this->categoryId = $f['category_id'] ?? '';
            $this->userId = $f['user_id'] ?? '';
            $this->tillId = $f['till_id'] ?? '';
            $this->productId = $f['product_id'] ?? '';
            $this->status = $f['status'] ?? '';
            $this->page = 1;
            $this->feedbackMessage = "Loaded preset '{$name}'.";
        }
    }

    public function queueExport(string $format): void
    {
        $userId = Auth::id();
        $filters = [
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'group_by' => $this->groupBy,
            'search' => $this->search,
            'category_id' => $this->categoryId,
            'user_id' => $this->userId,
            'till_id' => $this->tillId,
            'product_id' => $this->productId,
            'status' => $this->status,
        ];

        $export = ReportQueuedExport::create([
            'user_id' => $userId,
            'report_key' => $this->type,
            'format' => $format,
            'filters' => $filters,
            'status' => ReportQueuedExport::STATUS_PENDING,
        ]);

        ProcessQueuedReportExport::dispatch($export->id);

        $this->feedbackMessage = 'Export job queued! You will receive a notification when ready.';
    }

    public function createSchedule(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasRole(Role::Owner)) {
            return;
        }

        $emails = array_filter(array_map('trim', explode(',', $this->scheduleRecipients)));
        if (empty($emails)) {
            $emails = [$user->email];
        }

        $filters = [
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'group_by' => $this->groupBy,
            'search' => $this->search,
            'category_id' => $this->categoryId,
            'user_id' => $this->userId,
            'till_id' => $this->tillId,
            'product_id' => $this->productId,
            'status' => $this->status,
        ];

        ReportSchedule::create([
            'report_key' => $this->type,
            'frequency' => $this->scheduleFrequency,
            'format' => $this->scheduleFormat,
            'recipient_emails' => $emails,
            'filters' => $filters,
            'is_active' => true,
            'created_by' => $user->id,
            'next_run_at' => match ($this->scheduleFrequency) {
                'weekly' => now()->addWeek()->startOfWeek()->addHours(8),
                'monthly' => now()->addMonth()->startOfMonth()->addHours(8),
                default => now()->addDay()->startOfDay()->addHours(8),
            },
        ]);

        AuditLogger::record('report.schedule_created', null, [], [], [
            'report' => $this->type,
            'frequency' => $this->scheduleFrequency,
            'recipients' => $emails,
        ]);

        $this->showScheduleModal = false;
        $this->scheduleRecipients = '';
        $this->feedbackMessage = 'Report schedule created successfully.';
    }

    public function render(ReportRegistry $registry): View
    {
        /** @var User $user */
        $user = Auth::user();

        $definition = $registry->get($this->type);
        abort_unless($definition && $definition->canView($user), 403);

        $filters = [
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'group_by' => $this->groupBy,
            'search' => $this->search,
            'category_id' => $this->categoryId,
            'user_id' => $this->userId,
            'till_id' => $this->tillId,
            'product_id' => $this->productId,
            'status' => $this->status,
        ];

        $startTime = microtime(true);
        $result = $definition->query($filters, $user);
        $this->renderDurationMs = round((microtime(true) - $startTime) * 1000, 2);

        // Record execution in log
        ReportRunsLog::create([
            'user_id' => $user->id,
            'report_key' => $this->type,
            'filters' => $filters,
            'row_count' => $result['row_count'] ?? count($result['rows']),
            'duration_ms' => $this->renderDurationMs,
            'run_at' => now(),
        ]);

        $allRows = $result['rows'];

        // Apply client-side sorting if requested
        if ($this->sortCol !== null && ! empty($allRows)) {
            usort($allRows, function ($a, $b) {
                $valA = $a[$this->sortCol] ?? '';
                $valB = $b[$this->sortCol] ?? '';

                if (is_numeric($valA) && is_numeric($valB)) {
                    return $this->sortDir === 'asc' ? $valA <=> $valB : $valB <=> $valA;
                }

                return $this->sortDir === 'asc' ? strcmp((string) $valA, (string) $valB) : strcmp((string) $valB, (string) $valA);
            });
        }

        // Pagination
        $totalRows = count($allRows);
        $totalPages = max(1, (int) ceil($totalRows / $this->pageSize));
        $this->page = max(1, min($this->page, $totalPages));
        $paginatedRows = array_slice($allRows, ($this->page - 1) * $this->pageSize, $this->pageSize);

        // Reference lists
        $categories = Category::orderBy('name')->get();
        $staffUsers = User::whereHas('roles', fn ($q) => $q->where('slug', Role::PharmacyStaff->value))->get();
        $tills = Till::where('is_active', true)->orderBy('name')->get();
        $products = Product::where('is_active', true)->orderBy('brand')->take(100)->get();
        $savedFilters = ReportSavedFilter::where('user_id', $user->id)->where('report_key', $this->type)->get();

        return view('livewire.reports.run', [
            'definition' => $definition,
            'headers' => $result['headers'],
            'rows' => $paginatedRows,
            'allRowsCount' => $totalRows,
            'totalPages' => $totalPages,
            'summary' => $result['summary'],
            'categories' => $categories,
            'staffUsers' => $staffUsers,
            'tills' => $tills,
            'products' => $products,
            'savedFilters' => $savedFilters,
            'canViewCost' => $definition->canViewCost($user),
            'currency' => setting('profile.currency', 'ETB'),
            'isOwner' => $user->hasRole(Role::Owner),
        ]);
    }
}
