<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('catalogue.stock_ledger_title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('catalogue.stock_ledger_subtitle') }}</p>
        </div>
        <div class="flex items-center gap-2.5">
            @if ($canExport)
                <a href="{{ route('inventory.ledger.export', ['format' => 'csv']) }}?{{ $exportQuery }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
                    <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    {{ __('common.export_csv') }}
                </a>
                <a href="{{ route('inventory.ledger.export', ['format' => 'pdf']) }}?{{ $exportQuery }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
                    <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    {{ __('common.export_pdf') }}
                </a>
            @endif
            <a href="{{ route('inventory.stock') }}" class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                {{ __('catalogue.view_stock_on_hand') }}
            </a>
        </div>
    </div>

    <!-- Active filter alert (if product or batch selected) -->
    @if ($selectedProduct || $selectedBatch)
        <div class="rounded-xl border border-teal-200 bg-teal-50/70 p-3.5 flex items-center justify-between text-sm text-teal-900 shadow-sm">
            <div class="flex items-center gap-2">
                <svg class="h-4 w-4 text-teal-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                <span>Filtered for:</span>
                @if ($selectedProduct)
                    <span class="font-bold">{{ $selectedProduct->brand }}</span>
                @endif
                @if ($selectedBatch)
                    <span>Batch <code class="font-mono bg-white px-2 py-0.5 rounded-lg border border-teal-200 text-teal-800 font-semibold">{{ $selectedBatch->batch_no }}</code></span>
                @endif
            </div>
            <a href="{{ route('inventory.ledger') }}" class="text-xs text-teal-700 underline hover:text-teal-900 font-semibold transition">
                {{ __('common.clear_filter') }}
            </a>
        </div>
    @endif

    <!-- Filters & Search -->
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('common.search') }}</label>
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('catalogue.search_ledger_placeholder') }}" class="block w-full rounded-lg border border-slate-300 py-2 pl-9 pr-3 text-sm placeholder:text-slate-400 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
            </div>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('catalogue.movement_type') }}</label>
            <select wire:model.live="type" class="block w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                <option value="">{{ __('common.all') }}</option>
                <option value="receipt">{{ __('catalogue.type_receipt') }}</option>
                <option value="sale">{{ __('catalogue.type_sale') }}</option>
                <option value="quarantine">{{ __('catalogue.type_quarantine') }}</option>
                <option value="adjustment">{{ __('catalogue.type_adjustment') }}</option>
                <option value="return">{{ __('catalogue.type_return') }}</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('common.from_date') }}</label>
            <input type="date" wire:model.live="from" class="block w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('common.to_date') }}</label>
            <input type="date" wire:model.live="to" class="block w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
        </div>
    </div>

    <!-- Ledger Movements Table -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50/75 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th scope="col" class="px-4 py-3.5 text-left">{{ __('catalogue.movement_time') }}</th>
                        <th scope="col" class="px-4 py-3.5 text-left">{{ __('catalogue.product_name') }}</th>
                        <th scope="col" class="px-4 py-3.5 text-left">{{ __('catalogue.batch_no') }}</th>
                        <th scope="col" class="px-4 py-3.5 text-center">{{ __('catalogue.movement_type') }}</th>
                        <th scope="col" class="px-4 py-3.5 text-right">{{ __('catalogue.quantity_change') }}</th>
                        <th scope="col" class="px-4 py-3.5 text-right">{{ __('catalogue.balance_after') }}</th>
                        <th scope="col" class="px-4 py-3.5 text-left">{{ __('catalogue.recorded_by') }}</th>
                        <th scope="col" class="px-4 py-3.5 text-left">{{ __('catalogue.source_doc') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($movements as $m)
                        @php
                            $sourceInfo = \App\Services\LedgerQuery::sourceInfo($m);
                            $isPositive = $m->quantity > 0;
                        @endphp
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="px-4 py-3.5 text-xs text-slate-500 whitespace-nowrap">
                                {{ local_time($m->created_at, 'Y-m-d H:i:s') }}
                            </td>
                            <td class="px-4 py-3.5 font-semibold text-slate-900">
                                <div>{{ $m->batch?->product?->brand ?? '-' }}</div>
                                <div class="text-xs text-slate-400 font-normal">{{ $m->batch?->product?->generic_name ?? '' }}</div>
                            </td>
                            <td class="px-4 py-3.5 text-xs font-mono text-slate-700 whitespace-nowrap">
                                <a href="{{ route('inventory.ledger', ['batch_id' => $m->batch_id]) }}" class="hover:underline text-teal-700 font-bold transition">
                                    {{ $m->batch?->batch_no ?? '-' }}
                                </a>
                            </td>
                            <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                @switch($m->type)
                                    @case('receipt')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20">📥 Receipt</span>
                                        @break
                                    @case('sale')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 ring-1 ring-blue-600/20">🛒 Sale</span>
                                        @break
                                    @case('quarantine')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 ring-1 ring-rose-600/20">🔒 Quarantine</span>
                                        @break
                                    @case('adjustment')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 ring-1 ring-amber-600/20">⚖️ Adjustment</span>
                                        @break
                                    @default
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 ring-1 ring-slate-500/20">{{ ucfirst($m->type) }}</span>
                                @endswitch
                            </td>
                            <td class="px-4 py-3.5 text-right font-mono font-bold whitespace-nowrap {{ $isPositive ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $isPositive ? '+' . number_format($m->quantity) : number_format($m->quantity) }}
                            </td>
                            <td class="px-4 py-3.5 text-right font-mono font-semibold text-slate-900 whitespace-nowrap">
                                {{ number_format($m->balance_after) }}
                            </td>
                            <td class="px-4 py-3.5 text-xs text-slate-600 whitespace-nowrap font-medium">
                                {{ $m->user?->username ?? '-' }}
                            </td>
                            <td class="px-4 py-3.5 text-xs whitespace-nowrap">
                                @if ($sourceInfo['url'])
                                    <a href="{{ $sourceInfo['url'] }}" class="text-teal-700 hover:text-teal-900 font-semibold underline transition">
                                        {{ $sourceInfo['label'] }}
                                    </a>
                                @else
                                    <span class="text-slate-600 font-medium">{{ $sourceInfo['label'] }}</span>
                                @endif
                                @if ($m->description)
                                    <div class="text-[10px] text-slate-400 truncate max-w-xs" title="{{ $m->description }}">{{ $m->description }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="h-10 w-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <p class="mt-2 text-sm font-medium text-slate-500">{{ __('catalogue.no_ledger_entries_found') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($movements->hasPages())
            <div class="px-5 py-3 border-t border-slate-200 bg-slate-50/50">
                {{ $movements->links() }}
            </div>
        @endif
    </div>
</div>
