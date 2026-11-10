<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('catalogue.stock_on_hand_title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('catalogue.stock_on_hand_subtitle') }}</p>
        </div>
        <div class="flex items-center gap-2.5">
            @if ($canExport)
                <a href="{{ route('inventory.stock.export', ['format' => 'csv']) }}?{{ $exportQuery }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
                    <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    {{ __('common.export_csv') }}
                </a>
                <a href="{{ route('inventory.stock.export', ['format' => 'pdf']) }}?{{ $exportQuery }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
                    <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    {{ __('common.export_pdf') }}
                </a>
            @endif
            <a href="{{ route('inventory.ledger') }}" class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ __('catalogue.view_ledger') }}
            </a>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('common.search') }}</label>
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('catalogue.search_stock_placeholder') }}" class="block w-full rounded-lg border border-slate-300 py-2 pl-9 pr-3 text-sm placeholder:text-slate-400 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
            </div>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('catalogue.filter_status') }}</label>
            <select wire:model.live="filter" class="block w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                <option value="">{{ __('common.all') }}</option>
                <option value="low_stock">⚠️ {{ __('catalogue.filter_low_stock') }}</option>
                <option value="near_expiry">⏳ {{ __('catalogue.filter_near_expiry') }}</option>
                <option value="expired">🛑 {{ __('catalogue.filter_expired') }}</option>
                <option value="quarantine">🔒 {{ __('catalogue.filter_quarantine') }}</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('catalogue.category') }}</label>
            <select wire:model.live="category_id" class="block w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                <option value="">{{ __('common.all') }}</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Products with Stocks Table -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50/75 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th scope="col" class="w-10 px-3 py-3.5"></th>
                        <th scope="col" class="px-4 py-3.5 text-left">{{ __('catalogue.product_name') }}</th>
                        <th scope="col" class="px-4 py-3.5 text-left">{{ __('catalogue.barcode') }}</th>
                        <th scope="col" class="px-4 py-3.5 text-right">{{ __('catalogue.sellable_stock') }}</th>
                        <th scope="col" class="px-4 py-3.5 text-right">{{ __('catalogue.quarantine_stock') }}</th>
                        <th scope="col" class="px-4 py-3.5 text-right">{{ __('catalogue.reorder_level') }}</th>
                        @if ($canViewCost)
                            <th scope="col" class="px-4 py-3.5 text-right">{{ __('catalogue.valuation') }}</th>
                        @endif
                        <th scope="col" class="px-4 py-3.5 text-center">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($products as $p)
                        @php
                            $m = $productMetrics[$p->id] ?? [
                                'total_sellable' => 0,
                                'total_quarantine' => 0,
                                'total_valuation' => 0,
                                'has_near_expiry' => false,
                                'has_expired' => false,
                            ];
                            $isExpanded = isset($expanded[$p->id]);
                            $isLow = $p->reorder_level > 0 && $m['total_sellable'] <= $p->reorder_level;
                        @endphp
                        <tr class="hover:bg-slate-50/70 transition {{ $isLow ? 'bg-amber-50/40' : '' }}">
                            <td class="px-3 py-3.5 text-center">
                                @if ($p->batches->isNotEmpty())
                                    <button wire:click="toggleExpand({{ $p->id }})" class="p-1 rounded-md text-slate-400 hover:text-teal-700 hover:bg-slate-100 focus:outline-none transition">
                                        <svg class="h-4 w-4 transform transition-transform {{ $isExpanded ? 'rotate-90 text-teal-700' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </button>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 font-semibold text-slate-900">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('catalogue.show', $p) }}" class="text-teal-700 hover:text-teal-900 transition font-bold">{{ $p->brand }}</a>
                                    @if ($m['has_expired'])
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-700" title="{{ __('catalogue.filter_expired') }}">🛑 Exp</span>
                                    @elseif ($m['has_near_expiry'])
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700" title="{{ __('catalogue.filter_near_expiry') }}">⏳ Soon</span>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-400 font-normal mt-0.5">{{ $p->generic_name }} · {{ $p->dosage_form }} · {{ $p->strength }}</div>
                            </td>
                            <td class="px-4 py-3.5 text-slate-500 text-xs">
                                <div class="font-mono">{{ $p->barcode ?? '-' }}</div>
                                <div class="text-slate-400">{{ $p->category?->name ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3.5 text-right font-semibold {{ $m['total_sellable'] > 0 ? 'text-slate-900' : 'text-slate-400' }}">
                                {{ number_format($m['total_sellable']) }}
                                @if ($isLow)
                                    <span class="ml-1 text-xs text-amber-600" title="{{ __('catalogue.low_stock_warning') }}">⚠️</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right">
                                @if ($m['total_quarantine'] > 0)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 ring-1 ring-rose-600/20">
                                        🔒 {{ number_format($m['total_quarantine']) }}
                                    </span>
                                @else
                                    <span class="text-slate-300">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right text-xs text-slate-500">
                                {{ $p->reorder_level ? number_format($p->reorder_level) : '-' }}
                            </td>
                            @if ($canViewCost)
                                <td class="px-4 py-3.5 text-right font-mono font-medium text-slate-700">
                                    {{ $m['total_valuation'] !== null ? number_format($m['total_valuation'] / 100, 2) . ' ETB' : '-' }}
                                </td>
                            @endif
                            <td class="px-4 py-3.5 text-center whitespace-nowrap text-xs">
                                <a href="{{ route('inventory.ledger', ['product_id' => $p->id]) }}" class="inline-flex items-center gap-1 text-teal-700 hover:text-teal-900 font-semibold transition">
                                    {{ __('catalogue.ledger') }}
                                </a>
                            </td>
                        </tr>

                        <!-- Expanded Batches Sub-table -->
                        @if ($isExpanded)
                            <tr class="bg-slate-50/75">
                                <td colspan="{{ $canViewCost ? 8 : 7 }}" class="px-6 py-4">
                                    <div class="rounded-xl border border-slate-200 bg-white overflow-hidden shadow-xs">
                                        <div class="px-4 py-2.5 bg-slate-100/70 border-b border-slate-200 text-xs font-semibold text-slate-600 flex items-center justify-between">
                                            <span>{{ __('catalogue.batches_for_product', ['brand' => $p->brand]) }}</span>
                                            <span class="text-slate-400 font-medium">{{ $p->batches->count() }} {{ __('catalogue.batches_count') }}</span>
                                        </div>
                                        <table class="min-w-full divide-y divide-slate-100 text-xs">
                                            <thead class="bg-slate-50/75 text-slate-400 uppercase tracking-wider font-semibold">
                                                <tr>
                                                    <th class="px-4 py-2 text-left">{{ __('catalogue.batch_no') }}</th>
                                                    <th class="px-4 py-2 text-left">{{ __('catalogue.expiry_date') }}</th>
                                                    <th class="px-4 py-2 text-left">{{ __('catalogue.location') }}</th>
                                                    <th class="px-4 py-2 text-left">{{ __('common.status') }}</th>
                                                    <th class="px-4 py-2 text-right">{{ __('catalogue.quantity') }}</th>
                                                    @if ($canViewCost)
                                                        <th class="px-4 py-2 text-right">{{ __('catalogue.unit_cost') }}</th>
                                                        <th class="px-4 py-2 text-right">{{ __('catalogue.valuation') }}</th>
                                                    @endif
                                                    <th class="px-4 py-2 text-center">{{ __('common.actions') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100">
                                                @foreach ($p->batches as $batch)
                                                    @php
                                                        $today = now()->format('Y-m-d');
                                                        $nearThreshold = now()->addDays((int)(setting('stock.near_expiry_days') ?? 90))->format('Y-m-d');
                                                        $bExp = $batch->expiry_date?->format('Y-m-d');
                                                        $isExpired = $bExp && $bExp <= $today;
                                                        $isNear = $bExp && $bExp > $today && $bExp <= $nearThreshold;
                                                    @endphp
                                                    @forelse ($batch->stocks as $stock)
                                                        <tr class="hover:bg-slate-50/70 transition">
                                                            <td class="px-4 py-2 font-mono font-semibold text-slate-900">
                                                                {{ $batch->batch_no }}
                                                            </td>
                                                            <td class="px-4 py-2 whitespace-nowrap">
                                                                <span class="font-medium text-slate-700">{{ $bExp ?? '-' }}</span>
                                                                @if ($isExpired)
                                                                    <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-700">🛑 EXPIRED</span>
                                                                @elseif ($isNear)
                                                                    <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700">⏳ NEAR</span>
                                                                @endif
                                                            </td>
                                                            <td class="px-4 py-2 text-slate-500">{{ $stock->location ?? '-' }}</td>
                                                            <td class="px-4 py-2">
                                                                @if ($stock->status === 'sellable')
                                                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-800">Sellable</span>
                                                                @else
                                                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-100 text-rose-800">Quarantine</span>
                                                                @endif
                                                            </td>
                                                            <td class="px-4 py-2 text-right font-bold text-slate-900">
                                                                {{ number_format($stock->quantity) }}
                                                            </td>
                                                            @if ($canViewCost)
                                                                <td class="px-4 py-2 text-right font-mono text-slate-600">
                                                                    {{ $batch->unit_cost !== null ? number_format($batch->unit_cost / 100, 2) . ' ETB' : '-' }}
                                                                </td>
                                                                <td class="px-4 py-2 text-right font-mono font-semibold text-slate-900">
                                                                    {{ $batch->unit_cost !== null ? number_format(($stock->quantity * $batch->unit_cost) / 100, 2) . ' ETB' : '-' }}
                                                                </td>
                                                            @endif
                                                            <td class="px-4 py-2 text-center">
                                                                <a href="{{ route('inventory.ledger', ['batch_id' => $batch->id]) }}" class="text-teal-700 hover:text-teal-900 font-semibold transition">
                                                                    {{ __('catalogue.ledger') }}
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr class="hover:bg-slate-50/70 text-slate-400">
                                                            <td class="px-4 py-2 font-mono">{{ $batch->batch_no }}</td>
                                                            <td class="px-4 py-2">{{ $bExp ?? '-' }}</td>
                                                            <td class="px-4 py-2">-</td>
                                                            <td class="px-4 py-2"><span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 text-slate-600">No stock</span></td>
                                                            <td class="px-4 py-2 text-right">0</td>
                                                            @if ($canViewCost)
                                                                <td class="px-4 py-2 text-right font-mono">{{ $batch->unit_cost !== null ? number_format($batch->unit_cost / 100, 2) . ' ETB' : '-' }}</td>
                                                                <td class="px-4 py-2 text-right font-mono">0.00 ETB</td>
                                                            @endif
                                                            <td class="px-4 py-2 text-center">
                                                                <a href="{{ route('inventory.ledger', ['batch_id' => $batch->id]) }}" class="text-teal-700 hover:text-teal-900 font-semibold transition">
                                                                    {{ __('catalogue.ledger') }}
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    @endforelse
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="{{ $canViewCost ? 8 : 7 }}" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="h-10 w-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                    <p class="mt-2 text-sm font-medium text-slate-500">{{ __('catalogue.no_stock_found') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($products->hasPages())
            <div class="px-5 py-3 border-t border-slate-200 bg-slate-50/50">
                {{ $products->links() }}
            </div>
        @endif
    </div>
</div>
