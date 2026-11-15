<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('locations.title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('locations.subtitle') }}</p>
        </div>
        @if ($canManage)
            <div>
                <button wire:click="openCreateModal" class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    {{ __('locations.add_location') }}
                </button>
            </div>
        @endif
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">{{ __('locations.code') }}</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">{{ __('locations.name') }}</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">{{ __('locations.type') }}</th>
                        <th class="px-6 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">{{ __('locations.status') }}</th>
                        <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">{{ __('locations.items_count') }}</th>
                        @if ($canManage)
                            <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">{{ __('common.actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($locations as $loc)
                        <tr class="hover:bg-slate-50/75 transition">
                            <td class="px-6 py-4 text-sm font-mono font-bold text-slate-900">{{ $loc->code }}</td>
                            <td class="px-6 py-4 text-sm font-medium text-slate-800">{{ $loc->name }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize
                                    {{ $loc->type === 'controlled-cabinet' ? 'bg-purple-100 text-purple-800 border border-purple-200' :
                                       ($loc->type === 'quarantine' ? 'bg-amber-100 text-amber-800 border border-amber-200' :
                                       ($loc->type === 'fridge' ? 'bg-blue-100 text-blue-800 border border-blue-200' :
                                       'bg-slate-100 text-slate-700 border border-slate-200')) }}">
                                    {{ str_replace('-', ' ', $loc->type) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center text-sm">
                                @if ($loc->is_active)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200">
                                        {{ __('common.active') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-700 border border-rose-200">
                                        {{ __('common.inactive') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-semibold text-slate-700">
                                {{ $loc->batch_stocks_count }}
                            </td>
                            @if ($canManage)
                                <td class="px-6 py-4 text-right text-sm space-x-2">
                                    <button wire:click="edit({{ $loc->id }})" class="font-semibold text-teal-600 hover:text-teal-800 transition">
                                        {{ __('common.edit') }}
                                    </button>
                                    <button wire:click="toggleActive({{ $loc->id }})" class="font-semibold {{ $loc->is_active ? 'text-amber-600 hover:text-amber-800' : 'text-emerald-600 hover:text-emerald-800' }} transition">
                                        {{ $loc->is_active ? __('locations.deactivate') : __('locations.activate') }}
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canManage ? 6 : 5 }}" class="px-6 py-12 text-center text-sm text-slate-500">
                                {{ __('locations.no_locations_found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-lg font-bold text-slate-900">
                        {{ $editingLocationId ? __('locations.edit_location') : __('locations.new_location') }}
                    </h3>
                    <button wire:click="$set('showModal', false)" class="text-slate-400 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-4 text-left">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">{{ __('locations.code') }} <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="code" class="block w-full rounded-xl border border-slate-300 py-2.5 px-3 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-500/20 font-mono" placeholder="SHELF-A1" />
                        @error('code') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">{{ __('locations.name') }} <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="name" class="block w-full rounded-xl border border-slate-300 py-2.5 px-3 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-500/20" placeholder="Main Shelf A1" />
                        @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">{{ __('locations.type') }} <span class="text-rose-500">*</span></label>
                        <select wire:model="type" class="block w-full rounded-xl border border-slate-300 py-2.5 px-3 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-500/20">
                            @foreach ($types as $t)
                                <option value="{{ $t }}">{{ ucwords(str_replace('-', ' ', $t)) }}</option>
                            @endforeach
                        </select>
                        @error('type') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <input type="checkbox" id="is_active" wire:model="is_active" class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500" />
                        <label for="is_active" class="text-sm font-medium text-slate-700">{{ __('locations.is_active') }}</label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                    <button wire:click="$set('showModal', false)" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                        {{ __('common.cancel') }}
                    </button>
                    <button wire:click="save" class="rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 transition">
                        {{ __('common.save') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
