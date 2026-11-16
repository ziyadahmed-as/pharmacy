<div class="space-y-6">
    <x-breadcrumbs
        :items="[
            ['label' => __('nav.staff'), 'url' => route('staff.index')],
            ['label' => $staff->name]
        ]"
        :back="route('staff.index')"
        :backLabel="__('common.back_to_list')"
    />

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $staff->name }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                    @if($staff->status === 'active') bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20
                    @elseif($staff->status === 'on_leave') bg-amber-50 text-amber-700 ring-1 ring-amber-600/20
                    @else bg-slate-100 text-slate-600 ring-1 ring-slate-500/20 @endif">
                    {{ __('staff.status.'.$staff->status) }}
                </span>
            </div>
            <p class="mt-1 text-sm text-slate-500">{{ $staff->title ?: '—' }}</p>
        </div>
        @if ($canManage && $staff->status !== 'ended')
            <div class="flex items-center gap-2.5">
                <a href="{{ route('staff.edit', $staff) }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
                    <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    {{ __('common.edit') }}
                </a>
                <button type="button" wire:click="$set('confirmEnd', true)" class="rounded-xl border border-rose-200 bg-white px-3.5 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50 transition">
                    {{ __('staff.end_employment') }}
                </button>
            </div>
        @endif
    </div>

    @if ($confirmEnd)
        <div class="rounded-2xl border border-rose-200 bg-rose-50/70 p-5 shadow-sm" role="alertdialog">
            <p class="text-sm font-bold text-rose-900">{{ __('staff.end_confirm') }}</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="end_date" class="block text-xs font-semibold uppercase tracking-wider text-rose-900">{{ __('staff.end_date') }}</label>
                    <input id="end_date" type="date" wire:model="end_date" class="mt-1 w-full rounded-xl border border-rose-300 py-2 px-3 text-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-400/20 transition">
                </div>
                <div>
                    <label for="end_reason" class="block text-xs font-semibold uppercase tracking-wider text-rose-900">{{ __('common.reason') }}</label>
                    <input id="end_reason" type="text" wire:model="end_reason" class="mt-1 w-full rounded-xl border border-rose-300 py-2 px-3 text-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-400/20 transition">
                </div>
            </div>
            <div class="mt-4 flex gap-2.5">
                <button type="button" wire:click="endEmployment" class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-rose-700 transition">{{ __('common.confirm') }}</button>
                <button type="button" wire:click="$set('confirmEnd', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">{{ __('common.cancel') }}</button>
            </div>
        </div>
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <h2 class="text-base font-bold text-slate-900 pb-3 border-b border-slate-100">{{ __('staff.personal') }}</h2>
            <dl class="space-y-3 text-xs">
                @if ($staff->photo_path)
                    <div class="pb-2">
                        <img src="{{ route('staff.photo', $staff) }}" alt="{{ $staff->name }}" class="h-24 w-24 rounded-2xl object-cover shadow-sm ring-1 ring-slate-200">
                    </div>
                @endif
                @foreach (['phone' => 'staff.phone', 'address' => 'staff.address', 'hire_date' => 'staff.hire_date', 'emergency_contact_name' => 'staff.emergency_name', 'emergency_contact_phone' => 'staff.emergency_phone'] as $field => $label)
                    @if ($sensitive || in_array($field, ['phone', 'address', 'hire_date']))
                        <div class="flex justify-between gap-4 py-1 border-b border-slate-50">
                            <dt class="font-semibold uppercase tracking-wider text-slate-400">{{ __($label) }}</dt>
                            <dd class="text-slate-800 font-medium">{{ $staff->{$field} instanceof \Carbon\CarbonInterface ? $staff->{$field}->toDateString() : ($staff->{$field} ?: '—') }}</dd>
                        </div>
                    @endif
                @endforeach
                @if ($sensitive)
                    <div class="flex justify-between gap-4 py-1 border-b border-slate-50">
                        <dt class="font-semibold uppercase tracking-wider text-slate-400">{{ __('staff.id_number') }}</dt>
                        <dd class="font-mono text-slate-800 font-semibold">{{ $staff->id_number ?: '—' }}</dd>
                    </div>
                @endif
                @if ($staff->end_date)
                    <div class="flex justify-between gap-4 py-1 border-b border-slate-50">
                        <dt class="font-semibold uppercase tracking-wider text-slate-400">{{ __('staff.end_date') }}</dt>
                        <dd class="text-slate-800 font-medium">{{ $staff->end_date->toDateString() }}</dd>
                    </div>
                @endif
                <div class="flex justify-between gap-4 py-1">
                    <dt class="font-semibold uppercase tracking-wider text-slate-400">{{ __('staff.linked_user') }}</dt>
                    <dd class="text-teal-700 font-mono font-semibold">{{ $staff->user?->username ?: '—' }}</dd>
                </div>
            </dl>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h2 class="text-base font-bold text-slate-900">{{ __('staff.licence_history') }}</h2>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $validNow ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20' : 'bg-rose-50 text-rose-700 ring-1 ring-rose-600/20' }}">
                    {{ $validNow ? __('staff.licence_valid') : __('staff.licence_not_valid') }}
                </span>
            </div>
            <ul class="divide-y divide-slate-100 text-xs">
                @forelse ($staff->licences as $lic)
                    <li class="py-2.5" wire:key="lic-{{ $lic->id }}">
                        <p class="font-bold text-slate-900 font-mono">{{ $lic->number }} <span class="font-normal font-sans text-slate-500">· {{ $lic->issuing_body }}@if($lic->category) · {{ $lic->category }}@endif</span></p>
                        <p class="mt-0.5 text-slate-400">{{ $lic->issued_at?->toDateString() ?: '—' }} &rarr; <span class="font-semibold text-slate-700">{{ $lic->expires_at->toDateString() }}</span></p>
                    </li>
                @empty
                    <li class="py-4 text-center text-slate-400 italic">{{ __('staff.no_licence') }}</li>
                @endforelse
            </ul>

            @if ($canManage && $staff->status !== 'ended')
                <form wire:submit="addLicence" class="mt-4 space-y-4 border-t border-slate-100 pt-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">{{ __('staff.add_licence') }}</h3>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ([['lic_number', 'staff.licence_number', 'text'], ['lic_body', 'staff.issuing_body', 'text'], ['lic_category', 'staff.category', 'text'], ['lic_issued', 'staff.issued_at', 'date'], ['lic_expires', 'staff.expires_at', 'date']] as [$f, $l, $t])
                            <div wire:key="l-{{ $f }}">
                                <label for="{{ $f }}" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">{{ __($l) }}</label>
                                <input id="{{ $f }}" type="{{ $t }}" wire:model="{{ $f }}" class="mt-1 w-full rounded-xl border border-slate-300 py-1.5 px-3 text-xs focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                                @error($f)<p class="mt-1 text-xs text-rose-600 font-medium" role="alert">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>
                    <button type="submit" class="rounded-xl bg-teal-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition">{{ __('common.save') }}</button>
                </form>
            @endif
        </section>
    </div>

    {{-- Section 3: Staff Documents (UC-39 FR-10.3) --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div>
                <h2 class="text-base font-bold text-slate-900">{{ __('staff.documents.title') }}</h2>
                <p class="text-xs text-slate-500">{{ __('staff.documents.subtitle') }}</p>
            </div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                {{ count($staff->documents) }} {{ __('staff.documents.count') }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-xs">
                <thead>
                    <tr class="text-left text-slate-400 font-semibold uppercase tracking-wider">
                        <th class="py-2.5 px-3">{{ __('staff.documents.type') }}</th>
                        <th class="py-2.5 px-3">{{ __('staff.documents.file') }}</th>
                        <th class="py-2.5 px-3">{{ __('staff.documents.dates') }}</th>
                        <th class="py-2.5 px-3">{{ __('staff.documents.status') }}</th>
                        <th class="py-2.5 px-3 text-right">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($staff->documents as $doc)
                        <tr wire:key="doc-{{ $doc->id }}" class="hover:bg-slate-50/60 transition">
                            <td class="py-3 px-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-slate-100 text-slate-800">
                                    {{ __('staff.documents.types.'.$doc->type) }}
                                </span>
                            </td>
                            <td class="py-3 px-3">
                                <p class="font-semibold text-slate-900">{{ $doc->file_name ?: 'document' }}</p>
                                <p class="text-[11px] text-slate-400">{{ $doc->formattedSize() }} · {{ $doc->created_at?->diffForHumans() }}</p>
                            </td>
                            <td class="py-3 px-3 text-slate-600">
                                <div><span class="text-slate-400">{{ __('staff.issued_at') }}:</span> {{ $doc->issued_at?->toDateString() ?: '—' }}</div>
                                <div><span class="text-slate-400">{{ __('staff.expires_at') }}:</span> {{ $doc->expires_at?->toDateString() ?: '—' }}</div>
                            </td>
                            <td class="py-3 px-3">
                                @if (! $doc->expires_at)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-600">
                                        {{ __('staff.documents.no_expiry') }}
                                    </span>
                                @elseif ($doc->isExpired())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-700 ring-1 ring-rose-600/20">
                                        {{ __('staff.documents.expired') }}
                                    </span>
                                @elseif ($doc->isExpiringSoon(30))
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 ring-1 ring-amber-600/20">
                                        {{ __('staff.documents.expiring_soon') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20">
                                        {{ __('staff.documents.valid') }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-right space-x-2">
                                <a href="{{ route('staff.documents.show', [$staff, $doc]) }}" target="_blank" class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-teal-50 text-teal-700 hover:bg-teal-100 transition">
                                    {{ __('common.download') }}
                                </a>
                                @if ($canManage)
                                    <button type="button" wire:click="deleteDocument({{ $doc->id }})" wire:confirm="{{ __('staff.documents.confirm_delete') }}" class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-semibold text-rose-600 hover:bg-rose-50 transition">
                                        {{ __('common.delete') }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-slate-400 italic">
                                {{ __('staff.documents.none') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($canManage && $staff->status !== 'ended')
            <form wire:submit="uploadDocument" class="mt-4 space-y-4 border-t border-slate-100 pt-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">{{ __('staff.documents.upload_heading') }}</h3>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="doc_type" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">{{ __('staff.documents.type') }}</label>
                        <select id="doc_type" wire:model="doc_type" class="mt-1 w-full rounded-xl border border-slate-300 py-1.5 px-3 text-xs focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                            @foreach (\App\Models\StaffDocument::TYPES as $t)
                                <option value="{{ $t }}">{{ __('staff.documents.types.'.$t) }}</option>
                            @endforeach
                        </select>
                        @error('doc_type')<p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="doc_file" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">{{ __('staff.documents.file') }} (max 5MB)</label>
                        <input id="doc_file" type="file" wire:model="doc_file" accept=".pdf,.png,.jpg,.jpeg,.webp" class="mt-1 w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100">
                        @error('doc_file')<p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="doc_issued" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">{{ __('staff.issued_at') }}</label>
                        <input id="doc_issued" type="date" wire:model="doc_issued" class="mt-1 w-full rounded-xl border border-slate-300 py-1.5 px-3 text-xs focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                        @error('doc_issued')<p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="doc_expires" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">{{ __('staff.expires_at') }}</label>
                        <input id="doc_expires" type="date" wire:model="doc_expires" class="mt-1 w-full rounded-xl border border-slate-300 py-1.5 px-3 text-xs focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                        @error('doc_expires')<p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-teal-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 transition">
                        <span wire:loading.remove>{{ __('staff.documents.upload_btn') }}</span>
                        <span wire:loading>{{ __('common.uploading') }}...</span>
                    </button>
                </div>
            </form>
        @endif
    </section>
</div>
