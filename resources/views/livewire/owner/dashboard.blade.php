<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 dark:border-slate-700 pb-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('dashboard.owner.title') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-300">
                    {{ __('dashboard.owner.executive_view') }}
                </span>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('dashboard.owner.subtitle') }} · {{ now()->format('l, d F Y') }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            <button
                type="button"
                wire:click="sendSummary"
                class="inline-flex items-center px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-xl shadow-sm text-xs font-bold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                <svg class="-ml-1 mr-2 h-4 w-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                {{ __('dashboard.owner.send_daily_summary_btn') }}
            </button>
            <a href="{{ route('reports.index') }}" class="inline-flex items-center px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold shadow-sm transition">
                {{ __('dashboard.owner.all_reports') }}
            </a>
        </div>
    </div>

    {{-- Feedback Alerts --}}
    @if ($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 dark:bg-emerald-900/30 p-4 text-sm font-medium text-emerald-800 dark:text-emerald-200 flex items-center justify-between shadow-sm">
            <span>✓ {{ $statusMessage }}</span>
            <button wire:click="$set('statusMessage', null)" class="text-emerald-500 hover:text-emerald-700">✕</button>
        </div>
    @endif

    @if ($errorMessage)
        <div class="rounded-xl border border-rose-200 bg-rose-50 dark:bg-rose-900/30 p-4 text-sm font-medium text-rose-800 dark:text-rose-200 flex items-center justify-between shadow-sm">
            <span>⚠️ {{ $errorMessage }}</span>
            <button wire:click="$set('errorMessage', null)" class="text-rose-500 hover:text-rose-700">✕</button>
        </div>
    @endif

    {{-- Responsive Metric Tiles Grid (1 col mobile, 2 col tablet, 3 col desktop) --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {{-- Tile 0: Monthly Sales Target --}}
        <livewire:owner.tiles.sales-target-tile />

        {{-- Tile 1: Sales Today --}}
        <livewire:owner.tiles.sales-today-tile />

        {{-- Tile 2: Cash & Trends --}}
        <livewire:owner.tiles.cash-trends-tile />

        {{-- Tile 3: Profit & Loss (Owner confidential) --}}
        <livewire:owner.tiles.profit-loss-tile />

        {{-- Tile 4: Top Sellers --}}
        <livewire:owner.tiles.top-sellers-tile />

        {{-- Tile 5: Low Stock --}}
        <livewire:owner.tiles.low-stock-tile />

        {{-- Tile 6: Expiring Items --}}
        <livewire:owner.tiles.expiring-items-tile />
    </div>

    {{-- Quick Access Section --}}
    <div class="bg-slate-50 dark:bg-slate-900/40 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 mt-6">
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">{{ __('dashboard.owner.quick_reports') }}</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
            <a href="{{ route('finance.cash-revenue') }}" class="p-3 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-teal-500 transition shadow-sm font-medium text-slate-700 dark:text-slate-300 flex items-center justify-between">
                <span>{{ __('dashboard.owner.cash_revenue_summary') }}</span>
                <span class="text-slate-400">→</span>
            </a>
            <a href="{{ route('credits.ageing') }}" class="p-3 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-teal-500 transition shadow-sm font-medium text-slate-700 dark:text-slate-300 flex items-center justify-between">
                <span>{{ __('dashboard.owner.receivables_ageing') }}</span>
                <span class="text-slate-400">→</span>
            </a>
            <a href="{{ route('reports.run', ['type' => 'compliance-controlled-drugs']) }}" class="p-3 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-teal-500 transition shadow-sm font-medium text-slate-700 dark:text-slate-300 flex items-center justify-between">
                <span>{{ __('dashboard.owner.controlled_drugs') }}</span>
                <span class="text-slate-400">→</span>
            </a>
            <a href="{{ route('audit.index') }}" class="p-3 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-teal-500 transition shadow-sm font-medium text-slate-700 dark:text-slate-300 flex items-center justify-between">
                <span>{{ __('dashboard.owner.audit_trail') }}</span>
                <span class="text-slate-400">→</span>
            </a>
        </div>
    </div>
</div>

