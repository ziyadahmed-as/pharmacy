<div class="sm:mx-auto sm:w-full sm:max-w-md">
    <div class="rounded-2xl bg-white/95 backdrop-blur-md px-6 py-8 shadow-2xl ring-1 ring-slate-900/10 sm:px-10">
        <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-50 text-teal-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </div>
            <div>
                <h1 class="text-lg font-bold text-slate-900">{{ __('auth.mfa_title') }}</h1>
                <p class="text-xs text-slate-400">Two-Factor Verification</p>
            </div>
        </div>

        <p class="mt-4 text-xs text-slate-600 leading-relaxed">{{ __('auth.mfa_help') }}</p>

        <form wire:submit="verify" class="mt-6 space-y-4">
            <div>
                <label for="code" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('auth.mfa_code') }}</label>
                <input wire:model="code" id="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus placeholder="000000"
                       class="mt-1 block w-full rounded-xl border border-slate-300 py-3 px-3 text-center text-xl font-mono tracking-widest text-slate-900 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                @error('code') <p class="mt-1 text-xs text-rose-600 font-medium" role="alert">{{ $message }}</p> @enderror
            </div>
            <button type="submit" wire:loading.attr="disabled" class="w-full rounded-xl bg-teal-600 px-4 py-3 text-sm font-semibold text-white shadow-md hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition">
                {{ __('auth.mfa_verify') }}
            </button>
            <button type="button" wire:click="cancel" class="w-full rounded-xl border border-slate-200 bg-white py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
                {{ __('common.cancel') }}
            </button>
        </form>
    </div>
</div>
