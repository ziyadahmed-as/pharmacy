<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm hover:shadow-md transition flex flex-col justify-between">
    <div>
        <div class="flex items-center justify-between text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
            <span>{{ __('dashboard.owner.top_sellers') }}</span>
            <span class="text-[10px] text-slate-400 font-normal">{{ __('dashboard.owner.by_volume') }}</span>
        </div>

        @if ($topProducts->isEmpty())
            <div class="py-6 text-center text-xs text-slate-400">
                {{ __('dashboard.owner.no_sales_data') }}
            </div>
        @else
            <div class="space-y-2">
                @foreach ($topProducts as $idx => $prod)
                    <div class="flex items-center justify-between text-xs py-1 border-b border-slate-50 dark:border-slate-700/40 last:border-0">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="h-4 w-4 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-bold text-[10px] flex items-center justify-center shrink-0">
                                {{ $idx + 1 }}
                            </span>
                            <div class="truncate font-medium text-slate-800 dark:text-slate-200">
                                {{ $prod->brand }}
                            </div>
                        </div>
                        <div class="text-right shrink-0 font-mono">
                            <span class="font-bold text-slate-900 dark:text-white">{{ $prod->total_qty }}</span>
                            <span class="text-[10px] text-slate-400">({{ number_format($prod->total_revenue / 100, 2) }} ETB)</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-xs">
        <span class="text-slate-400">{{ __('dashboard.owner.product_performance') }}</span>
        <a href="{{ $reportUrl }}" class="text-teal-600 hover:text-teal-700 dark:text-teal-400 font-semibold inline-flex items-center gap-1">
            <span>{{ __('dashboard.owner.view_report') }}</span>
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>
</div>

