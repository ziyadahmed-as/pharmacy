<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('prescriptions.title') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('prescriptions.subtitle') }}</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('prescriptions.search') }}" class="inline-flex items-center px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm text-sm font-medium text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                <svg class="-ml-1 mr-2 h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                {{ __('prescriptions.search_nav') }}
            </a>
            @if($canRegister)
                <a href="{{ route('prescriptions.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-teal-600 hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 transition">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    {{ __('prescriptions.new_prescription') }}
                </a>
            @endif
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 flex flex-col sm:flex-row gap-4">
        <div class="relative flex-1">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('prescriptions.search_placeholder') }}"
                class="w-full pl-10 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
            />
        </div>
        <div class="sm:w-64">
            <select
                wire:model.live="statusFilter"
                class="w-full py-2 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
            >
                <option value="">{{ __('prescriptions.all_statuses') }}</option>
                @foreach($statusOptions as $val => $label)
                    <option value="{{ $val }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Prescriptions Table -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-300 font-medium">
                    <tr>
                        <th class="px-6 py-3.5">{{ __('prescriptions.fields.prescription_number') }}</th>
                        <th class="px-6 py-3.5">{{ __('prescriptions.fields.patient') }}</th>
                        <th class="px-6 py-3.5">{{ __('prescriptions.fields.prescriber_name') }}</th>
                        <th class="px-6 py-3.5">{{ __('prescriptions.fields.prescription_date') }}</th>
                        <th class="px-6 py-3.5">{{ __('prescriptions.fields.status') }}</th>
                        <th class="px-6 py-3.5 text-right">{{ __('customers.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($prescriptions as $prescription)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                            <td class="px-6 py-4 font-mono font-semibold text-slate-900 dark:text-white">
                                <a href="{{ route('prescriptions.show', $prescription) }}" class="hover:text-teal-600 transition">
                                    {{ $prescription->prescription_number }}
                                </a>
                            </td>
                            <td class="px-6 py-4 text-slate-900 dark:text-white font-medium">
                                {{ $prescription->customer->name }}
                            </td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                                <div>{{ $prescription->prescriber_name }}</div>
                                <div class="text-xs text-slate-400 font-mono">{{ $prescription->prescriber_registration_no }}</div>
                            </td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                                {{ $prescription->prescription_date->format('Y-m-d') }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border
                                    @if($prescription->status === 'verified') bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800
                                    @elseif($prescription->status === 'pending_verification') bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border-amber-200 dark:border-amber-800
                                    @elseif($prescription->status === 'registered') bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300 border-sky-200 dark:border-sky-800
                                    @elseif($prescription->status === 'partially_dispensed') bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300 border-violet-200 dark:border-violet-800
                                    @elseif($prescription->status === 'fully_dispensed') bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-300 border-teal-200 dark:border-teal-800
                                    @elseif($prescription->status === 'rejected') bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border-rose-200 dark:border-rose-800
                                    @elseif($prescription->status === 'cancelled') bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700
                                    @elseif($prescription->status === 'expired') bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300 border-orange-200 dark:border-orange-800
                                    @else bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700
                                    @endif">
                                    {{ $prescription->statusLabel() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('prescriptions.show', $prescription) }}" class="text-teal-600 hover:text-teal-800 dark:text-teal-400 font-medium text-xs">
                                    {{ __('prescriptions.view_details') }} &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-400 dark:text-slate-500">
                                {{ __('prescriptions.no_prescriptions_found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($prescriptions->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                {{ $prescriptions->links() }}
            </div>
        @endif
    </div>
</div>

