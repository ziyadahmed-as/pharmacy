<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">{{ $staff->name }}</h1>
            <p class="text-sm text-slate-500">{{ $staff->title ?: '—' }} · {{ __('staff.status.'.$staff->status) }}</p>
        </div>
        @if ($canManage && $staff->status !== 'ended')
            <div class="flex gap-2">
                <a href="{{ route('staff.edit', $staff) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">{{ __('common.edit') }}</a>
                <button type="button" wire:click="$set('confirmEnd', true)" class="rounded-lg border border-red-300 px-4 py-2 text-sm text-red-700 hover:bg-red-50">{{ __('staff.end_employment') }}</button>
            </div>
        @endif
    </div>

    @if ($confirmEnd)
        <div class="rounded-xl border border-red-200 bg-red-50 p-4" role="alertdialog">
            <p class="text-sm font-medium text-red-900">{{ __('staff.end_confirm') }}</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div><label for="end_date" class="block text-xs font-medium text-red-900">{{ __('staff.end_date') }}</label><input id="end_date" type="date" wire:model="end_date" class="mt-1 w-full rounded-lg border border-red-300 px-3 py-1.5 text-sm"></div>
                <div><label for="end_reason" class="block text-xs font-medium text-red-900">{{ __('common.reason') }}</label><input id="end_reason" type="text" wire:model="end_reason" class="mt-1 w-full rounded-lg border border-red-300 px-3 py-1.5 text-sm"></div>
            </div>
            <div class="mt-3 flex gap-2">
                <button type="button" wire:click="endEmployment" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">{{ __('common.confirm') }}</button>
                <button type="button" wire:click="$set('confirmEnd', false)" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm text-slate-700">{{ __('common.cancel') }}</button>
            </div>
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-semibold text-slate-900">{{ __('staff.personal') }}</h2>
            <dl class="mt-3 space-y-2 text-sm">
                @if ($staff->photo_path)
                    <img src="{{ route('staff.photo', $staff) }}" alt="{{ $staff->name }}" class="h-24 w-24 rounded-lg object-cover">
                @endif
                @foreach (['phone' => 'staff.phone', 'address' => 'staff.address', 'hire_date' => 'staff.hire_date', 'emergency_contact_name' => 'staff.emergency_name', 'emergency_contact_phone' => 'staff.emergency_phone'] as $field => $label)
                    @if ($sensitive || in_array($field, ['phone', 'address', 'hire_date']))
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __($label) }}</dt><dd class="text-slate-900">{{ $staff->{$field} instanceof \Carbon\CarbonInterface ? $staff->{$field}->toDateString() : ($staff->{$field} ?: '—') }}</dd></div>
                    @endif
                @endforeach
                @if ($sensitive)
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('staff.id_number') }}</dt><dd class="font-mono text-slate-900">{{ $staff->id_number ?: '—' }}</dd></div>
                @endif
                @if ($staff->end_date)
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('staff.end_date') }}</dt><dd>{{ $staff->end_date->toDateString() }}</dd></div>
                @endif
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('staff.linked_user') }}</dt><dd>{{ $staff->user?->username ?: '—' }}</dd></div>
            </dl>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-slate-900">{{ __('staff.licence_history') }}</h2>
                <span @class(['rounded px-2 py-0.5 text-xs font-medium', 'bg-emerald-100 text-emerald-700' => $validNow, 'bg-red-100 text-red-700' => ! $validNow])>{{ $validNow ? __('staff.licence_valid') : __('staff.licence_not_valid') }}</span>
            </div>
            <ul class="mt-3 divide-y divide-slate-100 text-sm">
                @forelse ($staff->licences as $lic)
                    <li class="py-2" wire:key="lic-{{ $lic->id }}">
                        <p class="font-medium text-slate-900">{{ $lic->number }} <span class="font-normal text-slate-500">· {{ $lic->issuing_body }}@if($lic->category) · {{ $lic->category }}@endif</span></p>
                        <p class="text-xs text-slate-500">{{ $lic->issued_at?->toDateString() ?: '—' }} → {{ $lic->expires_at->toDateString() }}</p>
                    </li>
                @empty
                    <li class="py-2 text-slate-500">{{ __('staff.no_licence') }}</li>
                @endforelse
            </ul>

            @if ($canManage && $staff->status !== 'ended')
                <form wire:submit="addLicence" class="mt-4 space-y-3 border-t border-slate-100 pt-4">
                    <h3 class="text-sm font-semibold text-slate-800">{{ __('staff.add_licence') }}</h3>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ([['lic_number', 'staff.licence_number', 'text'], ['lic_body', 'staff.issuing_body', 'text'], ['lic_category', 'staff.category', 'text'], ['lic_issued', 'staff.issued_at', 'date'], ['lic_expires', 'staff.expires_at', 'date']] as [$f, $l, $t])
                            <div wire:key="l-{{ $f }}"><label for="{{ $f }}" class="block text-xs font-medium text-slate-600">{{ __($l) }}</label><input id="{{ $f }}" type="{{ $t }}" wire:model="{{ $f }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm">@error($f)<p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>@enderror</div>
                        @endforeach
                    </div>
                    <button type="submit" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">{{ __('common.save') }}</button>
                </form>
            @endif
        </section>
    </div>
</div>
