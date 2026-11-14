<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm hover:shadow-md transition flex flex-col justify-between">
    <div>
        <div class="flex items-center justify-between text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
            <span>{{ __('dashboard.owner.expiring_items') }}</span>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $expiredCount > 0 ? 'bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300' }}">
                {{ $nearExpiryCount }} {{ __('dashboard.owner.near_expiry') }}
            </span>
        </div>

        <div class="flex items-baseline gap-2">
            <span class="text-3xl font-extrabold font-mono text-amber-600 dark:text-amber-400">
                {{ $nearExpiryCount }}
            </span>
            <span class="text-xs text-slate-500">
                {{ __('dashboard.owner.within_days', ['days' => $thresholdDays]) }}
            </span>
        </div>

        @if ($expiredCount > 0)
            <div class="mt-1 text-xs font-semibold text-rose-600 dark:text-rose-400">
                ⚠️ {{ $expiredCount }} {{ __('dashboard.owner.already_expired') }}
            </div>
        @endif

        @if ($nearExpiryBatches->isNotEmpty())
            <div class="mt-3 space-y-1.5">
                @foreach ($nearExpiryBatches as $batch)
                    <div class="flex items-center justify-between text-xs py-1 border-b border-slate-50 dark:border-slate-700/40 last:border-0">
                        <span class="truncate font-medium text-slate-800 dark:text-slate-200">{{ $batch->product?->brand ?? $batch->batch_no }}</span>
                        <span class="text-right shrink-0 font-mono text-[11px] text-amber-600">
                            {{ $batch->expiry_date?->format('d M Y') }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-xs">
        <span class="text-slate-400">{{ __('dashboard.owner.batch_expiry_monitoring') }}</span>
        <a href="{{ $reportUrl }}" class="text-teal-600 hover:text-teal-700 dark:text-teal-400 font-semibold inline-flex items-center gap-1">
            <span>{{ __('dashboard.owner.view_report') }}</span>
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>
</div>

