<div class="sm:mx-auto sm:w-full sm:max-w-md">
    <div class="border border-slate-100 bg-white px-4 py-8 shadow-xl sm:rounded-xl sm:px-10">
        <h1 class="text-xl font-bold text-slate-900">{{ __('auth.mfa_title') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('auth.mfa_help') }}</p>
        <form wire:submit="verify" class="mt-6 space-y-4">
            <div>
                <label for="code" class="block text-sm font-medium text-slate-700">{{ __('auth.mfa_code') }}</label>
                <input wire:model="code" id="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus
                       class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-center text-lg tracking-widest focus:border-teal-500 focus:ring-teal-500">
                @error('code') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <button type="submit" wire:loading.attr="disabled" class="w-full rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-700">{{ __('auth.mfa_verify') }}</button>
            <button type="button" wire:click="cancel" class="w-full text-sm text-slate-500 hover:text-slate-800">{{ __('common.cancel') }}</button>
        </form>
    </div>
</div>
