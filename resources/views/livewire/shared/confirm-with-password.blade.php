<div>
    @if ($open)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true" aria-labelledby="reauth-title">
            <form wire:submit="confirm" class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
                <h2 id="reauth-title" class="text-lg font-semibold text-slate-900">{{ __('auth.reauth_title') }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ __('auth.reauth_help') }}</p>
                <label for="reauth-password" class="mt-4 block text-sm font-medium text-slate-700">{{ __('auth.password') }}</label>
                <input id="reauth-password" type="password" wire:model="password" autocomplete="current-password" autofocus
                       class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-teal-500">
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="cancel" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">{{ __('common.cancel') }}</button>
                    <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700 disabled:opacity-60">{{ __('common.confirm') }}</button>
                </div>
            </form>
        </div>
    @endif
</div>
