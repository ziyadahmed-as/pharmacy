<div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-4">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
        <div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('dashboard.owner.sales_target_title') }}</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $periodLabel }}</p>
        </div>
        <button type="button" wire:click="$toggle('isEditing')" class="text-xs font-semibold text-teal-600 hover:text-teal-700">
            {{ $isEditing ? __('common.cancel') : ($target ? __('common.edit') : __('sales.targets.set_target')) }}
        </button>
    </div>

    @if ($isEditing)
        <form wire:submit="saveTarget" class="space-y-3 pt-2">
            <div>
                <label for="newTargetAmount" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                    {{ __('sales.targets.monthly_target_etb') }}
                </label>
                <div class="mt-1 relative rounded-xl shadow-sm">
                    <input id="newTargetAmount" type="number" step="0.01" wire:model="newTargetAmount" placeholder="e.g. 50000.00" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 py-1.5 px-3 text-xs focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20">
                </div>
                @error('newTargetAmount')<p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p>@enderror
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="rounded-xl bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-700 transition">
                    {{ __('common.save') }}
                </button>
            </div>
        </form>
    @else
        @if ($target)
            <div class="space-y-3">
                <div class="flex justify-between items-baseline">
                    <span class="text-2xl font-black font-mono text-slate-900 dark:text-white">
                        {{ number_format($achievedSantim / 100, 2) }} <span class="text-xs font-sans font-normal text-slate-500">/ {{ number_format($targetSantim / 100, 2) }} ETB</span>
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold font-mono {{ $percentage >= 100 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300' : 'bg-teal-50 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300' }}">
                        {{ $percentage }}%
                    </span>
                </div>

                {{-- Progress Bar --}}
                <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-3 overflow-hidden shadow-inner">
                    <div class="h-3 rounded-full transition-all duration-500 {{ $percentage >= 100 ? 'bg-emerald-500' : ($percentage >= 50 ? 'bg-teal-500' : 'bg-amber-500') }}"
                         style="width: {{ min(100, $percentage) }}%">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 text-xs pt-1 border-t border-slate-50 dark:border-slate-800">
                    <div>
                        <span class="text-slate-400">{{ __('sales.targets.remaining') }}:</span>
                        <p class="font-mono font-semibold text-slate-800 dark:text-slate-200">{{ number_format($remainingSantim / 100, 2) }} ETB</p>
                    </div>
                    <div class="text-right">
                        <span class="text-slate-400">{{ __('sales.targets.days_left') }}:</span>
                        <p class="font-semibold text-slate-800 dark:text-slate-200">{{ $daysRemaining }} {{ __('sales.targets.days') }}</p>
                    </div>
                </div>
            </div>
        @else
            <div class="py-6 text-center text-slate-400 italic text-xs">
                <p>{{ __('sales.targets.no_target_set') }}</p>
                <button type="button" wire:click="$set('isEditing', true)" class="mt-2 text-teal-600 font-semibold hover:underline">
                    + {{ __('sales.targets.set_target') }}
                </button>
            </div>
        @endif
    @endif
</div>
