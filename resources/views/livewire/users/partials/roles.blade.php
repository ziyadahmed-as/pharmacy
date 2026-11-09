<fieldset>
    <legend class="block text-sm font-medium">{{ __('users.roles') }}</legend>
    <div class="mt-1 space-y-1">
        @foreach ($allRoles as $role)
            <label class="flex items-center gap-2 text-sm" wire:key="rr-{{ $role->value }}"><input type="checkbox" value="{{ $role->value }}" wire:model="roles" class="rounded border-slate-300"> {{ $role->label() }}</label>
        @endforeach
    </div>
    @error('roles') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
</fieldset>
