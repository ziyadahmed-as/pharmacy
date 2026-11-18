<?php

namespace App\Livewire\Inventory;

use App\Models\Category;
use App\Services\ExpiryStockWidgetQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Expiry & Stock widget (UC-32, FR-7.7).
 *
 * OW and PM see all columns including cost and stock value.
 * PP sees the same widget without any cost or profit data.
 * Sort preference is remembered in the user's session.
 *
 * @uc UC-32 @fr FR-7.7 @br BR-06 @nfr NFR-1 (< 2 s), NFR-5 (mobile)
 */
#[Layout('layouts.app')]
#[Title('Expiry & Stock')]
class ExpiryStockWidget extends Component
{
    use WithPagination;

    // -------------------------------------------------------------------------
    // URL-bound filter / sort state
    // -------------------------------------------------------------------------

    #[Url]
    public string $sortBy = ExpiryStockWidgetQuery::SORT_NEAREST_EXPIRY;

    #[Url]
    public string $status = ExpiryStockWidgetQuery::STATUS_ALL;

    #[Url]
    public ?int $categoryId = null;

    #[Url]
    public string $classification = '';

    #[Url]
    public string $search = '';

    #[Url]
    public string $soldFrom = '';

    #[Url]
    public string $soldTo = '';

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user && $user->hasPermission('stock.view'), 403);

        // Restore previously saved sort preference from session.
        $saved = session('expiry_widget_sort');
        if ($saved && $this->sortBy === ExpiryStockWidgetQuery::SORT_NEAREST_EXPIRY) {
            $this->sortBy = $saved;
        }
    }

    // -------------------------------------------------------------------------
    // Watchers — reset pagination when filters change
    // -------------------------------------------------------------------------

    public function updatedSortBy(): void
    {
        $this->resetPage();
        session(['expiry_widget_sort' => $this->sortBy]);
    }

    public function updatedStatus(): void   { $this->resetPage(); }
    public function updatedCategoryId(): void { $this->resetPage(); }
    public function updatedClassification(): void { $this->resetPage(); }
    public function updatedSearch(): void   { $this->resetPage(); }
    public function updatedSoldFrom(): void { $this->resetPage(); }
    public function updatedSoldTo(): void   { $this->resetPage(); }

    // -------------------------------------------------------------------------
    // Quick-filter from summary card click
    // -------------------------------------------------------------------------

    public function filterByCard(string $card): void
    {
        $this->status = match ($card) {
            'expiring_30'            => ExpiryStockWidgetQuery::STATUS_EXPIRING_30,
            'expiring_60'            => ExpiryStockWidgetQuery::STATUS_EXPIRING_60,
            'expiring_90'            => ExpiryStockWidgetQuery::STATUS_EXPIRING_90,
            'expired_and_quarantine' => ExpiryStockWidgetQuery::STATUS_EXPIRED,
            'low_stock'              => ExpiryStockWidgetQuery::STATUS_SELLABLE,
            default                  => ExpiryStockWidgetQuery::STATUS_ALL,
        };
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->sortBy         = ExpiryStockWidgetQuery::SORT_NEAREST_EXPIRY;
        $this->status         = ExpiryStockWidgetQuery::STATUS_ALL;
        $this->categoryId     = null;
        $this->classification = '';
        $this->search         = '';
        $this->soldFrom       = '';
        $this->soldTo         = '';
        $this->resetPage();
    }

    // -------------------------------------------------------------------------
    // Post-sale refresh — re-render so quantities reflect the deduction
    // -------------------------------------------------------------------------

    #[On('browse-refresh')]
    public function onSaleCompleted(): void
    {
        // Livewire re-runs render() — quantities come from live BatchStock rows.
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------

    public function render(): View
    {
        $user        = Auth::user();
        $canViewCost = $user?->hasPermission('cost.view') ?? false;

        // Downgrade PP from cost-only sorts silently.
        if (! $canViewCost && in_array($this->sortBy, ExpiryStockWidgetQuery::COST_SORTS, true)) {
            $this->sortBy = ExpiryStockWidgetQuery::SORT_NEAREST_EXPIRY;
        }

        $query = new ExpiryStockWidgetQuery($this->buildFilters());

        $rows         = $query->paginate($user, 20);
        $summaryCards = $query->summaryCards($user);
        $categories   = Category::orderBy('name')->get(['id', 'name']);

        $windows     = setting('inventory.near_expiry_alert_days', [90, 60, 30]);
        $windows     = is_array($windows) ? $windows : [90, 60, 30];
        rsort($windows);

        $exportQuery = http_build_query(array_filter($this->buildFilters()));

        return view('livewire.inventory.expiry-stock-widget', [
            'rows'         => $rows,
            'summaryCards' => $summaryCards,
            'categories'   => $categories,
            'canViewCost'  => $canViewCost,
            'exportQuery'  => $exportQuery,
            'windows'      => $windows,
            'sortOptions'  => $this->sortOptions($canViewCost),
        ])->layout('layouts.app', ['title' => __('catalogue.expiry_stock_widget_title')]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildFilters(): array
    {
        return array_filter([
            'sort_by'        => $this->sortBy,
            'status'         => $this->status !== ExpiryStockWidgetQuery::STATUS_ALL ? $this->status : null,
            'category_id'    => $this->categoryId,
            'classification' => $this->classification !== '' ? $this->classification : null,
            'search'         => $this->search !== '' ? $this->search : null,
            'sold_from'      => $this->soldFrom !== '' ? $this->soldFrom : null,
            'sold_to'        => $this->soldTo !== '' ? $this->soldTo : null,
        ]);
    }

    private function sortOptions(bool $canViewCost): array
    {
        $options = [
            ExpiryStockWidgetQuery::SORT_NEAREST_EXPIRY    => __('catalogue.sort_nearest_expiry'),
            ExpiryStockWidgetQuery::SORT_FURTHEST_EXPIRY   => __('catalogue.sort_furthest_expiry'),
            ExpiryStockWidgetQuery::SORT_LONGEST_SINCE_MFG => __('catalogue.sort_longest_since_mfg'),
            ExpiryStockWidgetQuery::SORT_LOWEST_QTY        => __('catalogue.sort_lowest_qty'),
            ExpiryStockWidgetQuery::SORT_BEST_SELLING      => __('catalogue.sort_best_selling'),
            ExpiryStockWidgetQuery::SORT_NAME_AZ           => __('catalogue.sort_name_az'),
            ExpiryStockWidgetQuery::SORT_RECENTLY_REGISTERED => __('catalogue.sort_recently_registered'),
        ];

        if ($canViewCost) {
            $options[ExpiryStockWidgetQuery::SORT_HIGHEST_STOCK_VALUE]    = __('catalogue.sort_highest_stock_value');
            $options[ExpiryStockWidgetQuery::SORT_HIGHEST_POTENTIAL_LOSS] = __('catalogue.sort_highest_potential_loss');
        }

        return $options;
    }
}
