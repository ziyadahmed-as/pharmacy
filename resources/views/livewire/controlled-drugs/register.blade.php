<div class="max-w-7xl mx-auto py-6 space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('controlled_drugs.register_title') }}</h1>
            <p class="text-sm text-gray-500">{{ __('controlled_drugs.register_subtitle') }}</p>
        </div>

        {{-- Export buttons (PM and Owner) --}}
        @if(auth()->user()?->hasPermission('controlled_drugs.export'))
            <div class="flex items-center gap-2">
                <a href="{{ route('controlled-drugs.export', array_merge(['format' => 'csv'], request()->query())) }}"
                   class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 shadow-sm">
                    {{ __('common.export_csv') }}
                </a>
                <a href="{{ route('controlled-drugs.export', array_merge(['format' => 'pdf'], request()->query())) }}"
                   class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 shadow-sm">
                    {{ __('common.export_pdf') }}
                </a>
            </div>
        @endif
    </div>

    {{-- Filters --}}
    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('common.search') }}</label>
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('controlled_drugs.search_placeholder') }}"
                   class="block w-full border-gray-300 rounded-md shadow-sm sm:text-sm focus:ring-indigo-500 focus:border-indigo-500" />
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('controlled_drugs.entry_type') }}</label>
            <select wire:model.live="type"
                    class="block w-full border-gray-300 rounded-md shadow-sm sm:text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">{{ __('common.all') }}</option>
                <option value="receipt">{{ __('controlled_drugs.type_receipt') }}</option>
                <option value="dispense">{{ __('controlled_drugs.type_dispense') }}</option>
                <option value="adjustment">{{ __('controlled_drugs.type_adjustment') }}</option>
                <option value="disposal">{{ __('controlled_drugs.type_disposal') }}</option>
            </select>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('common.from_date') }}</label>
            <input type="date" wire:model.live="fromDate"
                   class="block w-full border-gray-300 rounded-md shadow-sm sm:text-sm focus:ring-indigo-500 focus:border-indigo-500" />
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('common.to_date') }}</label>
            <input type="date" wire:model.live="toDate"
                   class="block w-full border-gray-300 rounded-md shadow-sm sm:text-sm focus:ring-indigo-500 focus:border-indigo-500" />
        </div>
    </div>

    {{-- Register table --}}
    <div class="bg-white shadow rounded-lg overflow-x-auto border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">{{ __('common.date') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">{{ __('prescriptions.fields.product') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">{{ __('controlled_drugs.batch_no') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">{{ __('controlled_drugs.entry_type') }}</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase tracking-wider text-xs">{{ __('controlled_drugs.quantity') }}</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase tracking-wider text-xs">{{ __('controlled_drugs.running_balance') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">{{ __('prescriptions.fields.patient') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">{{ __('prescriptions.fields.prescriber_name') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">{{ __('controlled_drugs.recorded_by') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">{{ __('controlled_drugs.second_person') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">{{ __('controlled_drugs.reference') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($entries as $entry)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $entry->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 font-medium text-gray-900">
                            {{ $entry->product->brand }}
                            <span class="block text-xs text-gray-500">{{ $entry->product->strength }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-700 font-mono text-xs">{{ $entry->batch->batch_no }}</td>
                        <td class="px-4 py-3">
                            @php
                                $badgeClass = match($entry->entry_type) {
                                    'receipt' => 'bg-green-100 text-green-800',
                                    'dispense' => 'bg-indigo-100 text-indigo-800',
                                    'disposal' => 'bg-red-100 text-red-800',
                                    default => 'bg-amber-100 text-amber-800',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $badgeClass }}">
                                {{ ucfirst($entry->entry_type) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right font-mono font-semibold">{{ $entry->quantity }}</td>
                        <td class="px-4 py-3 text-right font-mono font-bold text-gray-900">{{ $entry->running_balance }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $entry->patient_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $entry->prescriber_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $entry->user->name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $entry->secondPerson?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500 text-xs">
                            @if($entry->sale)
                                <span class="font-mono">{{ $entry->sale->receipt_no }}</span>
                            @else
                                {{ $entry->notes ?? '—' }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-4 py-8 text-center text-gray-500">
                            {{ __('controlled_drugs.no_entries_found') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $entries->links() }}
    </div>
</div>

