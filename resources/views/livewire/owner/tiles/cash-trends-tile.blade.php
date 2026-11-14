<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm hover:shadow-md transition flex flex-col justify-between">
    <div>
        <div class="flex items-center justify-between text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
            <span>{{ __('dashboard.owner.cash_and_trends') }}</span>
            <span class="text-[10px] text-slate-400 font-normal">{{ __('dashboard.owner.seven_day_trend') }}</span>
        </div>

        {{-- Cash vs Digital stats --}}
        <div class="grid grid-cols-2 gap-2 my-2">
            <div class="bg-slate-50 dark:bg-slate-900/60 rounded-xl p-2.5">
                <span class="text-[10px] font-bold uppercase text-slate-400">{{ __('dashboard.owner.cash') }}</span>
                <div class="text-base font-extrabold text-slate-900 dark:text-white font-mono">
                    {{ number_format($cashTender / 100, 2) }}
                </div>
            </div>
            <div class="bg-slate-50 dark:bg-slate-900/60 rounded-xl p-2.5">
                <span class="text-[10px] font-bold uppercase text-teal-600 dark:text-teal-400">{{ __('dashboard.owner.digital') }}</span>
                <div class="text-base font-extrabold text-slate-900 dark:text-white font-mono">
                    {{ number_format($digitalTender / 100, 2) }}
                </div>
            </div>
        </div>

        {{-- Mini bar trend chart --}}
        <div class="mt-3">
            <div class="text-[10px] text-slate-400 mb-1 font-semibold uppercase">{{ __('dashboard.owner.daily_revenue') }}</div>
            <div class="flex items-end justify-between h-14 gap-1.5 pt-2">
                @foreach ($trend as $day)
                    @php
                        $pct = $maxTrendAmount > 0 ? max(8, (int) round(($day['amount'] / $maxTrendAmount) * 100)) : 8;
                    @endphp
                    <div class="flex-1 flex flex-col items-center gap-1 group relative">
                        <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-t overflow-hidden flex items-end h-10">
                            <div
                                class="w-full bg-teal-500 hover:bg-teal-600 transition rounded-t"
                                style="height: {{ $pct }}%"
                                title="{{ $day['date'] }}: {{ $day['amount_etb'] }} ETB">
                            </div>
                        </div>
                        <span class="text-[9px] text-slate-400 uppercase font-mono">{{ $day['day'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-xs">
        <span class="text-slate-400">{{ __('dashboard.owner.cash_reconciliation') }}</span>
        <a href="{{ $reportUrl }}" class="text-teal-600 hover:text-teal-700 dark:text-teal-400 font-semibold inline-flex items-center gap-1">
            <span>{{ __('dashboard.owner.view_report') }}</span>
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>
</div>

