<div class="space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('credits.index') }}" class="text-teal-600 hover:text-teal-800 dark:text-teal-400 text-sm">{{ __('credits.back_to_accounts') }}</a>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 max-w-xl">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white mb-4">{{ __('credits.set_limit') }} — {{ $customer->name }}</h1>

        @if($successMessage)
            <div class="mb-4 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded-lg p-4 text-green-800 dark:text-green-300 text-sm">{{ $successMessage }}</div>
        @endif
        @if($errorMessage)
            <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded-lg p-4 text-red-800 dark:text-red-300 text-sm">{{ $errorMessage }}</div>
        @endif

        @if($account)
            <div class="mb-4 bg-slate-50 dark:bg-slate-900 rounded-lg p-4">
                <div class="flex justify-between text-sm">
                    <span class="text-slate-500">{{ __('credits.balance_outstanding') }}:</span>
                    <span class="font-semibold text-slate-900 dark:text-white">{{ $account->balanceEtb() }} ETB</span>
                </div>
            </div>
        @endif

        <form wire:submit="saveLimit" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('credits.limit_etb') }}</label>
                <input type="number" step="0.01" wire:model="limitEtb" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-teal-500"/>
                @error('limitEtb') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('credits.notes') }}</label>
                <textarea wire:model="notes" rows="3" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-teal-500"></textarea>
            </div>
            <button type="submit" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-sm font-medium transition">{{ __('credits.save_limit') }}</button>
        </form>
    </div>
</div>

