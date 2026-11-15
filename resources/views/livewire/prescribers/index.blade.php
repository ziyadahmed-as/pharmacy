<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('prescribers.title', ['default' => 'Prescribers Register']) }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('prescribers.subtitle', ['default' => 'Maintain authorized health professionals, medical facilities and licence records.']) }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if($canManage)
                <button
                    type="button"
                    wire:click="openCreateModal"
                    class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-sm font-semibold transition flex items-center gap-2 shadow-sm"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('prescribers.add_prescriber', ['default' => 'Add Prescriber']) }}
                </button>
            @endif
        </div>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="p-4 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-sm text-emerald-800 dark:text-emerald-200">
            {{ session('success') }}
        </div>
    @endif

    {{-- Filter bar --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <label for="search" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('common.search', ['default' => 'Search']) }}</label>
                <div class="relative">
                    <input
                        type="text"
                        id="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ __('prescribers.search_placeholder', ['default' => 'Search by name, licence number, facility or phone...']) }}"
                        class="w-full pl-9 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-teal-500 focus:border-teal-500"
                    />
                    <svg class="h-4 w-4 text-slate-400 absolute left-3 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            <div>
                <label for="statusFilter" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('common.status', ['default' => 'Status']) }}</label>
                <select
                    id="statusFilter"
                    wire:model.live="statusFilter"
                    class="w-full py-2 px-3 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-teal-500 focus:border-teal-500"
                >
                    <option value="all">{{ __('common.all', ['default' => 'All Statuses']) }}</option>
                    <option value="active">{{ __('common.active', ['default' => 'Active']) }}</option>
                    <option value="inactive">{{ __('common.inactive', ['default' => 'Inactive']) }}</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Prescribers Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
            <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">{{ __('prescribers.name', ['default' => 'Name']) }}</th>
                    <th class="px-4 py-3 text-left">{{ __('prescribers.licence_number', ['default' => 'Licence Number']) }}</th>
                    <th class="px-4 py-3 text-left">{{ __('prescribers.facility', ['default' => 'Facility']) }}</th>
                    <th class="px-4 py-3 text-left">{{ __('prescribers.phone', ['default' => 'Phone']) }}</th>
                    <th class="px-4 py-3 text-center">{{ __('prescriptions.title', ['default' => 'Prescriptions']) }}</th>
                    <th class="px-4 py-3 text-center">{{ __('common.status', ['default' => 'Status']) }}</th>
                    @if($canManage)
                        <th class="px-4 py-3 text-right">{{ __('common.actions', ['default' => 'Actions']) }}</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                @forelse($prescribers as $prescriber)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-750 transition">
                        <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">
                            {{ $prescriber->name }}
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-300">
                            {{ $prescriber->licence_number ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                            {{ $prescriber->facility ?? '—' }}
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-300">
                            {{ $prescriber->phone ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-center text-slate-700 dark:text-slate-300 font-semibold">
                            {{ $prescriber->prescriptions_count }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($prescriber->is_active)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                    {{ __('common.active', ['default' => 'Active']) }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                                    {{ __('common.inactive', ['default' => 'Inactive']) }}
                                </span>
                            @endif
                        </td>
                        @if($canManage)
                            <td class="px-4 py-3 text-right space-x-2">
                                <button
                                    type="button"
                                    wire:click="edit({{ $prescriber->id }})"
                                    class="text-xs font-medium text-teal-600 hover:text-teal-800 dark:text-teal-400 dark:hover:text-teal-300"
                                >
                                    {{ __('common.edit', ['default' => 'Edit']) }}
                                </button>
                                <button
                                    type="button"
                                    wire:click="toggleActive({{ $prescriber->id }})"
                                    class="text-xs font-medium {{ $prescriber->is_active ? 'text-amber-600 hover:text-amber-800' : 'text-emerald-600 hover:text-emerald-800' }}"
                                >
                                    {{ $prescriber->is_active ? __('common.deactivate', ['default' => 'Deactivate']) : __('common.activate', ['default' => 'Activate']) }}
                                </button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManage ? 7 : 6 }}" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
                            {{ __('prescribers.no_prescribers_found', ['default' => 'No prescribers found matching criteria.']) }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($prescribers->hasPages())
        <div>{{ $prescribers->links() }}</div>
    @endif

    {{-- Create / Edit Modal --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-lg w-full p-6 space-y-4 border border-slate-200 dark:border-slate-700">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-3">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">
                        {{ $editingPrescriberId ? __('prescribers.edit_prescriber', ['default' => 'Edit Prescriber']) : __('prescribers.add_prescriber', ['default' => 'Add Prescriber']) }}
                    </h3>
                    <button type="button" wire:click="$set('showModal', false)" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label for="pName" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                            {{ __('prescribers.name', ['default' => 'Full Name']) }} <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="pName"
                            wire:model="name"
                            placeholder="Dr. Abebe Bikila"
                            class="mt-1 w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-teal-500 focus:border-teal-500"
                        />
                        @error('name') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="pLicence" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                            {{ __('prescribers.licence_number', ['default' => 'Licence / Registration Number']) }}
                        </label>
                        <input
                            type="text"
                            id="pLicence"
                            wire:model="licence_number"
                            placeholder="MED-12345"
                            class="mt-1 w-full px-3 py-2 font-mono border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-teal-500 focus:border-teal-500"
                        />
                        @error('licence_number') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="pFacility" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                {{ __('prescribers.facility', ['default' => 'Health Facility / Hospital']) }}
                            </label>
                            <input
                                type="text"
                                id="pFacility"
                                wire:model="facility"
                                placeholder="Tikur Anbessa Hospital"
                                class="mt-1 w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-teal-500 focus:border-teal-500"
                            />
                            @error('facility') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="pPhone" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                {{ __('prescribers.phone', ['default' => 'Phone Number']) }}
                            </label>
                            <input
                                type="text"
                                id="pPhone"
                                wire:model="phone"
                                placeholder="0911223344"
                                class="mt-1 w-full px-3 py-2 font-mono border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-teal-500 focus:border-teal-500"
                            />
                            @error('phone') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="flex items-center gap-2 cursor-pointer mt-2">
                            <input
                                type="checkbox"
                                wire:model="is_active"
                                class="rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                            />
                            <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('common.active', ['default' => 'Active prescriber']) }}</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-700">
                        <button
                            type="button"
                            wire:click="$set('showModal', false)"
                            class="px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition"
                        >
                            {{ __('common.cancel', ['default' => 'Cancel']) }}
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-sm font-semibold transition"
                        >
                            {{ __('common.save', ['default' => 'Save']) }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
