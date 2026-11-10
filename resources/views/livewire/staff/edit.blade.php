<div class="mx-auto max-w-3xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $staffId ? __('staff.edit_title') : __('staff.add') }}</h1>
            <p class="mt-1 text-sm text-slate-500">Employee profile details and contact information</p>
        </div>
        <a href="{{ $staffId ? route('staff.show', $staffId) : route('staff.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-slate-900 transition">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            {{ __('common.cancel') }}
        </a>
    </div>

    <form wire:submit="save" class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-6">
        <div class="grid gap-4 sm:grid-cols-2">
            @php
                $fields = [
                    ['name', 'common.name', 'text'], ['title', 'staff.title', 'text'], ['phone', 'staff.phone', 'text'], ['address', 'staff.address', 'text'],
                    ['hire_date', 'staff.hire_date', 'date'], ['emergency_contact_name', 'staff.emergency_name', 'text'], ['emergency_contact_phone', 'staff.emergency_phone', 'text'],
                ];
            @endphp
            @foreach ($fields as [$field, $label, $type])
                <div wire:key="f-{{ $field }}">
                    <label for="{{ $field }}" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __($label) }}</label>
                    <input id="{{ $field }}" type="{{ $type }}" wire:model="form.{{ $field }}" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                    @error('form.'.$field) <p class="mt-1 text-xs text-rose-600 font-medium" role="alert">{{ $message }}</p> @enderror
                </div>
            @endforeach
            <div>
                <label for="id_number" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('staff.id_number') }}</label>
                <input id="id_number" type="text" autocomplete="off" wire:model="form.id_number" placeholder="{{ $staffId ? __('staff.id_number_keep') : '' }}" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                @error('form.id_number') <p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="status" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('common.status') }}</label>
                <select id="status" wire:model="form.status" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                    <option value="active">{{ __('staff.status.active') }}</option>
                    <option value="on_leave">{{ __('staff.status.on_leave') }}</option>
                </select>
            </div>
        </div>
        <div>
            <label for="photo" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('staff.photo') }}</label>
            <input id="photo" type="file" wire:model="photo" accept="image/png,image/jpeg,image/webp" class="mt-1 block w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100">
            <p class="mt-1 text-xs text-slate-400">{{ __('staff.photo_help') }}</p>
            @error('photo') <p class="mt-1 text-xs text-rose-600 font-medium" role="alert">{{ $message }}</p> @enderror
        </div>
        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
            <a href="{{ $staffId ? route('staff.show', $staffId) : route('staff.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">{{ __('common.cancel') }}</a>
            <button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-teal-600 px-5 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition disabled:opacity-60">{{ __('common.save') }}</button>
        </div>
    </form>
</div>
