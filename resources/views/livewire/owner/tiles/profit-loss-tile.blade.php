<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm hover:shadow-md transition flex flex-col justify-between">
    <div>
        <div class="flex items-center justify-between text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
            <span>
                @if($showCostProfit)
                    {{ __('dashboard.owner.profit_and_loss') }}
                @else
                    {{ __('dashboard.owner.operating_surplus_title') }}
                @endif
            </span>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-300">
                {{ __('dashboard.owner.owner_confidential') }}
            </span>
        </div>

        <div class="text-3xl font-extrabold font-mono {{ $netProfit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
            {{ number_format($netProfit / 100, 2) }} <span class="text-sm font-normal text-slate-500">ETB</span>
        </div>
        <p class="text-xs text-slate-500 mt-1">
            {{ $showCostProfit ? __('dashboard.owner.est_net_profit_today') : __('dashboard.owner.operating_surplus') }}
            · <span class="font-bold text-slate-700 dark:text-slate-300">{{ $marginPercent }}%</span> {{ __('dashboard.owner.margin') }}
        </p>

        <div class="mt-3 grid grid-cols-3 gap-1.5 text-center text-xs">
            @if($showCostProfit)
            <div class="bg-slate-50 dark:bg-slate-900/60 rounded-xl p-2 border border-slate-100 dark:border-slate-700/50">
                <span class="text-[9px] text-slate-400 uppercase font-semibold">{{ __('dashboard.owner.cogs') }}</span>
                <div class="font-bold font-mono text-[11px] text-slate-700 dark:text-slate-300">
                    {{ number_format($cogs / 100, 2) }}
                </div>
            </div>
            <div class="bg-slate-50 dark:bg-slate-900/60 rounded-xl p-2 border border-slate-100 dark:border-slate-700/50">
                <span class="text-[9px] text-slate-400 uppercase font-semibold">{{ __('dashboard.owner.gross_profit') }}</span>
                <div class="font-bold font-mono text-[11px] text-emerald-600">
                    {{ number_format($grossProfit / 100, 2) }}
                </div>
            </div>
            @else
            <div class="bg-slate-50 dark:bg-slate-900/60 rounded-xl p-2 border border-slate-100 dark:border-slate-700/50 col-span-2">
                <span class="text-[9px] text-slate-400 uppercase font-semibold">{{ __('dashboard.owner.net_sales_revenue') }}</span>
                <div class="font-bold font-mono text-[11px] text-emerald-600">
                    {{ number_format($netRevenue / 100, 2) }}
                </div>
            </div>
            @endif
            <div class="bg-slate-50 dark:bg-slate-900/60 rounded-xl p-2 border border-slate-100 dark:border-slate-700/50">
                <span class="text-[9px] text-slate-400 uppercase font-semibold">{{ __('dashboard.owner.expenses') }}</span>
                <div class="font-bold font-mono text-[11px] text-rose-600">
                    {{ number_format($expenses / 100, 2) }}
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-xs">
        <span class="text-slate-400">{{ __('dashboard.owner.valuation_and_margins') }}</span>
        <a href="{{ $reportUrl }}" class="text-teal-600 hover:text-teal-700 dark:text-teal-400 font-semibold inline-flex items-center gap-1">
            <span>{{ __('dashboard.owner.view_report') }}</span>
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>
</div>

