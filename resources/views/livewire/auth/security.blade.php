<div class="mx-auto max-w-2xl space-y-6">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('nav.security') }}</h1>

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">{{ __('auth.mfa_section') }}</h2>
        @if (! $canUseTwoFactor)
            <p class="mt-2 text-sm text-slate-500">{{ __('auth.mfa_not_available') }}</p>
        @elseif ($enabled)
            <p class="mt-2 text-sm text-emerald-700">{{ __('auth.mfa_is_on') }}</p>
            <div class="mt-4 flex items-end gap-2">
                <div class="flex-1">
                    <label for="disablePassword" class="block text-sm font-medium text-slate-700">{{ __('auth.password') }}</label>
                    <input id="disablePassword" type="password" wire:model="disablePassword" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('disablePassword') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="button" wire:click="disableTwoFactor" class="rounded-lg border border-red-300 px-4 py-2 text-sm text-red-700 hover:bg-red-50">{{ __('auth.mfa_disable') }}</button>
            </div>
        @elseif ($pendingSecret)
            <p class="mt-2 text-sm text-slate-600">{{ __('auth.mfa_setup_help') }}</p>
            <p class="mt-3 break-all rounded bg-slate-50 p-3 font-mono text-sm">{{ $pendingSecret }}</p>
            <p class="mt-2 break-all text-xs text-slate-400">{{ $uri }}</p>
            <div class="mt-4 flex items-end gap-2">
                <div class="flex-1">
                    <label for="code" class="block text-sm font-medium text-slate-700">{{ __('auth.mfa_code') }}</label>
                    <input id="code" wire:model="code" inputmode="numeric" maxlength="6" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="button" wire:click="confirmTwoFactor" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">{{ __('auth.mfa_enable') }}</button>
            </div>
        @else
            <p class="mt-2 text-sm text-slate-600">{{ __('auth.mfa_optional_help') }}</p>
            <button type="button" wire:click="startTwoFactor" class="mt-4 rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">{{ __('auth.mfa_start') }}</button>
        @endif
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">{{ __('auth.change_password') }}</h2>
        <form wire:submit="changePassword" class="mt-4 space-y-4">
            @foreach (['current_password' => 'auth.current_password', 'password' => 'auth.new_password', 'password_confirmation' => 'auth.confirm_password'] as $field => $label)
                <div>
                    <label for="sec-{{ $field }}" class="block text-sm font-medium text-slate-700">{{ __($label) }}</label>
                    <input type="password" id="sec-{{ $field }}" wire:model="{{ $field }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error($field) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach
            <button type="submit" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">{{ __('auth.change_password') }}</button>
        </form>
    </section>
</div>
