<div class="max-w-7xl mx-auto py-6 space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('prescriptions.search_title') }}</h1>
            <p class="text-sm text-gray-500">{{ __('prescriptions.search_subtitle') }}</p>
        </div>

        <div class="flex items-center gap-2">
            @if($canExport)
                <a href="{{ route('prescriptions.export', array_merge(['format' => 'csv'], request()->query())) }}"
                   class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 shadow-sm">
                    {{ __('common.export_csv') }}
                </a>
                <a href="{{ route('prescriptions.export', array_merge(['format' => 'pdf'], request()->query())) }}"
                   class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 shadow-sm">
                    {{ __('common.export_pdf') }}
                </a>
            @endif
            <a href="{{ route('prescriptions.index') }}"
               class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 shadow-sm">
                {{ __('prescriptions.back_to_list') }}
            </a>
        </div>
    </div>

    {{-- Filter Panel --}}
    <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-200 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Keyword --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('prescriptions.filter_keyword') }}</label>
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="{{ __('prescriptions.search_placeholder') }}"
                       class="block w-full border-gray-300 rounded-md shadow-sm sm:text-sm focus:ring-teal-500 focus:border-teal-500" />
            </div>

            {{-- Patient --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('prescriptions.fields.patient') }}</label>
                <input type="text" wire:model.live.debounce.300ms="patient"
                       placeholder="{{ __('prescriptions.filter_patient_placeholder') }}"
                       class="block w-full border-gray-300 rounded-md shadow-sm sm:text-sm focus:ring-teal-500 focus:border-teal-500" />
            </div>

            {{-- Prescriber --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('prescriptions.fields.prescriber_name') }}</label>
                <input type="text" wire:model.live.debounce.300ms="prescriber"
                       placeholder="{{ __('prescriptions.filter_prescriber_placeholder') }}"
                       class="block w-full border-gray-300 rounded-md shadow-sm sm:text-sm focus:ring-teal-500 focus:border-teal-500" />
            </div>

            {{-- Drug / Product --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('prescriptions.fields.product') }}</label>
                <input type="text" wire:model.live.debounce.300ms="drug"
                       placeholder="{{ __('prescriptions.filter_drug_placeholder') }}"
                       class="block w-full border-gray-300 rounded-md shadow-sm sm:text-sm focus:ring-teal-500 focus:border-teal-500" />
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end pt-2 border-t border-gray-100">
            {{-- Date From --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('common.from_date') }}</label>
                <input type="date" wire:model.live="dateFrom"
                       class="block w-full border-gray-300 rounded-md shadow-sm sm:text-sm focus:ring-teal-500 focus:border-teal-500" />
            </div>

            {{-- Date To --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('common.to_date') }}</label>
                <input type="date" wire:model.live="dateTo"
                       class="block w-full border-gray-300 rounded-md shadow-sm sm:text-sm focus:ring-teal-500 focus:border-teal-500" />
            </div>

            {{-- Status --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('prescriptions.fields.status') }}</label>
                <select wire:model.live="status"
                        class="block w-full border-gray-300 rounded-md shadow-sm sm:text-sm focus:ring-teal-500 focus:border-teal-500"
                        @if($suspectOnly) disabled @endif>
                    <option value="">{{ __('prescriptions.all_statuses') }}</option>
                    @foreach($statusOptions as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Suspect Checkbox & Reset --}}
            <div class="flex items-center justify-between gap-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model.live="suspectOnly"
                           class="rounded border-rose-300 text-rose-600 focus:ring-rose-500" />
                    <span class="text-xs font-semibold text-rose-700">{{ __('prescriptions.filter_suspect_only') }}</span>
                </label>

                <button type="button" wire:click="resetFilters"
                        class="text-xs text-gray-500 hover:text-gray-800 underline">
                    {{ __('common.clear_filter') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Results Table --}}
    <div class="bg-white shadow rounded-lg overflow-x-auto border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">{{ __('prescriptions.fields.prescription_number') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">{{ __('prescriptions.fields.prescription_date') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">{{ __('prescriptions.fields.patient') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">{{ __('prescriptions.fields.prescriber_name') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">{{ __('prescriptions.prescribed_drugs') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">{{ __('prescriptions.fields.status') }}</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase tracking-wider text-xs">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($prescriptions as $rx)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono font-medium text-gray-900 whitespace-nowrap">
                            {{ $rx->prescription_number }}
                            @if($rx->isRejected())
                                <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-bold bg-rose-100 text-rose-800">
                                    {{ __('prescriptions.suspect_badge') }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $rx->prescription_date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 text-gray-900 font-medium">
                            {{ $rx->customer->name }}
                            <span class="block text-xs text-gray-500">{{ $rx->customer->phone }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-700">
                            {{ $rx->prescriber_name }}
                            <span class="block text-xs font-mono text-gray-400">{{ $rx->prescriber_registration_no }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600 text-xs">
                            <ul class="space-y-1">
                                @foreach($rx->lines as $line)
                                    <li>
                                        <span class="font-medium text-gray-800">{{ $line->product->brand }}</span>
                                        &bull; {{ $line->dose }}
                                        <span class="text-gray-400">({{ $line->quantity_dispensed }}/{{ $line->quantity_prescribed }})</span>
                                    </li>
                                @endforeach
                            </ul>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $rx->isRejected() ? 'bg-rose-100 text-rose-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $rx->statusLabel() }}
                            </span>
                            @if($rx->isRejected() && $rx->rejection_reason)
                                <p class="text-xs text-rose-600 mt-0.5 max-w-xs truncate" title="{{ $rx->rejection_reason }}">
                                    {{ $rx->rejection_reason }}
                                </p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('prescriptions.show', $rx) }}"
                               class="text-xs font-medium text-teal-600 hover:text-teal-800">
                                {{ __('prescriptions.view_details') }} &rarr;
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                            {{ __('prescriptions.no_prescriptions_found') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $prescriptions->links() }}
    </div>
</div>

