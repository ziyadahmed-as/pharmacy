<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('catalogue.stock_ledger_title') }}</h1>
            <p class="text-sm text-gray-500">{{ __('catalogue.stock_ledger_subtitle') }}</p>
        </div>
        <div class="flex items-center gap-2">
            @if ($canExport)
                <a href="{{ route('inventory.ledger.export', ['format' => 'csv']) }}?{{ $exportQuery }}"
                   class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition">
                    📄 {{ __('common.export_csv') }}
                </a>
                <a href="{{ route('inventory.ledger.export', ['format' => 'pdf']) }}?{{ $exportQuery }}"
                   class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition">
                    📑 {{ __('common.export_pdf') }}
                </a>
            @endif
            <a href="{{ route('inventory.stock') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-md shadow-sm transition">
                📦 {{ __('catalogue.view_stock_on_hand') }}
            </a>
        </div>
    </div>

    <!-- Active filter alert (if product or batch selected) -->
    @if ($selectedProduct || $selectedBatch)
        <div class="bg-indigo-50 border border-indigo-200 rounded-md p-3 flex items-center justify-between text-sm text-indigo-900">
            <div class="flex items-center gap-2">
                <span>🔍 Filtered for:</span>
                @if ($selectedProduct)
                    <span class="font-semibold">{{ $selectedProduct->brand }}</span>
                @endif
                @if ($selectedBatch)
                    <span>Batch <code class="font-mono bg-white px-1.5 py-0.5 rounded border border-indigo-200">{{ $selectedBatch->batch_no }}</code></span>
                @endif
            </div>
            <a href="{{ route('inventory.ledger') }}" class="text-xs text-indigo-700 underline hover:text-indigo-900 font-medium">
                {{ __('common.clear_filter') }}
            </a>
        </div>
    @endif

    <!-- Filters & Search -->
    <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-700">{{ __('common.search') }}</label>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('catalogue.search_ledger_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700">{{ __('catalogue.movement_type') }}</label>
            <select wire:model.live="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">{{ __('common.all') }}</option>
                <option value="receipt">{{ __('catalogue.type_receipt') }}</option>
                <option value="sale">{{ __('catalogue.type_sale') }}</option>
                <option value="quarantine">{{ __('catalogue.type_quarantine') }}</option>
                <option value="adjustment">{{ __('catalogue.type_adjustment') }}</option>
                <option value="return">{{ __('catalogue.type_return') }}</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700">{{ __('common.from_date') }}</label>
            <input type="date" wire:model.live="from" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700">{{ __('common.to_date') }}</label>
            <input type="date" wire:model.live="to" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
    </div>

    <!-- Ledger Movements Table -->
    <div class="bg-white shadow-sm border border-gray-200 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider font-semibold">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left">{{ __('catalogue.movement_time') }}</th>
                        <th scope="col" class="px-4 py-3 text-left">{{ __('catalogue.product_name') }}</th>
                        <th scope="col" class="px-4 py-3 text-left">{{ __('catalogue.batch_no') }}</th>
                        <th scope="col" class="px-4 py-3 text-center">{{ __('catalogue.movement_type') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('catalogue.quantity_change') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('catalogue.balance_after') }}</th>
                        <th scope="col" class="px-4 py-3 text-left">{{ __('catalogue.recorded_by') }}</th>
                        <th scope="col" class="px-4 py-3 text-left">{{ __('catalogue.source_doc') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse ($movements as $m)
                        @php
                            $sourceInfo = \App\Services\LedgerQuery::sourceInfo($m);
                            $isPositive = $m->quantity > 0;
                        @endphp
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                {{ local_time($m->created_at, 'Y-m-d H:i:s') }}
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-900">
                                <div>{{ $m->batch?->product?->brand ?? '-' }}</div>
                                <div class="text-xs text-gray-400">{{ $m->batch?->product?->generic_name ?? '' }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs font-mono text-gray-700 whitespace-nowrap">
                                <a href="{{ route('inventory.ledger', ['batch_id' => $m->batch_id]) }}" class="hover:underline text-indigo-600 font-semibold">
                                    {{ $m->batch?->batch_no ?? '-' }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @switch($m->type)
                                    @case('receipt')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800">📥 Receipt</span>
                                        @break
                                    @case('sale')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">🛒 Sale</span>
                                        @break
                                    @case('quarantine')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">🔒 Quarantine</span>
                                        @break
                                    @case('adjustment')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">⚖️ Adjustment</span>
                                        @break
                                    @default
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">{{ ucfirst($m->type) }}</span>
                                @endswitch
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-semibold whitespace-nowrap {{ $isPositive ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $isPositive ? '+' . number_format($m->quantity) : number_format($m->quantity) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-medium text-gray-900 whitespace-nowrap">
                                {{ number_format($m->balance_after) }}
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">
                                {{ $m->user?->username ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-xs whitespace-nowrap">
                                @if ($sourceInfo['url'])
                                    <a href="{{ $sourceInfo['url'] }}" class="text-indigo-600 hover:text-indigo-900 font-medium underline">
                                        {{ $sourceInfo['label'] }}
                                    </a>
                                @else
                                    <span class="text-gray-600">{{ $sourceInfo['label'] }}</span>
                                @endif
                                @if ($m->description)
                                    <div class="text-[10px] text-gray-400 truncate max-w-xs" title="{{ $m->description }}">{{ $m->description }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                {{ __('catalogue.no_ledger_entries_found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $movements->links() }}
        </div>
    </div>
</div>

