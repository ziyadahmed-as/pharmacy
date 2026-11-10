<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('catalogue.alerts_title') }}</h1>
            <p class="text-sm text-gray-500">{{ __('catalogue.alerts_subtitle') }}</p>
        </div>
        @if ($tab === 'reorder')
            <div class="flex items-center gap-2">
                <a href="{{ route('inventory.reorder.export', ['format' => 'csv']) }}"
                   class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition">
                    📄 {{ __('common.export_csv') }}
                </a>
                <a href="{{ route('inventory.reorder.export', ['format' => 'pdf']) }}"
                   class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition">
                    📑 {{ __('common.export_pdf') }}
                </a>
                <button onclick="window.print()" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition">
                    🖨️ {{ __('catalogue.print_reorder_list') }}
                </button>
            </div>
        @endif
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex space-x-6">
            <button wire:click="setTab('alerts')"
                    class="py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 {{ $tab === 'alerts' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                <span>🔔 {{ __('catalogue.tab_active_alerts') }}</span>
                @if ($counts['total'] > 0)
                    <span class="px-2 py-0.5 text-xs rounded-full font-semibold bg-red-100 text-red-800">{{ $counts['total'] }}</span>
                @endif
            </button>
            <button wire:click="setTab('reorder')"
                    class="py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 {{ $tab === 'reorder' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                <span>📋 {{ __('catalogue.tab_reorder_list') }}</span>
            </button>
            <button wire:click="setTab('near_expiry')"
                    class="py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 {{ $tab === 'near_expiry' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                <span>⏳ {{ __('catalogue.tab_near_expiry_panel') }}</span>
            </button>
        </nav>
    </div>

    <!-- TAB 1: ALERTS & NOTIFICATIONS -->
    @if ($tab === 'alerts')
        <div class="space-y-4">
            <!-- Filter Pills & Mark All Seen -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <button wire:click="$set('type', '')"
                            class="px-3 py-1.5 rounded-full font-medium transition {{ $type === '' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        {{ __('common.all') }} ({{ $counts['total'] }})
                    </button>
                    <button wire:click="$set('type', 'critical_stock')"
                            class="px-3 py-1.5 rounded-full font-medium transition {{ $type === 'critical_stock' ? 'bg-red-600 text-white' : 'bg-red-50 text-red-700 hover:bg-red-100' }}">
                        🚨 Critical Stock ({{ $counts['critical'] }})
                    </button>
                    <button wire:click="$set('type', 'low_stock')"
                            class="px-3 py-1.5 rounded-full font-medium transition {{ $type === 'low_stock' ? 'bg-amber-600 text-white' : 'bg-amber-50 text-amber-700 hover:bg-amber-100' }}">
                        ⚠️ Low Stock ({{ $counts['low'] }})
                    </button>
                    <button wire:click="$set('type', 'near_expiry')"
                            class="px-3 py-1.5 rounded-full font-medium transition {{ $type === 'near_expiry' ? 'bg-yellow-600 text-white' : 'bg-yellow-50 text-yellow-700 hover:bg-yellow-100' }}">
                        ⏳ Near Expiry ({{ $counts['near_expiry'] }})
                    </button>
                    <button wire:click="$set('type', 'expired')"
                            class="px-3 py-1.5 rounded-full font-medium transition {{ $type === 'expired' ? 'bg-rose-700 text-white' : 'bg-rose-50 text-rose-800 hover:bg-rose-100' }}">
                        🛑 Expired ({{ $counts['expired'] }})
                    </button>
                </div>

                @if ($canManage && $counts['total'] > 0)
                    <button wire:click="markAllSeen" class="text-xs text-indigo-600 hover:text-indigo-900 font-semibold underline">
                        ✓ {{ __('catalogue.mark_all_seen') }}
                    </button>
                @endif
            </div>

            <!-- Alerts Table -->
            <div class="bg-white shadow-sm border border-gray-200 rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider font-semibold">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left">{{ __('common.status') }}</th>
                                <th scope="col" class="px-4 py-3 text-left">{{ __('catalogue.product_name') }}</th>
                                <th scope="col" class="px-4 py-3 text-left">{{ __('catalogue.alert_message') }}</th>
                                <th scope="col" class="px-4 py-3 text-right">{{ __('catalogue.current_stock') }}</th>
                                <th scope="col" class="px-4 py-3 text-left">{{ __('common.time') }}</th>
                                @if ($canManage)
                                    <th scope="col" class="px-4 py-3 text-center">{{ __('common.actions') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($alerts as $alert)
                                <tr class="hover:bg-gray-50 transition {{ $alert->status === 'active' ? 'bg-amber-50/20 font-medium' : 'text-gray-500' }}">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @switch($alert->type)
                                            @case('critical_stock')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-red-100 text-red-800">🚨 Critical</span>
                                                @break
                                            @case('low_stock')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800">⚠️ Low Stock</span>
                                                @break
                                            @case('near_expiry')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-yellow-100 text-yellow-800">⏳ Near Expiry</span>
                                                @break
                                            @case('expired')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-rose-100 text-rose-800">🛑 Expired</span>
                                                @break
                                        @endswitch
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('catalogue.show', $alert->product) }}" class="text-indigo-600 hover:underline font-semibold">
                                                {{ $alert->product->brand }}
                                            </a>
                                            @if ($alert->batch)
                                                <a href="{{ route('inventory.ledger', ['batch_id' => $alert->batch_id]) }}" class="text-xs font-mono text-gray-500 hover:text-indigo-600">
                                                    [{{ $alert->batch->batch_no }}]
                                                </a>
                                            @endif
                                        </div>
                                        <div class="text-xs text-gray-400">{{ $alert->product->generic_name }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-800">
                                        {{ $alert->message }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-medium text-gray-900 whitespace-nowrap">
                                        {{ number_format($alert->current_quantity) }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                        {{ local_time($alert->created_at, 'Y-m-d H:i') }}
                                    </td>
                                    @if ($canManage)
                                        <td class="px-4 py-3 text-center whitespace-nowrap text-xs">
                                            @if ($alert->status === 'active')
                                                <button wire:click="markSeen({{ $alert->id }})" class="text-indigo-600 hover:text-indigo-900 font-medium">
                                                    ✓ {{ __('catalogue.mark_seen') }}
                                                </button>
                                            @else
                                                <span class="text-gray-400 text-[11px]">{{ __('catalogue.seen') }}</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canManage ? 6 : 5 }}" class="px-4 py-8 text-center text-gray-500">
                                        {{ __('catalogue.no_active_alerts') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $alerts->links() }}
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 2: REORDER LIST -->
    @if ($tab === 'reorder')
        <div class="space-y-4">
            <!-- Warning Banner (Orders placed outside PMS) -->
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 flex items-start gap-3">
                <span class="text-xl">⚠️</span>
                <div>
                    <h3 class="font-bold">{{ __('catalogue.reorder_external_warning_title') }}</h3>
                    <p class="text-xs text-amber-800 mt-0.5">{{ __('catalogue.reorder_external_warning_body') }}</p>
                </div>
            </div>

            <!-- Reorder List Table -->
            <div class="bg-white shadow-sm border border-gray-200 rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider font-semibold">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left">{{ __('catalogue.product_name') }}</th>
                                <th scope="col" class="px-4 py-3 text-left">{{ __('catalogue.preferred_supplier') }}</th>
                                <th scope="col" class="px-4 py-3 text-right">{{ __('catalogue.on_hand') }}</th>
                                <th scope="col" class="px-4 py-3 text-right">{{ __('catalogue.reorder_level') }}</th>
                                <th scope="col" class="px-4 py-3 text-right">{{ __('catalogue.max_level') }}</th>
                                <th scope="col" class="px-4 py-3 text-right font-bold text-indigo-700">{{ __('catalogue.suggested_order_qty') }}</th>
                                <th scope="col" class="px-4 py-3 text-center">{{ __('common.status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($reorderItems as $item)
                                <tr class="hover:bg-gray-50 transition {{ $item['is_critical'] ? 'bg-red-50/30' : '' }}">
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        <a href="{{ route('catalogue.show', $item['product']) }}" class="text-indigo-600 hover:underline font-semibold">
                                            {{ $item['product']->brand }}
                                        </a>
                                        <div class="text-xs text-gray-400">{{ $item['product']->generic_name }} · {{ $item['product']->dosage_form }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-700">
                                        {{ $item['preferred_supplier'] }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-bold {{ $item['is_critical'] ? 'text-red-700' : 'text-amber-700' }}">
                                        {{ number_format($item['on_hand']) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-xs text-gray-500">
                                        {{ number_format($item['reorder_level']) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-xs text-gray-500">
                                        {{ $item['max_level'] !== null ? number_format($item['max_level']) : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-indigo-700 text-base">
                                        {{ number_format($item['suggested_qty']) }}
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        @if ($item['is_critical'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-red-100 text-red-800">
                                                🚨 {{ __('catalogue.status_critical') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800">
                                                ⚠️ {{ __('catalogue.status_low') }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                        {{ __('catalogue.reorder_list_empty') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 3: NEAR-EXPIRY DECISION PANEL -->
    @if ($tab === 'near_expiry')
        <div class="space-y-6">
            <div class="text-xs text-gray-500 bg-blue-50 border border-blue-200 p-3 rounded-md">
                ℹ️ {{ __('catalogue.near_expiry_decision_help') }}
            </div>

            <!-- Grouped by Window -->
            @php
                $sections = [
                    'expired' => ['title' => '🛑 ' . __('catalogue.window_expired_title'), 'color' => 'border-rose-300 bg-rose-50/50', 'badge' => 'bg-rose-100 text-rose-800'],
                    '0_30' => ['title' => '🚨 ' . __('catalogue.window_0_30_title'), 'color' => 'border-red-300 bg-red-50/30', 'badge' => 'bg-red-100 text-red-800'],
                    '31_60' => ['title' => '⚠️ ' . __('catalogue.window_31_60_title'), 'color' => 'border-amber-300 bg-amber-50/20', 'badge' => 'bg-amber-100 text-amber-800'],
                    '61_90' => ['title' => '⏳ ' . __('catalogue.window_61_90_title'), 'color' => 'border-yellow-300 bg-yellow-50/10', 'badge' => 'bg-yellow-100 text-yellow-800'],
                ];
            @endphp

            @foreach ($sections as $key => $sec)
                @php $items = $nearExpiryBatches->get($key, collect()); @endphp
                @if ($items->isNotEmpty())
                    <div class="rounded-lg border {{ $sec['color'] }} p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <h2 class="font-bold text-gray-900 text-sm flex items-center gap-2">
                                <span>{{ $sec['title'] }}</span>
                                <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $sec['badge'] }}">{{ $items->count() }} batches</span>
                            </h2>
                        </div>
                        <div class="bg-white rounded-md border border-gray-200 overflow-hidden shadow-xs">
                            <table class="min-w-full divide-y divide-gray-200 text-xs">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr>
                                        <th class="px-4 py-2 text-left">{{ __('catalogue.product_name') }}</th>
                                        <th class="px-4 py-2 text-left">{{ __('catalogue.batch_no') }}</th>
                                        <th class="px-4 py-2 text-left">{{ __('catalogue.expiry_date') }}</th>
                                        <th class="px-4 py-2 text-right">{{ __('catalogue.sellable_stock') }}</th>
                                        <th class="px-4 py-2 text-left font-semibold text-indigo-700">{{ __('catalogue.decision_options') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($items as $item)
                                        @php
                                            $b = $item['batch'];
                                            $days = $item['days_remaining'];
                                        @endphp
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-medium text-gray-900">
                                                <a href="{{ route('catalogue.show', $b->product) }}" class="hover:underline text-indigo-600 font-semibold">{{ $b->product->brand }}</a>
                                                <span class="text-gray-400">· {{ $b->product->generic_name }}</span>
                                            </td>
                                            <td class="px-4 py-2 font-mono text-gray-700">
                                                <a href="{{ route('inventory.ledger', ['batch_id' => $b->id]) }}" class="hover:underline">{{ $b->batch_no }}</a>
                                            </td>
                                            <td class="px-4 py-2 whitespace-nowrap">
                                                <span>{{ $b->expiry_date->format('Y-m-d') }}</span>
                                                <span class="ml-1 text-[10px] text-gray-500">({{ $days > 0 ? $days . ' days left' : 'Expired' }})</span>
                                            </td>
                                            <td class="px-4 py-2 text-right font-mono font-medium text-gray-900">
                                                {{ number_format($item['sellable_qty']) }}
                                            </td>
                                            <td class="px-4 py-2">
                                                <div class="flex items-center gap-2">
                                                    @if ($key === 'expired')
                                                        <span class="text-rose-700 font-semibold" title="UC-08">🗑️ {{ __('catalogue.action_prepare_disposal') }}</span>
                                                    @else
                                                        <span class="text-emerald-700 font-medium" title="FEFO dispensing in POS">⚡ {{ __('catalogue.action_fefo_priority') }}</span>
                                                        <span class="text-gray-300">|</span>
                                                        <span class="text-indigo-700 font-medium" title="UC-07">🔄 {{ __('catalogue.action_return_supplier') }}</span>
                                                        @if ($key === '0_30')
                                                            <span class="text-gray-300">|</span>
                                                            <span class="text-rose-700 font-medium" title="UC-08">🗑️ {{ __('catalogue.action_prepare_disposal') }}</span>
                                                        @endif
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</div>

