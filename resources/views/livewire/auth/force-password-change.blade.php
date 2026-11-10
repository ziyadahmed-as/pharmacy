<div class="mx-auto max-w-md">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-xl">
        <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-50 text-teal-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </div>
            <div>
                <h1 class="text-lg font-bold text-slate-900">{{ __('auth.force_change_title') }}</h1>
                <p class="text-xs text-slate-400">Security Requirement</p>
            </div>
        </div>

        <p class="mt-4 text-xs text-slate-600 leading-relaxed">{{ __('auth.force_change_help', ['min' => $min, 'count' => \App\Services\PasswordPolicy::historySize()]) }}</p>

        <form wire:submit="updatePassword" class="mt-5 space-y-4">
            @foreach (['current_password' => 'auth.current_password', 'password' => 'auth.new_password', 'password_confirmation' => 'auth.confirm_password'] as $field => $label)
                <div>
                    <label for="{{ $field }}" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __($label) }}</label>
                    <input type="password" id="{{ $field }}" wire:model="{{ $field }}" autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}"
                           class="mt-1 block w-full rounded-xl border border-slate-300 py-2.5 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                    @error($field) <p class="mt-1 text-xs text-rose-600 font-medium" role="alert">{{ $message }}</p> @enderror
                </div>
            @endforeach
            <button type="submit" wire:loading.attr="disabled" class="w-full rounded-xl bg-teal-600 px-4 py-3 text-sm font-semibold text-white shadow-md hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 disabled:opacity-60 transition">
                {{ __('auth.change_password') }}
            </button>
        </form>
    </div>
</div>
