<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm hover:shadow-md transition flex flex-col justify-between">
    <div>
        <div class="flex items-center justify-between text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
            <span>{{ __('dashboard.owner.sales_today') }}</span>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">
                {{ $salesCount }} {{ __('dashboard.owner.transactions') }}
            </span>
        </div>

        <div class="text-3xl font-extrabold text-slate-900 dark:text-white font-mono">
            {{ number_format($netSales / 100, 2) }} <span class="text-sm font-normal text-slate-500">ETB</span>
        </div>
        <p class="text-xs text-slate-500 mt-1">
            {{ __('dashboard.owner.gross') }}: {{ number_format($grossSales / 100, 2) }} ETB
            @if ($refunds > 0)
                · <span class="text-rose-600">{{ __('dashboard.owner.refunds') }}: -{{ number_format($refunds / 100, 2) }} ETB</span>
            @endif
        </p>
    </div>

    <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-xs">
        <span class="text-slate-400">{{ __('dashboard.owner.net_sales_today') }}</span>
        <a href="{{ $reportUrl }}" class="text-teal-600 hover:text-teal-700 dark:text-teal-400 font-semibold inline-flex items-center gap-1">
            <span>{{ __('dashboard.owner.view_report') }}</span>
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>
</div>

