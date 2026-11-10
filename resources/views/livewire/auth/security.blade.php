<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('nav.security') }}</h1>
        <p class="mt-1 text-sm text-slate-500">Manage multi-factor authentication and update your password</p>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-4">
        <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
            <svg class="h-5 w-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <h2 class="text-base font-bold text-slate-900">{{ __('auth.mfa_section') }}</h2>
        </div>

        @if (! $canUseTwoFactor)
            <p class="text-xs text-slate-500">{{ __('auth.mfa_not_available') }}</p>
        @elseif ($enabled)
            <div class="flex items-center gap-2 rounded-xl bg-emerald-50 p-3 text-xs font-semibold text-emerald-800">
                <svg class="h-4 w-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                <span>{{ __('auth.mfa_is_on') }}</span>
            </div>
            <div class="mt-4 flex items-end gap-3">
                <div class="flex-1">
                    <label for="disablePassword" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('auth.password') }}</label>
                    <input id="disablePassword" type="password" wire:model="disablePassword" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                    @error('disablePassword') <p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                </div>
                <button type="button" wire:click="disableTwoFactor" class="rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 transition">{{ __('auth.mfa_disable') }}</button>
            </div>
        @elseif ($pendingSecret)
            <p class="text-xs text-slate-600">{{ __('auth.mfa_setup_help') }}</p>
            <p class="mt-2 break-all rounded-xl bg-slate-50 p-3 font-mono text-sm border border-slate-200 font-bold text-slate-800 text-center">{{ $pendingSecret }}</p>
            <p class="mt-1 break-all text-[11px] text-slate-400 font-mono">{{ $uri }}</p>
            <div class="mt-4 flex items-end gap-3">
                <div class="flex-1">
                    <label for="code" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('auth.mfa_code') }}</label>
                    <input id="code" wire:model="code" inputmode="numeric" maxlength="6" placeholder="000000" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm font-mono tracking-widest text-center focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                    @error('code') <p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                </div>
                <button type="button" wire:click="confirmTwoFactor" class="rounded-xl bg-teal-600 px-5 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition">{{ __('auth.mfa_enable') }}</button>
            </div>
        @else
            <p class="text-xs text-slate-600">{{ __('auth.mfa_optional_help') }}</p>
            <button type="button" wire:click="startTwoFactor" class="rounded-xl bg-teal-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition">{{ __('auth.mfa_start') }}</button>
        @endif
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-4">
        <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
            <svg class="h-5 w-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
            <h2 class="text-base font-bold text-slate-900">{{ __('auth.change_password') }}</h2>
        </div>
        <form wire:submit="changePassword" class="space-y-4">
            @foreach (['current_password' => 'auth.current_password', 'password' => 'auth.new_password', 'password_confirmation' => 'auth.confirm_password'] as $field => $label)
                <div>
                    <label for="sec-{{ $field }}" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __($label) }}</label>
                    <input type="password" id="sec-{{ $field }}" wire:model="{{ $field }}" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                    @error($field) <p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                </div>
            @endforeach
            <div class="pt-2">
                <button type="submit" class="rounded-xl bg-teal-600 px-5 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition">{{ __('auth.change_password') }}</button>
            </div>
        </form>
    </section>
</div>
