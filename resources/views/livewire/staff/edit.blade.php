<div class="mx-auto max-w-3xl">
    <h1 class="text-2xl font-bold text-slate-900">{{ $staffId ? __('staff.edit_title') : __('staff.add') }}</h1>
    <form wire:submit="save" class="mt-5 space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="grid gap-4 sm:grid-cols-2">
            @php
                $fields = [
                    ['name', 'common.name', 'text'], ['title', 'staff.title', 'text'], ['phone', 'staff.phone', 'text'], ['address', 'staff.address', 'text'],
                    ['hire_date', 'staff.hire_date', 'date'], ['emergency_contact_name', 'staff.emergency_name', 'text'], ['emergency_contact_phone', 'staff.emergency_phone', 'text'],
                ];
            @endphp
            @foreach ($fields as [$field, $label, $type])
                <div wire:key="f-{{ $field }}">
                    <label for="{{ $field }}" class="block text-sm font-medium text-slate-700">{{ __($label) }}</label>
                    <input id="{{ $field }}" type="{{ $type }}" wire:model="form.{{ $field }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-teal-500">
                    @error('form.'.$field) <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                </div>
            @endforeach
            <div>
                <label for="id_number" class="block text-sm font-medium text-slate-700">{{ __('staff.id_number') }}</label>
                <input id="id_number" type="text" autocomplete="off" wire:model="form.id_number" placeholder="{{ $staffId ? __('staff.id_number_keep') : '' }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('form.id_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="status" class="block text-sm font-medium text-slate-700">{{ __('common.status') }}</label>
                <select id="status" wire:model="form.status" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="active">{{ __('staff.status.active') }}</option><option value="on_leave">{{ __('staff.status.on_leave') }}</option>
                </select>
            </div>
        </div>
        <div>
            <label for="photo" class="block text-sm font-medium text-slate-700">{{ __('staff.photo') }}</label>
            <input id="photo" type="file" wire:model="photo" accept="image/png,image/jpeg,image/webp" class="mt-1 block w-full text-sm">
            <p class="mt-1 text-xs text-slate-500">{{ __('staff.photo_help') }}</p>
            @error('photo') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
        </div>
        <div class="flex justify-end gap-2">
            <a href="{{ $staffId ? route('staff.show', $staffId) : route('staff.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">{{ __('common.cancel') }}</a>
            <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700 disabled:opacity-60">{{ __('common.save') }}</button>
        </div>
    </form>
</div>
