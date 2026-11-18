<div class="space-y-5">

    {{-- Page header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('catalogue.expiry_stock_widget_title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('catalogue.expiry_stock_widget_subtitle') }}</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('inventory.expiry-stock.export', ['format' => 'csv']) }}?{{ $exportQuery }}"
               class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
                <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                {{ __('common.export_csv') }}
            </a>
            <a href="{{ route('inventory.expiry-stock.export', ['format' => 'pdf']) }}?{{ $exportQuery }}"
               class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
                <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                {{ __('common.export_pdf') }}
            </a>
        </div>
    </div>

    {{-- ===================================================================== --}}
    {{-- Summary cards                                                         --}}
    {{-- ===================================================================== --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        @php
            $cards = [
                ['key' => 'expiring_30',            'label' => __('catalogue.card_expiring_30'),            'icon' => '⏳', 'colour' => 'rose'],
                ['key' => 'expiring_60',            'label' => __('catalogue.card_expiring_60'),            'icon' => '🕐', 'colour' => 'orange'],
                ['key' => 'expiring_90',            'label' => __('catalogue.card_expiring_90'),            'icon' => '⚠️', 'colour' => 'yellow'],
                ['key' => 'expired_and_quarantine', 'label' => __('catalogue.card_expired_quarantine'),     'icon' => '🛑', 'colour' => 'rose'],
                ['key' => 'low_stock',              'label' => __('catalogue.card_low_stock'),              'icon' => '📉', 'colour' => 'amber'],
                ['key' => 'sold_today',             'label' => __('catalogue.card_sold_today'),             'icon' => '🛒', 'colour' => 'teal'],
            ];
        @endphp
        @foreach ($cards as $card)
            @php
                $val = $summaryCards[$card['key']] ?? 0;
                $colourMap = [
                    'rose'   => 'border-rose-200 bg-rose-50 text-rose-800',
                    'orange' => 'border-orange-200 bg-orange-50 text-orange-800',
                    'yellow' => 'border-yellow-200 bg-yellow-50 text-yellow-800',
                    'amber'  => 'border-amber-200 bg-amber-50 text-amber-800',
                    'teal'   => 'border-teal-200 bg-teal-50 text-teal-800',
                ];
                $cls = $colourMap[$card['colour']] ?? 'border-slate-200 bg-slate-50 text-slate-700';
            @endphp
            <button type="button"
                wire:click="filterByCard('{{ $card['key'] }}')"
                class="rounded-xl border {{ $cls }} p-3 text-left hover:shadow-sm transition focus:outline-none focus:ring-2 focus:ring-teal-400">
                <div class="text-xl mb-1">{{ $card['icon'] }}</div>
                <div class="text-2xl font-extrabold font-mono">{{ number_format($val) }}</div>
                <div class="text-[11px] font-semibold uppercase tracking-wide mt-0.5 opacity-80">{{ $card['label'] }}</div>
            </button>
        @endforeach

        {{-- Total stock value — OW/PM only --}}
        @if ($canViewCost && $summaryCards['total_stock_value'] !== null)
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-3 col-span-2 sm:col-span-1">
                <div class="text-xl mb-1">💰</div>
                <div class="text-2xl font-extrabold font-mono text-indigo-800">
                    {{ number_format($summaryCards['total_stock_value'] / 100, 0) }}
                </div>
                <div class="text-[11px] font-semibold uppercase tracking-wide mt-0.5 text-indigo-700">{{ __('catalogue.card_total_stock_value') }}</div>
            </div>
        @endif
    </div>

    {{-- ===================================================================== --}}
    {{-- Filters & sort toolbar                                                --}}
    {{-- ===================================================================== --}}
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-3">

            {{-- Search --}}
            <div class="xl:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('common.search') }}</label>
                <div class="relative">
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           placeholder="{{ __('catalogue.search_stock_placeholder') }}"
                           class="block w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm placeholder:text-slate-400 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                </div>
            </div>

            {{-- Sort --}}
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('catalogue.sort_by') }}</label>
                <select wire:model.live="sortBy" class="block w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                    @foreach ($sortOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Status filter --}}
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('common.status') }}</label>
                <select wire:model.live="status" class="block w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                    <option value="all">{{ __('common.all') }}</option>
                    <option value="{{ \App\Services\ExpiryStockWidgetQuery::STATUS_SELLABLE }}">{{ __('catalogue.filter_sellable') }}</option>
                    <option value="{{ \App\Services\ExpiryStockWidgetQuery::STATUS_EXPIRING_30 }}">⏳ {{ __('catalogue.filter_expiring_30') }}</option>
                    <option value="{{ \App\Services\ExpiryStockWidgetQuery::STATUS_EXPIRING_60 }}">🕐 {{ __('catalogue.filter_expiring_60') }}</option>
                    <option value="{{ \App\Services\ExpiryStockWidgetQuery::STATUS_EXPIRING_90 }}">⚠️ {{ __('catalogue.filter_expiring_90') }}</option>
                    <option value="{{ \App\Services\ExpiryStockWidgetQuery::STATUS_EXPIRED }}">🛑 {{ __('catalogue.filter_expired') }}</option>
                    <option value="{{ \App\Services\ExpiryStockWidgetQuery::STATUS_QUARANTINED }}">🔒 {{ __('catalogue.filter_quarantine') }}</option>
                    <option value="{{ \App\Services\ExpiryStockWidgetQuery::STATUS_DEPLETED }}">📦 {{ __('catalogue.filter_depleted') }}</option>
                </select>
            </div>

            {{-- Category --}}
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('catalogue.category') }}</label>
                <select wire:model.live="categoryId" class="block w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                    <option value="">{{ __('common.all') }}</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Classification --}}
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('catalogue.classification') }}</label>
                <select wire:model.live="classification" class="block w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                    <option value="">{{ __('common.all') }}</option>
                    <option value="rx">Rx</option>
                    <option value="otc">OTC</option>
                    <option value="controlled">{{ __('catalogue.controlled') }}</option>
                    <option value="non_drug">{{ __('catalogue.non_drug') }}</option>
                </select>
            </div>

        </div>

        {{-- Sold date range row --}}
        <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('catalogue.sold_from') }}</label>
                <input type="date" wire:model.live="soldFrom" class="block w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('catalogue.sold_to') }}</label>
                <input type="date" wire:model.live="soldTo" class="block w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
            </div>
            <div class="flex items-end">
                <button type="button" wire:click="resetFilters"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    {{ __('common.clear_filter') }}
                </button>
            </div>
        </div>
    </div>

    {{-- ===================================================================== --}}
    {{-- Batch table                                                           --}}
    {{-- ===================================================================== --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto" wire:loading.class="opacity-60" wire:target="sortBy,status,categoryId,classification,search,soldFrom,soldTo">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50/75 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="w-10 px-3 py-3.5"></th>
                        <th class="px-4 py-3.5 text-left">{{ __('catalogue.product_name') }}</th>
                        <th class="px-4 py-3.5 text-left">{{ __('catalogue.batch_no') }}</th>
                        <th class="px-4 py-3.5 text-left">{{ __('catalogue.mfg_date') }}</th>
                        <th class="px-4 py-3.5 text-left">{{ __('catalogue.expiry_date') }}</th>
                        <th class="px-3 py-3.5 text-center">{{ __('catalogue.days_left') }}</th>
                        <th class="px-4 py-3.5 text-right">{{ __('catalogue.qty_remaining') }}</th>
                        <th class="px-4 py-3.5 text-right">{{ __('catalogue.qty_sold') }}</th>
                        <th class="px-4 py-3.5 text-right">{{ __('catalogue.selling_price') }}</th>
                        <th class="px-4 py-3.5 text-left">{{ __('common.status') }}</th>
                        @if ($canViewCost)
                            <th class="px-4 py-3.5 text-right">{{ __('catalogue.unit_cost') }}</th>
                            <th class="px-4 py-3.5 text-right">{{ __('catalogue.stock_value') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($rows as $row)
                        @php
                            $daysLeft = is_numeric($row->days_left) ? (int) $row->days_left : null;
                            $colour   = \App\Services\NearestExpiryQuery::expiryColour($daysLeft);
                            $badgeCls = match ($colour) {
                                'green'  => 'bg-emerald-100 text-emerald-800',
                                'yellow' => 'bg-yellow-100 text-yellow-800',
                                'orange' => 'bg-orange-100 text-orange-800',
                                'red'    => 'bg-rose-100 text-rose-700',
                                default  => 'bg-slate-100 text-slate-500',
                            };
                            $isOos = ((int) ($row->qty_remaining ?? 0)) === 0;
                        @endphp
                        <tr class="hover:bg-slate-50/60 transition {{ $isOos ? 'opacity-50' : '' }}">

                            {{-- Thumbnail --}}
                            <td class="px-3 py-3">
                                <img src="{{ \App\Services\ProductImageService::thumbnailUrl($row->thumbnail_path) }}"
                                     alt="{{ $row->brand }}"
                                     class="h-8 w-8 rounded-lg object-cover border border-slate-200 bg-slate-50">
                            </td>

                            {{-- Product --}}
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-900 text-sm leading-tight">{{ $row->brand }}</div>
                                <div class="text-xs text-slate-400 mt-0.5">{{ $row->generic_name }}@if($row->strength) · {{ $row->strength }}@endif@if($row->dosage_form) {{ $row->dosage_form }}@endif</div>
                                <div class="mt-0.5">
                                    <span @class([
                                        'inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold',
                                        'bg-blue-50 text-blue-700'   => $row->classification === 'rx',
                                        'bg-rose-50 text-rose-700'   => $row->classification === 'controlled',
                                        'bg-emerald-50 text-emerald-700' => $row->classification === 'otc',
                                        'bg-slate-100 text-slate-600' => $row->classification === 'non_drug',
                                    ])>{{ strtoupper($row->classification) }}</span>
                                    @if ($row->category_name)
                                        <span class="ml-1 text-[10px] text-slate-400">{{ $row->category_name }}</span>
                                    @endif
                                </div>
                            </td>

                            {{-- Batch No --}}
                            <td class="px-4 py-3 font-mono text-xs text-slate-700">{{ $row->batch_no }}</td>

                            {{-- Mfg date --}}
                            <td class="px-4 py-3 text-xs text-slate-500">{{ $row->manufacturing_date ?? '—' }}</td>

                            {{-- Expiry date --}}
                            <td class="px-4 py-3 text-xs font-medium text-slate-700 whitespace-nowrap">{{ $row->expiry_date }}</td>

                            {{-- Days left badge --}}
                            <td class="px-3 py-3 text-center">
                                @if ($daysLeft !== null)
                                    <span class="inline-flex items-center rounded px-2 py-0.5 text-[11px] font-bold {{ $badgeCls }}">
                                        {{ $daysLeft }}d
                                    </span>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>

                            {{-- Qty remaining --}}
                            <td class="px-4 py-3 text-right font-semibold font-mono {{ $isOos ? 'text-rose-600' : 'text-slate-900' }}">
                                {{ number_format((int) ($row->qty_remaining ?? 0)) }}
                                @if ($isOos)
                                    <span class="ml-1 text-[10px] font-bold bg-rose-100 text-rose-700 px-1 rounded">{{ __('catalogue.out_of_stock') }}</span>
                                @endif
                            </td>

                            {{-- Qty sold --}}
                            <td class="px-4 py-3 text-right font-mono text-xs text-slate-600">
                                {{ number_format((int) ($row->qty_sold ?? 0)) }}
                            </td>

                            {{-- Selling price --}}
                            <td class="px-4 py-3 text-right text-xs font-medium text-slate-700">
                                @if ($row->selling_price)
                                    {{ number_format($row->selling_price / 100, 2) }} ETB
                                @else
                                    <span class="text-slate-400 italic text-[11px]">{{ __('catalogue.no_price_set') }}</span>
                                @endif
                            </td>

                            {{-- Batch status --}}
                            <td class="px-4 py-3">
                                @php $bs = $row->batch_status ?? 'SELLABLE'; @endphp
                                <span @class([
                                    'inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold',
                                    'bg-emerald-50 text-emerald-800'  => $bs === 'SELLABLE',
                                    'bg-amber-100 text-amber-800'     => $bs === 'QUARANTINED',
                                    'bg-rose-100 text-rose-800'       => $bs === 'RECALLED',
                                    'bg-slate-100 text-slate-500'     => $bs === 'DEPLETED',
                                ])>{{ $bs }}</span>
                            </td>

                            {{-- Cost columns (OW/PM only) --}}
                            @if ($canViewCost)
                                <td class="px-4 py-3 text-right text-xs font-mono text-slate-600">
                                    {{ $row->unit_cost ? number_format($row->unit_cost / 100, 2) . ' ETB' : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right text-xs font-mono font-semibold text-slate-700">
                                    {{ ($row->stock_value_at_cost > 0) ? number_format($row->stock_value_at_cost / 100, 2) . ' ETB' : '—' }}
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canViewCost ? 12 : 10 }}" class="px-6 py-14 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="h-10 w-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                                    <p class="text-sm font-medium text-slate-500">{{ __('catalogue.no_stock_found') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($rows->hasPages())
            <div class="px-5 py-3 border-t border-slate-200 bg-slate-50/50 text-xs">
                {{ $rows->links() }}
            </div>
        @endif
    </div>

</div>
