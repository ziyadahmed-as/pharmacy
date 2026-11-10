<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('catalogue.alerts_title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('catalogue.alerts_subtitle') }}</p>
        </div>
        @if ($tab === 'reorder')
            <div class="flex items-center gap-2.5">
                <a href="{{ route('inventory.reorder.export', ['format' => 'csv']) }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
                    <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    {{ __('common.export_csv') }}
                </a>
                <a href="{{ route('inventory.reorder.export', ['format' => 'pdf']) }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
                    <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    {{ __('common.export_pdf') }}
                </a>
                <button onclick="window.print()" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
                    <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    {{ __('catalogue.print_reorder_list') }}
                </button>
            </div>
        @endif
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-slate-200">
        <nav class="-mb-px flex space-x-6">
            <button wire:click="setTab('alerts')"
                    class="py-3 px-1 border-b-2 font-semibold text-sm flex items-center gap-2 transition {{ $tab === 'alerts' ? 'border-teal-600 text-teal-700' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
                <span>🔔 {{ __('catalogue.tab_active_alerts') }}</span>
                @if ($counts['total'] > 0)
                    <span class="px-2 py-0.5 text-xs rounded-full font-bold bg-rose-100 text-rose-800">{{ $counts['total'] }}</span>
                @endif
            </button>
            <button wire:click="setTab('reorder')"
                    class="py-3 px-1 border-b-2 font-semibold text-sm flex items-center gap-2 transition {{ $tab === 'reorder' ? 'border-teal-600 text-teal-700' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
                <span>📋 {{ __('catalogue.tab_reorder_list') }}</span>
            </button>
            <button wire:click="setTab('near_expiry')"
                    class="py-3 px-1 border-b-2 font-semibold text-sm flex items-center gap-2 transition {{ $tab === 'near_expiry' ? 'border-teal-600 text-teal-700' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
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
                            class="px-3 py-1.5 rounded-xl font-semibold transition {{ $type === '' ? 'bg-teal-600 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                        {{ __('common.all') }} ({{ $counts['total'] }})
                    </button>
                    <button wire:click="$set('type', 'critical_stock')"
                            class="px-3 py-1.5 rounded-xl font-semibold transition {{ $type === 'critical_stock' ? 'bg-rose-600 text-white shadow-sm' : 'bg-rose-50 text-rose-700 hover:bg-rose-100' }}">
                        🚨 Critical Stock ({{ $counts['critical'] }})
                    </button>
                    <button wire:click="$set('type', 'low_stock')"
                            class="px-3 py-1.5 rounded-xl font-semibold transition {{ $type === 'low_stock' ? 'bg-amber-600 text-white shadow-sm' : 'bg-amber-50 text-amber-700 hover:bg-amber-100' }}">
                        ⚠️ Low Stock ({{ $counts['low'] }})
                    </button>
                    <button wire:click="$set('type', 'near_expiry')"
                            class="px-3 py-1.5 rounded-xl font-semibold transition {{ $type === 'near_expiry' ? 'bg-amber-500 text-white shadow-sm' : 'bg-amber-50 text-amber-700 hover:bg-amber-100' }}">
                        ⏳ Near Expiry ({{ $counts['near_expiry'] }})
                    </button>
                    <button wire:click="$set('type', 'expired')"
                            class="px-3 py-1.5 rounded-xl font-semibold transition {{ $type === 'expired' ? 'bg-rose-700 text-white shadow-sm' : 'bg-rose-50 text-rose-800 hover:bg-rose-100' }}">
                        🛑 Expired ({{ $counts['expired'] }})
                    </button>
                </div>

                @if ($canManage && $counts['total'] > 0)
                    <button wire:click="markAllSeen" class="text-xs text-teal-700 hover:text-teal-900 font-semibold underline transition">
                        ✓ {{ __('catalogue.mark_all_seen') }}
                    </button>
                @endif
            </div>

            <!-- Alerts Table -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50/75 text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-3.5 text-left">{{ __('common.status') }}</th>
                                <th scope="col" class="px-4 py-3.5 text-left">{{ __('catalogue.product_name') }}</th>
                                <th scope="col" class="px-4 py-3.5 text-left">{{ __('catalogue.alert_message') }}</th>
                                <th scope="col" class="px-4 py-3.5 text-right">{{ __('catalogue.current_stock') }}</th>
                                <th scope="col" class="px-4 py-3.5 text-left">{{ __('common.time') }}</th>
                                @if ($canManage)
                                    <th scope="col" class="px-4 py-3.5 text-center">{{ __('common.actions') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @forelse ($alerts as $alert)
                                <tr class="hover:bg-slate-50/70 transition {{ $alert->status === 'active' ? 'bg-amber-50/20 font-medium' : 'text-slate-500' }}">
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        @switch($alert->type)
                                            @case('critical_stock')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-rose-50 text-rose-700 ring-1 ring-rose-600/20">🚨 Critical</span>
                                                @break
                                            @case('low_stock')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 ring-1 ring-amber-600/20">⚠️ Low Stock</span>
                                                @break
                                            @case('near_expiry')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 ring-1 ring-amber-600/20">⏳ Near Expiry</span>
                                                @break
                                            @case('expired')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-rose-50 text-rose-700 ring-1 ring-rose-600/20">🛑 Expired</span>
                                                @break
                                        @endswitch
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('catalogue.show', $alert->product) }}" class="text-teal-700 hover:text-teal-900 font-bold transition">
                                                {{ $alert->product->brand }}
                                            </a>
                                            @if ($alert->batch)
                                                <a href="{{ route('inventory.ledger', ['batch_id' => $alert->batch_id]) }}" class="text-xs font-mono text-slate-500 hover:text-teal-700 transition">
                                                    [{{ $alert->batch->batch_no }}]
                                                </a>
                                            @endif
                                        </div>
                                        <div class="text-xs text-slate-400 font-normal">{{ $alert->product->generic_name }}</div>
                                    </td>
                                    <td class="px-4 py-3.5 text-xs text-slate-700">
                                        {{ $alert->message }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-mono font-semibold text-slate-900 whitespace-nowrap">
                                        {{ number_format($alert->current_quantity) }}
                                    </td>
                                    <td class="px-4 py-3.5 text-xs text-slate-500 whitespace-nowrap">
                                        {{ local_time($alert->created_at, 'Y-m-d H:i') }}
                                    </td>
                                    @if ($canManage)
                                        <td class="px-4 py-3.5 text-center whitespace-nowrap text-xs">
                                            @if ($alert->status === 'active')
                                                <button wire:click="markSeen({{ $alert->id }})" class="text-teal-700 hover:text-teal-900 font-semibold transition">
                                                    ✓ {{ __('catalogue.mark_seen') }}
                                                </button>
                                            @else
                                                <span class="text-slate-400 text-xs">{{ __('catalogue.seen') }}</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canManage ? 6 : 5 }}" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center justify-center">
                                            <svg class="h-10 w-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                            <p class="mt-2 text-sm font-medium text-slate-500">{{ __('catalogue.no_active_alerts') }}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($alerts->hasPages())
                    <div class="px-5 py-3 border-t border-slate-200 bg-slate-50/50">
                        {{ $alerts->links() }}
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- TAB 2: REORDER LIST -->
    @if ($tab === 'reorder')
        <div class="space-y-4">
            <!-- Warning Banner -->
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 flex items-start gap-3 shadow-sm">
                <svg class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <div>
                    <h3 class="font-bold">{{ __('catalogue.reorder_external_warning_title') }}</h3>
                    <p class="text-xs text-amber-800 mt-0.5">{{ __('catalogue.reorder_external_warning_body') }}</p>
                </div>
            </div>

            <!-- Reorder List Table -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50/75 text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-3.5 text-left">{{ __('catalogue.product_name') }}</th>
                                <th scope="col" class="px-4 py-3.5 text-left">{{ __('catalogue.preferred_supplier') }}</th>
                                <th scope="col" class="px-4 py-3.5 text-right">{{ __('catalogue.on_hand') }}</th>
                                <th scope="col" class="px-4 py-3.5 text-right">{{ __('catalogue.reorder_level') }}</th>
                                <th scope="col" class="px-4 py-3.5 text-right">{{ __('catalogue.max_level') }}</th>
                                <th scope="col" class="px-4 py-3.5 text-right font-bold text-teal-700">{{ __('catalogue.suggested_order_qty') }}</th>
                                <th scope="col" class="px-4 py-3.5 text-center">{{ __('common.status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @forelse ($reorderItems as $item)
                                <tr class="hover:bg-slate-50/70 transition {{ $item['is_critical'] ? 'bg-rose-50/30' : '' }}">
                                    <td class="px-4 py-3.5 font-semibold text-slate-900">
                                        <a href="{{ route('catalogue.show', $item['product']) }}" class="text-teal-700 hover:text-teal-900 transition font-bold">
                                            {{ $item['product']->brand }}
                                        </a>
                                        <div class="text-xs text-slate-400 font-normal">{{ $item['product']->generic_name }} · {{ $item['product']->dosage_form }}</div>
                                    </td>
                                    <td class="px-4 py-3.5 text-xs text-slate-700">
                                        {{ $item['preferred_supplier'] }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-mono font-bold {{ $item['is_critical'] ? 'text-rose-700' : 'text-amber-700' }}">
                                        {{ number_format($item['on_hand']) }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right text-xs text-slate-500">
                                        {{ number_format($item['reorder_level']) }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right text-xs text-slate-500">
                                        {{ $item['max_level'] !== null ? number_format($item['max_level']) : '-' }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-mono font-extrabold text-teal-700 text-base">
                                        {{ number_format($item['suggested_qty']) }}
                                    </td>
                                    <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                        @if ($item['is_critical'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-rose-50 text-rose-700 ring-1 ring-rose-600/20">
                                                🚨 {{ __('catalogue.status_critical') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 ring-1 ring-amber-600/20">
                                                ⚠️ {{ __('catalogue.status_low') }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-slate-400 italic">
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
            <div class="flex items-center gap-2.5 rounded-xl border border-teal-200 bg-teal-50/70 p-3.5 text-xs text-teal-900 shadow-sm">
                <svg class="h-4 w-4 text-teal-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ __('catalogue.near_expiry_decision_help') }}</span>
            </div>

            <!-- Grouped by Window -->
            @php
                $sections = [
                    'expired' => ['title' => '🛑 ' . __('catalogue.window_expired_title'), 'color' => 'border-rose-200 bg-rose-50/40', 'badge' => 'bg-rose-100 text-rose-800'],
                    '0_30' => ['title' => '🚨 ' . __('catalogue.window_0_30_title'), 'color' => 'border-rose-200 bg-rose-50/20', 'badge' => 'bg-rose-100 text-rose-800'],
                    '31_60' => ['title' => '⚠️ ' . __('catalogue.window_31_60_title'), 'color' => 'border-amber-200 bg-amber-50/20', 'badge' => 'bg-amber-100 text-amber-800'],
                    '61_90' => ['title' => '⏳ ' . __('catalogue.window_61_90_title'), 'color' => 'border-amber-200 bg-amber-50/10', 'badge' => 'bg-amber-100 text-amber-800'],
                ];
            @endphp

            @foreach ($sections as $key => $sec)
                @php $items = $nearExpiryBatches->get($key, collect()); @endphp
                @if ($items->isNotEmpty())
                    <div class="rounded-2xl border {{ $sec['color'] }} p-5 space-y-3.5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <h2 class="font-bold text-slate-900 text-sm flex items-center gap-2">
                                <span>{{ $sec['title'] }}</span>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $sec['badge'] }}">{{ $items->count() }} batches</span>
                            </h2>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white overflow-hidden shadow-xs">
                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                <thead class="bg-slate-50/75 text-slate-500 uppercase tracking-wider font-semibold">
                                    <tr>
                                        <th class="px-4 py-2.5 text-left">{{ __('catalogue.product_name') }}</th>
                                        <th class="px-4 py-2.5 text-left">{{ __('catalogue.batch_no') }}</th>
                                        <th class="px-4 py-2.5 text-left">{{ __('catalogue.expiry_date') }}</th>
                                        <th class="px-4 py-2.5 text-right">{{ __('catalogue.sellable_stock') }}</th>
                                        <th class="px-4 py-2.5 text-left font-semibold text-teal-800">{{ __('catalogue.decision_options') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($items as $item)
                                        @php
                                            $b = $item['batch'];
                                            $days = $item['days_remaining'];
                                        @endphp
                                        <tr class="hover:bg-slate-50/60 transition">
                                            <td class="px-4 py-2.5 font-semibold text-slate-900">
                                                <a href="{{ route('catalogue.show', $b->product) }}" class="text-teal-700 hover:text-teal-900 font-bold transition">{{ $b->product->brand }}</a>
                                                <span class="text-slate-400 font-normal">· {{ $b->product->generic_name }}</span>
                                            </td>
                                            <td class="px-4 py-2.5 font-mono text-slate-700 font-medium">
                                                <a href="{{ route('inventory.ledger', ['batch_id' => $b->id]) }}" class="hover:underline text-teal-700 transition">{{ $b->batch_no }}</a>
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap">
                                                <span class="font-medium text-slate-800">{{ $b->expiry_date->format('Y-m-d') }}</span>
                                                <span class="ml-1 text-[10px] text-slate-500">({{ $days > 0 ? $days . ' days left' : 'Expired' }})</span>
                                            </td>
                                            <td class="px-4 py-2.5 text-right font-mono font-bold text-slate-900">
                                                {{ number_format($item['sellable_qty']) }}
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <div class="flex items-center gap-2">
                                                    @if ($key === 'expired')
                                                        <span class="text-rose-700 font-semibold" title="UC-08">🗑️ {{ __('catalogue.action_prepare_disposal') }}</span>
                                                    @else
                                                        <span class="text-emerald-700 font-semibold" title="FEFO dispensing in POS">⚡ {{ __('catalogue.action_fefo_priority') }}</span>
                                                        <span class="text-slate-300">|</span>
                                                        <span class="text-teal-700 font-semibold" title="UC-07">🔄 {{ __('catalogue.action_return_supplier') }}</span>
                                                        @if ($key === '0_30')
                                                            <span class="text-slate-300">|</span>
                                                            <span class="text-rose-700 font-semibold" title="UC-08">🗑️ {{ __('catalogue.action_prepare_disposal') }}</span>
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
