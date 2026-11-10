<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('catalogue.stock_on_hand_title') }}</h1>
            <p class="text-sm text-gray-500">{{ __('catalogue.stock_on_hand_subtitle') }}</p>
        </div>
        <div class="flex items-center gap-2">
            @if ($canExport)
                <a href="{{ route('inventory.stock.export', ['format' => 'csv']) }}?{{ $exportQuery }}"
                   class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition">
                    📄 {{ __('common.export_csv') }}
                </a>
                <a href="{{ route('inventory.stock.export', ['format' => 'pdf']) }}?{{ $exportQuery }}"
                   class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition">
                    📑 {{ __('common.export_pdf') }}
                </a>
            @endif
            <a href="{{ route('inventory.ledger') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md shadow-sm transition">
                📜 {{ __('catalogue.view_ledger') }}
            </a>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-700">{{ __('common.search') }}</label>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('catalogue.search_stock_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700">{{ __('catalogue.filter_status') }}</label>
            <select wire:model.live="filter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">{{ __('common.all') }}</option>
                <option value="low_stock">⚠️ {{ __('catalogue.filter_low_stock') }}</option>
                <option value="near_expiry">⏳ {{ __('catalogue.filter_near_expiry') }}</option>
                <option value="expired">🛑 {{ __('catalogue.filter_expired') }}</option>
                <option value="quarantine">🔒 {{ __('catalogue.filter_quarantine') }}</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700">{{ __('catalogue.category') }}</label>
            <select wire:model.live="category_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">{{ __('common.all') }}</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Products with Stocks Table -->
    <div class="bg-white shadow-sm border border-gray-200 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider font-semibold">
                    <tr>
                        <th scope="col" class="w-10 px-3 py-3"></th>
                        <th scope="col" class="px-4 py-3 text-left">{{ __('catalogue.product_name') }}</th>
                        <th scope="col" class="px-4 py-3 text-left">{{ __('catalogue.barcode') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('catalogue.sellable_stock') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('catalogue.quarantine_stock') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('catalogue.reorder_level') }}</th>
                        @if ($canViewCost)
                            <th scope="col" class="px-4 py-3 text-right">{{ __('catalogue.valuation') }}</th>
                        @endif
                        <th scope="col" class="px-4 py-3 text-center">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
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
                        <tr class="hover:bg-gray-50 transition {{ $isLow ? 'bg-amber-50/40' : '' }}">
                            <td class="px-3 py-3 text-center">
                                @if ($p->batches->isNotEmpty())
                                    <button wire:click="toggleExpand({{ $p->id }})" class="text-gray-400 hover:text-indigo-600 focus:outline-none">
                                        <svg class="h-5 w-5 transform transition-transform {{ $isExpanded ? 'rotate-90 text-indigo-600' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </button>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-900">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('catalogue.show', $p) }}" class="hover:underline text-indigo-600 font-semibold">{{ $p->brand }}</a>
                                    @if ($m['has_expired'])
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800" title="{{ __('catalogue.filter_expired') }}">🛑 Exp</span>
                                    @elseif ($m['has_near_expiry'])
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800" title="{{ __('catalogue.filter_near_expiry') }}">⏳ Soon</span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500">{{ $p->generic_name }} · {{ $p->dosage_form }} · {{ $p->strength }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-500 text-xs">
                                <div>{{ $p->barcode ?? '-' }}</div>
                                <div class="text-gray-400">{{ $p->category?->name ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3 text-right font-medium {{ $m['total_sellable'] > 0 ? 'text-gray-900 font-semibold' : 'text-gray-400' }}">
                                {{ number_format($m['total_sellable']) }}
                                @if ($isLow)
                                    <span class="ml-1 text-xs text-amber-600" title="{{ __('catalogue.low_stock_warning') }}">⚠️</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if ($m['total_quarantine'] > 0)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                        🔒 {{ number_format($m['total_quarantine']) }}
                                    </span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-xs text-gray-500">
                                {{ $p->reorder_level ? number_format($p->reorder_level) : '-' }}
                            </td>
                            @if ($canViewCost)
                                <td class="px-4 py-3 text-right font-mono text-gray-700">
                                    {{ $m['total_valuation'] !== null ? number_format($m['total_valuation'] / 100, 2) . ' ETB' : '-' }}
                                </td>
                            @endif
                            <td class="px-4 py-3 text-center whitespace-nowrap text-xs">
                                <a href="{{ route('inventory.ledger', ['product_id' => $p->id]) }}" class="text-indigo-600 hover:text-indigo-900 font-medium">
                                    {{ __('catalogue.ledger') }}
                                </a>
                            </td>
                        </tr>

                        <!-- Expanded Batches Sub-table -->
                        @if ($isExpanded)
                            <tr class="bg-gray-50/75">
                                <td colspan="{{ $canViewCost ? 8 : 7 }}" class="px-6 py-4">
                                    <div class="rounded-lg border border-gray-200 bg-white overflow-hidden shadow-xs">
                                        <div class="px-4 py-2 bg-gray-100/70 border-b border-gray-200 text-xs font-semibold text-gray-600 flex items-center justify-between">
                                            <span>{{ __('catalogue.batches_for_product', ['brand' => $p->brand]) }}</span>
                                            <span class="text-gray-400">{{ $p->batches->count() }} {{ __('catalogue.batches_count') }}</span>
                                        </div>
                                        <table class="min-w-full divide-y divide-gray-100 text-xs">
                                            <thead class="bg-gray-50 text-gray-500 uppercase">
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
                                            <tbody class="divide-y divide-gray-100">
                                                @foreach ($p->batches as $batch)
                                                    @php
                                                        $today = now()->format('Y-m-d');
                                                        $nearThreshold = now()->addDays((int)(setting('stock.near_expiry_days') ?? 90))->format('Y-m-d');
                                                        $bExp = $batch->expiry_date?->format('Y-m-d');
                                                        $isExpired = $bExp && $bExp <= $today;
                                                        $isNear = $bExp && $bExp > $today && $bExp <= $nearThreshold;
                                                    @endphp
                                                    @forelse ($batch->stocks as $stock)
                                                        <tr class="hover:bg-gray-50">
                                                            <td class="px-4 py-2 font-mono font-medium text-gray-900">
                                                                {{ $batch->batch_no }}
                                                            </td>
                                                            <td class="px-4 py-2 whitespace-nowrap">
                                                                <span>{{ $bExp ?? '-' }}</span>
                                                                @if ($isExpired)
                                                                    <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-700">🛑 EXPIRED</span>
                                                                @elseif ($isNear)
                                                                    <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700">⏳ NEAR</span>
                                                                @endif
                                                            </td>
                                                            <td class="px-4 py-2 text-gray-500">{{ $stock->location ?? '-' }}</td>
                                                            <td class="px-4 py-2">
                                                                @if ($stock->status === 'sellable')
                                                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800">Sellable</span>
                                                                @else
                                                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-red-100 text-red-800">Quarantine</span>
                                                                @endif
                                                            </td>
                                                            <td class="px-4 py-2 text-right font-medium text-gray-900">
                                                                {{ number_format($stock->quantity) }}
                                                            </td>
                                                            @if ($canViewCost)
                                                                <td class="px-4 py-2 text-right font-mono text-gray-600">
                                                                    {{ $batch->unit_cost !== null ? number_format($batch->unit_cost / 100, 2) . ' ETB' : '-' }}
                                                                </td>
                                                                <td class="px-4 py-2 text-right font-mono font-medium text-gray-900">
                                                                    {{ $batch->unit_cost !== null ? number_format(($stock->quantity * $batch->unit_cost) / 100, 2) . ' ETB' : '-' }}
                                                                </td>
                                                            @endif
                                                            <td class="px-4 py-2 text-center">
                                                                <a href="{{ route('inventory.ledger', ['batch_id' => $batch->id]) }}" class="text-indigo-600 hover:text-indigo-900">
                                                                    {{ __('catalogue.ledger') }}
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr class="hover:bg-gray-50 text-gray-400">
                                                            <td class="px-4 py-2 font-mono">{{ $batch->batch_no }}</td>
                                                            <td class="px-4 py-2">{{ $bExp ?? '-' }}</td>
                                                            <td class="px-4 py-2">-</td>
                                                            <td class="px-4 py-2"><span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-600">No stock</span></td>
                                                            <td class="px-4 py-2 text-right">0</td>
                                                            @if ($canViewCost)
                                                                <td class="px-4 py-2 text-right font-mono">{{ $batch->unit_cost !== null ? number_format($batch->unit_cost / 100, 2) . ' ETB' : '-' }}</td>
                                                                <td class="px-4 py-2 text-right font-mono">0.00 ETB</td>
                                                            @endif
                                                            <td class="px-4 py-2 text-center">
                                                                <a href="{{ route('inventory.ledger', ['batch_id' => $batch->id]) }}" class="text-indigo-600 hover:text-indigo-900">
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
                            <td colspan="{{ $canViewCost ? 8 : 7 }}" class="px-4 py-8 text-center text-gray-500">
                                {{ __('catalogue.no_stock_found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $products->links() }}
        </div>
    </div>
</div>

