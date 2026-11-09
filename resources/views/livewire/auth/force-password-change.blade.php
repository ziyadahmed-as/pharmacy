<div class="mx-auto max-w-md">
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-bold text-slate-900">{{ __('auth.force_change_title') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('auth.force_change_help', ['min' => $min, 'count' => \App\Services\PasswordPolicy::historySize()]) }}</p>
        <form wire:submit="updatePassword" class="mt-5 space-y-4">
            @foreach (['current_password' => 'auth.current_password', 'password' => 'auth.new_password', 'password_confirmation' => 'auth.confirm_password'] as $field => $label)
                <div>
                    <label for="{{ $field }}" class="block text-sm font-medium text-slate-700">{{ __($label) }}</label>
                    <input type="password" id="{{ $field }}" wire:model="{{ $field }}" autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}"
                           class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-teal-500">
                    @error($field) <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                </div>
            @endforeach
            <button type="submit" wire:loading.attr="disabled" class="w-full rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 disabled:opacity-60">{{ __('auth.change_password') }}</button>
        </form>
    </div>
</div>
