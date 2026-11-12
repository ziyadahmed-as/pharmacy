<div class="max-w-4xl mx-auto py-6 space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('prescriptions.dispense') }}</h1>
            <p class="text-sm text-gray-500">
                {{ __('prescriptions.fields.prescription_number') }}: <strong>{{ $prescription->prescription_number }}</strong>
                &mdash; {{ $prescription->customer->name }}
            </p>
        </div>
        <a href="{{ route('prescriptions.show', $prescription) }}"
           class="text-sm text-indigo-600 hover:underline">
            &larr; {{ __('prescriptions.back_to_list') }}
        </a>
    </div>

    {{-- Status badge --}}
    <div class="flex gap-2 items-center">
        <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium bg-indigo-100 text-indigo-800">
            {{ $prescription->statusLabel() }}
        </span>
        @if($prescription->isExpiredForDispensing())
            <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium bg-red-100 text-red-800">
                {{ __('prescriptions.status_expired') }}
            </span>
        @endif
    </div>

    {{-- Success / Error messages --}}
    @if($successMessage)
        <div class="rounded-md bg-green-50 p-4 border border-green-200">
            <p class="text-green-800 text-sm font-medium">{{ $successMessage }}</p>
        </div>
    @endif

    @if($errorMessage && !$showOverrideModal && !$showRepeatWarning)
        <div class="rounded-md bg-red-50 p-4 border border-red-200">
            <p class="text-red-800 text-sm">{{ $errorMessage }}</p>
        </div>
    @endif

    {{-- Prescription lines table --}}
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('prescriptions.fields.product') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('prescriptions.fields.dose') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('prescriptions.fields.quantity_prescribed') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('prescriptions.fields.quantity_dispensed') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('prescriptions.remaining_qty') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($prescription->lines as $line)
                    <tr class="{{ $selectedLineId === $line->id ? 'bg-indigo-50' : '' }}">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $line->product->brand }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $line->dose }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ $line->quantity_prescribed }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ $line->quantity_dispensed }}</td>
                        <td class="px-4 py-3 text-sm font-semibold {{ $line->remainingQuantity() > 0 ? 'text-green-700' : 'text-gray-400' }}">
                            {{ $line->remainingQuantity() }}
                        </td>
                        <td class="px-4 py-3">
                            @if(!$line->isFullyDispensed())
                                <button wire:click="$set('selectedLineId', {{ $line->id }})"
                                        class="text-xs px-2 py-1 rounded {{ $selectedLineId === $line->id ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-indigo-50' }}">
                                    {{ __('prescriptions.dispense_line') }}
                                </button>
                            @else
                                <span class="text-xs text-gray-400">{{ __('prescriptions.status_fully_dispensed') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Dispensing form --}}
    @if($prescription->isDispensable() && !$prescription->isExpiredForDispensing())
        <div class="bg-white shadow rounded-lg p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-800">{{ __('prescriptions.dispense_summary') }}</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Quantity --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ __('prescriptions.remaining_qty') }}
                        @php $activeLine = $prescription->lines->firstWhere('id', $selectedLineId); @endphp
                        @if($activeLine)
                            <span class="text-indigo-600">({{ $activeLine->remainingQuantity() }} {{ __('prescriptions.remaining_qty') }})</span>
                        @endif
                    </label>
                    <input type="number" wire:model.live="quantity" min="1"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                    @error('quantity') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Proposed batch --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('prescriptions.proposed_batch') }}</label>
                    <p class="mt-1 text-sm text-gray-600 py-2">
                        @if($batchId)
                            Batch #{{ $batchId }}
                        @else
                            &mdash;
                        @endif
                    </p>
                </div>

                {{-- Payment method --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('prescriptions.payment_method') }}</label>
                    <select wire:model="paymentMethod"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="cash">{{ __('sales.payment_method_cash') }}</option>
                        <option value="mobile_money">{{ __('sales.payment_method_mobile_money') }}</option>
                        <option value="card">{{ __('sales.payment_method_card') }}</option>
                    </select>
                </div>

                {{-- Payment reference --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('prescriptions.payment_reference') }}</label>
                    <input type="text" wire:model="paymentReference"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                           placeholder="{{ __('prescriptions.optional') }}" />
                </div>
            </div>

            {{-- Controlled Drug Confirmation Panel --}}
            @if($isControlled)
                <div class="rounded-md bg-purple-50 border border-purple-200 p-4 space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-bold bg-purple-200 text-purple-900 uppercase tracking-wide">
                            {{ __('controlled_drugs.badge') }}
                        </span>
                        <h3 class="text-sm font-semibold text-purple-900">{{ __('controlled_drugs.confirmation_title') }}</h3>
                    </div>
                    <p class="text-xs text-purple-700">{{ __('controlled_drugs.confirmation_notice') }}</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                        <div>
                            <label class="block text-xs font-medium text-purple-900 mb-1">{{ __('controlled_drugs.confirmed_patient') }}</label>
                            <input type="text" wire:model="confirmedPatientName"
                                   class="block w-full border-purple-300 rounded-md shadow-sm sm:text-sm focus:ring-purple-500 focus:border-purple-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-purple-900 mb-1">{{ __('controlled_drugs.confirmed_prescriber') }}</label>
                            <input type="text" wire:model="confirmedPrescriberName"
                                   class="block w-full border-purple-300 rounded-md shadow-sm sm:text-sm focus:ring-purple-500 focus:border-purple-500" />
                        </div>
                    </div>
                </div>
            @endif

            {{-- Repeat warning acknowledgement --}}
            @if($showRepeatWarning)
                <div class="rounded-md bg-amber-50 border border-amber-200 p-4 space-y-3">
                    <h3 class="text-sm font-semibold text-amber-800">{{ __('prescriptions.repeat_warning_title') }}</h3>
                    <p class="text-sm text-amber-700">{{ $errorMessage }}</p>
                    <label class="flex items-center gap-2 text-sm text-amber-800">
                        <input type="checkbox" wire:model="repeatWarningAcknowledged" />
                        {{ __('prescriptions.repeat_warning_acknowledge') }}
                    </label>
                    @if($repeatWarningAcknowledged)
                        <button wire:click="dispense"
                                class="inline-flex items-center px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-md hover:bg-amber-700">
                            {{ __('prescriptions.dispense') }}
                        </button>
                    @endif
                </div>
            @endif

            {{-- Override reason --}}
            @if($showOverrideModal)
                <div class="rounded-md bg-red-50 border border-red-200 p-4 space-y-3">
                    <h3 class="text-sm font-semibold text-red-800">{{ __('prescriptions.override_reason_label') }}</h3>
                    <p class="text-sm text-red-700">{{ $errorMessage }}</p>
                    <textarea wire:model="overrideReason" rows="2"
                              class="block w-full border-red-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm"
                              placeholder="{{ __('prescriptions.override_reason_label') }}"></textarea>
                    @if($overrideReason)
                        <button wire:click="dispense"
                                class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700">
                            {{ __('prescriptions.dispense') }}
                        </button>
                    @endif
                </div>
            @endif

            {{-- Submit --}}
            @if(!$showOverrideModal && !$showRepeatWarning)
                <div class="flex justify-end">
                    <button wire:click="dispense" wire:loading.attr="disabled"
                            class="inline-flex items-center px-6 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md shadow hover:bg-indigo-700 disabled:opacity-50">
                        <span wire:loading.remove>{{ __('prescriptions.dispense') }}</span>
                        <span wire:loading>{{ __('common.please_wait') }}</span>
                    </button>
                </div>
            @endif
        </div>
    @endif
</div>

