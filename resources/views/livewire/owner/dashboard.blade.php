<div class="space-y-6 pb-12" wire:poll.60s="render">
    {{-- Top Command Bar: Header & Actions --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-5">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">{{ __('dashboard.owner.title') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-teal-100 text-teal-800 dark:bg-teal-900/60 dark:text-teal-300">
                    {{ __('dashboard.owner.live_command_centre') }}
                </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-2">
                <span>{{ __('dashboard.owner.subtitle') }}</span>
                <span>·</span>
                <span>{{ $data['period_label'] }}</span>
                <span>·</span>
                <span>{{ __('dashboard.owner.last_updated', ['time' => $data['generated_at']]) }}</span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 text-xs font-semibold">
            {{-- Refresh Button --}}
            <button
                type="button"
                wire:click="refreshDashboard"
                wire:loading.attr="disabled"
                class="inline-flex items-center px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm bg-white dark:bg-slate-800 hover:bg-slate-50 text-slate-700 dark:text-slate-300 transition">
                <svg class="h-3.5 w-3.5 mr-1.5 text-teal-600" wire:loading.class="animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                {{ __('dashboard.owner.btn_refresh') }}
            </button>

            {{-- Print Summary --}}
            <button
                type="button"
                onclick="window.print()"
                class="inline-flex items-center px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm bg-white dark:bg-slate-800 hover:bg-slate-50 text-slate-700 dark:text-slate-300 transition">
                <svg class="h-3.5 w-3.5 mr-1.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                {{ __('dashboard.owner.btn_print_summary') }}
            </button>

            {{-- Dispatch SMS / Email Daily Summary --}}
            <button
                type="button"
                wire:click="sendSummary('sms')"
                class="inline-flex items-center px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm bg-white dark:bg-slate-800 hover:bg-slate-50 text-slate-700 dark:text-slate-300 transition">
                <svg class="h-3.5 w-3.5 mr-1.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                {{ __('dashboard.owner.send_daily_summary_btn') }}
            </button>

            <a href="{{ route('reports.index') }}" class="inline-flex items-center px-3.5 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-xl shadow-sm transition">
                {{ __('dashboard.owner.all_reports') }} &rarr;
            </a>
        </div>
    </div>

    {{-- Feedback Alerts --}}
    @if ($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 dark:bg-emerald-950/40 p-3 text-xs font-semibold text-emerald-800 dark:text-emerald-300 flex items-center justify-between shadow-sm">
            <span>✓ {{ $statusMessage }}</span>
            <button wire:click="$set('statusMessage', null)" class="text-emerald-600 hover:text-emerald-800">✕</button>
        </div>
    @endif

    @if ($errorMessage)
        <div class="rounded-xl border border-rose-200 bg-rose-50 dark:bg-rose-950/40 p-3 text-xs font-semibold text-rose-800 dark:text-rose-300 flex items-center justify-between shadow-sm">
            <span>⚠️ {{ $errorMessage }}</span>
            <button wire:click="$set('errorMessage', null)" class="text-rose-600 hover:text-rose-800">✕</button>
        </div>
    @endif

    {{-- STEP 1: Command Header Filters Bar --}}
    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm space-y-3">
        <div class="flex flex-wrap items-center gap-3">
            {{-- Period Presets --}}
            <div class="flex flex-wrap items-center gap-1.5 bg-slate-100 dark:bg-slate-800/80 p-1 rounded-xl text-xs font-semibold">
                @foreach (['today' => 'dashboard.owner.periods.today', 'yesterday' => 'dashboard.owner.periods.yesterday', '7_days' => 'dashboard.owner.periods.7_days', 'this_month' => 'dashboard.owner.periods.this_month', 'custom' => 'dashboard.owner.periods.custom'] as $pKey => $pLabel)
                    <button
                        type="button"
                        wire:click="$set('period', '{{ $pKey }}')"
                        class="px-3 py-1.5 rounded-lg transition {{ $period === $pKey ? 'bg-white dark:bg-slate-700 text-teal-700 dark:text-teal-300 shadow-sm font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900' }}">
                        {{ __($pLabel) }}
                    </button>
                @endforeach
            </div>

            {{-- Custom Date Range Picker (shown when custom selected or set) --}}
            @if ($period === 'custom')
                <div class="flex items-center gap-2 text-xs">
                    <input type="date" wire:model.live="dateFrom" class="rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 py-1.5 px-3 text-xs focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-500">
                    <span class="text-slate-400">&rarr;</span>
                    <input type="date" wire:model.live="dateTo" class="rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 py-1.5 px-3 text-xs focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-500">
                </div>
            @endif

            {{-- Filter by Till --}}
            <div class="flex items-center gap-1.5 text-xs">
                <label for="filter_till" class="font-semibold text-slate-500 uppercase tracking-wider text-[11px]">{{ __('dashboard.owner.filter_till') }}:</label>
                <select id="filter_till" wire:model.live="tillId" class="rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 py-1.5 px-3 text-xs focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-500">
                    <option value="">{{ __('dashboard.owner.all_tills') }}</option>
                    @foreach ($tills as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Filter by Staff --}}
            <div class="flex items-center gap-1.5 text-xs">
                <label for="filter_staff" class="font-semibold text-slate-500 uppercase tracking-wider text-[11px]">{{ __('dashboard.owner.filter_staff') }}:</label>
                <select id="filter_staff" wire:model.live="staffId" class="rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 py-1.5 px-3 text-xs focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-500">
                    <option value="">{{ __('dashboard.owner.all_staff') }}</option>
                    @foreach ($staffMembers as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>

            @if ($tillId || $staffId || $period !== 'today')
                <button
                    type="button"
                    wire:click="$set('tillId', null); $set('staffId', null); $set('period', 'today')"
                    class="text-xs text-rose-600 hover:text-rose-800 font-semibold underline ml-auto">
                    {{ __('common.clear_filter') }}
                </button>
            @endif
        </div>
    </div>

    {{-- STEP 4: Needs-Attention Panel (First thing the owner reads) --}}
    <section class="rounded-2xl border border-amber-200 dark:border-amber-900/60 bg-gradient-to-r from-amber-50/70 to-orange-50/50 dark:from-amber-950/30 dark:to-orange-950/20 p-5 shadow-sm space-y-3">
        <div class="flex items-center justify-between border-b border-amber-200/60 dark:border-amber-900/40 pb-3">
            <div class="flex items-center gap-2.5">
                <span class="p-1.5 rounded-lg bg-amber-500 text-white shadow-sm">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </span>
                <div>
                    <h2 class="text-sm font-bold text-amber-950 dark:text-amber-200 uppercase tracking-wider">{{ __('dashboard.owner.attention.heading') }}</h2>
                    <p class="text-xs text-amber-800/80 dark:text-amber-400">{{ __('dashboard.owner.attention.subheading') }}</p>
                </div>
            </div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ count($data['attention']) > 0 ? 'bg-amber-200 text-amber-900 dark:bg-amber-900 dark:text-amber-200' : 'bg-emerald-100 text-emerald-800' }}">
                {{ count($data['attention']) }} {{ trans_choice('dashboard.owner.attention.items_count', count($data['attention'])) }}
            </span>
        </div>

        @if (count($data['attention']) > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 pt-1">
                @foreach ($data['attention'] as $item)
                    <div class="rounded-xl border bg-white dark:bg-slate-900 p-3.5 shadow-sm flex flex-col justify-between space-y-2.5 {{ $item['level'] === 'critical' ? 'border-rose-300 dark:border-rose-900/60' : ($item['level'] === 'warning' ? 'border-amber-300 dark:border-amber-900/60' : 'border-slate-200 dark:border-slate-800') }}">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h3 class="text-xs font-bold text-slate-900 dark:text-white">{{ $item['title'] }}</h3>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ $item['description'] }}</p>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-black {{ $item['level'] === 'critical' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' }}">
                                {{ $item['badge'] }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between pt-1 border-t border-slate-100 dark:border-slate-800 text-xs">
                            <a href="{{ $item['url'] }}" class="font-bold text-teal-600 hover:text-teal-700 dark:text-teal-400 inline-flex items-center gap-1">
                                <span>{{ $item['action_label'] }}</span>
                                <span>&rarr;</span>
                            </a>
                            @if (!empty($item['is_approval']))
                                <a href="{{ route('approvals.index') }}" class="px-2.5 py-1 rounded-lg text-xs font-bold bg-teal-600 hover:bg-teal-700 text-white shadow-sm transition">
                                    {{ __('dashboard.owner.attention.approve_now') }}
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="py-4 text-center text-xs font-semibold text-emerald-800 dark:text-emerald-300 flex items-center justify-center gap-2">
                <span>✓</span>
                <span>{{ __('dashboard.owner.attention.all_clear') }}</span>
            </div>
        @endif
    </section>

    {{-- STEP 2: KPI Grid (12 Core Command Centre KPIs with Previous Period Comparison) --}}
    <section class="space-y-3">
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ __('dashboard.owner.kpi.section_title') }}</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3.5">
            @foreach ($data['kpis'] as $kpiKey => $kpi)
                <a href="{{ $kpi['drill_url'] }}"
                   class="group rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm hover:border-teal-500 hover:shadow-md transition flex flex-col justify-between space-y-2">
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 line-clamp-1 group-hover:text-teal-600 transition">
                            {{ $kpi['label'] }}
                        </dt>
                        <dd class="mt-1 text-lg lg:text-xl font-black font-mono text-slate-900 dark:text-white group-hover:text-teal-600 transition">
                            {{ $kpi['formatted'] }}
                        </dd>
                        @if (!empty($kpi['subtext']))
                            <p class="text-[11px] text-slate-400 font-mono mt-0.5">{{ $kpi['subtext'] }}</p>
                        @endif
                    </div>

                    <div class="flex items-center justify-between text-[11px] pt-1.5 border-t border-slate-50 dark:border-slate-800">
                        @if ($kpi['change'] !== null)
                            <span class="inline-flex items-center font-bold font-mono {{ $kpi['is_positive'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                @if ($kpi['change'] > 0)
                                    &uarr; +{{ $kpi['change'] }}%
                                @elseif ($kpi['change'] < 0)
                                    &darr; {{ $kpi['change'] }}%
                                @else
                                    &minus; 0.0%
                                @endif
                            </span>
                        @else
                            <span class="text-slate-400 italic">{{ __('dashboard.owner.kpi.current_state') }}</span>
                        @endif
                        <span class="text-slate-300 group-hover:text-teal-500 transition font-bold">&rarr;</span>
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    {{-- STEP 7: Operating Surplus Tile (BRD v2.0 Replacement for Profit/Loss) --}}
    <section class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
            <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">
                    {{ $data['surplus']['show_cost_profit'] ? __('dashboard.owner.profit_loss_title') : __('dashboard.owner.operating_surplus_title') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ $data['surplus']['show_cost_profit'] ? __('dashboard.owner.profit_loss_desc') : __('dashboard.owner.operating_surplus_desc') }}
                </p>
            </div>
            <a href="{{ route('finance.cash-revenue') }}" class="text-xs font-semibold text-teal-600 hover:text-teal-700">
                {{ __('dashboard.owner.view_financial_report') }} &rarr;
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-center">
            {{-- Net Sales --}}
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/60">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('dashboard.owner.net_sales_revenue') }}</span>
                <p class="mt-1 text-2xl font-black font-mono text-slate-900 dark:text-white">{{ $data['surplus']['formatted_net_sales'] }}</p>
                <p class="text-[11px] text-slate-400 mt-0.5">{{ __('dashboard.owner.gross_less_refunds') }}</p>
            </div>

            {{-- Operating Expenses --}}
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/60">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('dashboard.owner.operating_expenses') }}</span>
                <p class="mt-1 text-2xl font-black font-mono text-rose-600 dark:text-rose-400">&minus; {{ $data['surplus']['formatted_expenses'] }}</p>
                <p class="text-[11px] text-slate-400 mt-0.5">{{ __('dashboard.owner.posted_expenses') }}</p>
            </div>

            {{-- Operating Surplus / Net Profit --}}
            <div class="p-4 rounded-xl bg-teal-50/70 dark:bg-teal-950/30 border border-teal-200 dark:border-teal-900/60">
                <span class="text-xs font-bold uppercase tracking-wider text-teal-800 dark:text-teal-300">
                    {{ $data['surplus']['show_cost_profit'] ? __('dashboard.owner.net_profit') : __('dashboard.owner.operating_surplus') }}
                </span>
                <p class="mt-1 text-2xl font-black font-mono text-teal-900 dark:text-teal-200">
                    {{ $data['surplus']['show_cost_profit'] ? $data['surplus']['formatted_net_profit'] : $data['surplus']['formatted_surplus'] }}
                </p>
                <p class="text-[11px] text-teal-700 dark:text-teal-400 font-semibold mt-0.5">
                    {{ __('dashboard.owner.margin_ratio') }}: {{ $data['surplus']['margin_percent'] }}%
                </p>
            </div>
        </div>
    </section>

    {{-- STEP 3: Interactive Command Charts Section (Accessible Bars, Table View, and CSV Export) --}}
    <section class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ __('dashboard.owner.charts.section_title') }}</h2>
            <p class="text-xs text-slate-400">{{ __('dashboard.owner.charts.table_toggle_hint') }}</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @foreach (['sales_by_hour', 'sales_by_day', 'sales_by_category', 'sales_by_payment_method', 'sales_by_staff', 'top_10_products', 'bottom_10_products'] as $chartKey)
                @php $chart = $data['charts'][$chartKey]; @endphp
                <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-4 flex flex-col justify-between"
                     wire:key="chart-card-{{ $chartKey }}">
                    {{-- Chart Header --}}
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ $chart['title'] }}</h3>
                            <p class="text-xs font-mono text-slate-500 dark:text-slate-400">
                                {{ __('dashboard.owner.charts.total') }}: {{ number_format(($chart['total'] ?? 0) / 100, 2) }} ETB
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            {{-- Table Toggle --}}
                            <button
                                type="button"
                                wire:click="toggleTable('{{ $chartKey }}')"
                                title="{{ !empty($showTable[$chartKey]) ? 'Switch to visual chart' : 'Switch to data table' }}"
                                class="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition text-xs font-semibold">
                                @if (!empty($showTable[$chartKey]))
                                    📊 {{ __('dashboard.owner.charts.chart_view') }}
                                @else
                                    📋 {{ __('dashboard.owner.charts.table_view') }}
                                @endif
                            </button>

                            {{-- CSV Download --}}
                            <button
                                type="button"
                                wire:click="exportChartCsv('{{ $chartKey }}')"
                                title="Download as CSV"
                                class="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition text-xs font-semibold">
                                ⬇ CSV
                            </button>

                            {{-- Drill-down link --}}
                            <a href="{{ $chart['drill_url'] }}" class="p-1.5 text-teal-600 hover:text-teal-700 font-bold" title="Open in Report Centre">
                                &rarr;
                            </a>
                        </div>
                    </div>

                    {{-- Chart Body: Visual Bars or Data Table --}}
                    <div class="min-h-[220px] flex flex-col justify-center">
                        @if (!empty($showTable[$chartKey]))
                            {{-- Table View --}}
                            <div class="overflow-x-auto max-h-[260px] overflow-y-auto">
                                <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                                    <thead>
                                        <tr class="text-left font-semibold text-slate-400 uppercase tracking-wider">
                                            <th class="py-2 px-2">{{ __('dashboard.owner.charts.col_label') }}</th>
                                            <th class="py-2 px-2 text-right">{{ __('dashboard.owner.charts.col_revenue') }}</th>
                                            <th class="py-2 px-2 text-right">{{ __('dashboard.owner.charts.col_count') }}</th>
                                            <th class="py-2 px-2 text-right">%</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50 dark:divide-slate-800/60">
                                        @forelse ($chart['data'] as $row)
                                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                                <td class="py-1.5 px-2 font-medium text-slate-900 dark:text-white">{{ $row['label'] }}</td>
                                                <td class="py-1.5 px-2 text-right font-mono font-semibold text-slate-800 dark:text-slate-200">{{ $row['formatted'] }}</td>
                                                <td class="py-1.5 px-2 text-right font-mono text-slate-500">{{ $row['count'] }}</td>
                                                <td class="py-1.5 px-2 text-right font-mono text-slate-500">{{ $row['percentage'] }}%</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="py-4 text-center text-slate-400 italic">{{ __('dashboard.owner.charts.no_data') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @else
                            {{-- Visual CSS Bar Chart View --}}
                            @if (count($chart['data']) > 0)
                                <div class="space-y-2.5">
                                    @foreach ($chart['data'] as $bar)
                                        <div class="space-y-1">
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="font-medium text-slate-800 dark:text-slate-200 truncate max-w-[200px]" title="{{ $bar['label'] }}">
                                                    {{ $bar['label'] }}
                                                </span>
                                                <span class="font-mono font-semibold text-slate-900 dark:text-white">
                                                    {{ $bar['formatted'] }}
                                                    <span class="text-slate-400 text-[11px] font-normal font-sans">({{ $bar['count'] }})</span>
                                                </span>
                                            </div>
                                            <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2.5 overflow-hidden shadow-inner">
                                                <div class="h-2.5 rounded-full bg-gradient-to-r from-teal-500 to-emerald-500 transition-all duration-500"
                                                     style="width: {{ max(2, min(100, $bar['percentage'])) }}%"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="py-12 text-center text-slate-400 italic text-xs">
                                    {{ __('dashboard.owner.charts.no_data') }}
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- STEP 6: Live Activity Feed (Last 20 Audited Events in Plain Language) --}}
    <section class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ __('dashboard.owner.activity.title') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('dashboard.owner.activity.subtitle') }}</p>
            </div>
            <a href="{{ route('audit.index') }}" class="text-xs font-semibold text-teal-600 hover:text-teal-700">
                {{ __('dashboard.owner.activity.view_all_audit') }} &rarr;
            </a>
        </div>

        <div class="divide-y divide-slate-100 dark:divide-slate-800 max-h-[380px] overflow-y-auto">
            @forelse ($data['activity'] as $event)
                <div class="py-2.5 flex items-start justify-between gap-3 text-xs hover:bg-slate-50/60 dark:hover:bg-slate-800/40 px-2 rounded-lg transition"
                     wire:key="activity-{{ $event['id'] }}">
                    <div class="flex items-start gap-2.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $event['badge_color'] }}">
                            {{ explode('.', $event['action'])[0] ?? 'event' }}
                        </span>
                        <div>
                            <p class="font-semibold text-slate-900 dark:text-slate-100">{{ $event['description'] }}</p>
                            <p class="text-[11px] text-slate-400">{{ $event['user'] }} · {{ $event['exact_time'] }}</p>
                        </div>
                    </div>
                    <span class="text-[11px] text-slate-400 whitespace-nowrap">{{ $event['time_ago'] }}</span>
                </div>
            @empty
                <div class="py-8 text-center text-slate-400 italic text-xs">
                    {{ __('dashboard.owner.activity.none') }}
                </div>
            @endforelse
        </div>
    </section>
</div>
