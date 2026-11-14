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
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('prescriptions.refills_allowed') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('prescriptions.next_refill_date') }}</th>
                    <th class="px-4 py-3 text-right"></th>
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
                        <td class="px-4 py-3 text-xs text-gray-600">
                            @if($line->refills_allowed > 0)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $line->canRefill() ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $line->refills_used }} / {{ $line->refills_allowed }}
                                </span>
                            @else
                                <span class="text-gray-400">&mdash;</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600">
                            @if($line->next_refill_date)
                                <span class="font-medium text-indigo-700">{{ $line->next_refill_date->format('Y-m-d') }}</span>
                            @else
                                <span class="text-gray-400">&mdash;</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right space-x-1">
                            @if($line->remainingQuantity() > 0)
                                <button wire:click="selectLine({{ $line->id }}, false)"
                                        class="text-xs px-2.5 py-1 rounded font-medium {{ $selectedLineId === $line->id && !$isRefill ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-indigo-50' }}">
                                    {{ __('prescriptions.dispense_line') }}
                                </button>
                            @endif
                            @if($line->canRefill())
                                <button wire:click="selectLine({{ $line->id }}, true)"
                                        class="text-xs px-2.5 py-1 rounded font-medium {{ $selectedLineId === $line->id && $isRefill ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                    {{ __('prescriptions.refill') }}
                                </button>
                            @endif
                            @if($line->isFullyDispensed())
                                <span class="text-xs text-gray-400 font-medium">{{ __('prescriptions.status_fully_dispensed') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Dispensing form --}}
    @if($prescription->isDispensable() && !$prescription->isExpiredForDispensing())
        @php $activeLine = $this->selectedLine(); @endphp
        <div class="bg-white shadow rounded-lg p-6 space-y-5">
            {{-- STEP 4: Remaining-quantity panel --}}
            @if($activeLine)
                <div class="rounded-lg border {{ $isRefill ? 'border-emerald-200 bg-emerald-50/60' : 'border-indigo-100 bg-indigo-50/50' }} p-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <span class="text-xs font-semibold uppercase tracking-wider {{ $isRefill ? 'text-emerald-800' : 'text-indigo-800' }}">
                                {{ $isRefill ? __('prescriptions.mode_refill') : __('prescriptions.remaining_quantity_panel') }}
                            </span>
                            <h3 class="text-base font-bold text-gray-900 mt-0.5">
                                {{ $activeLine->product->brand }}
                            </h3>
                            <p class="text-xs text-gray-600">
                                {{ __('prescriptions.fields.dose') }}: {{ $activeLine->dose }}
                            </p>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="text-center sm:text-right">
                                <span class="text-xs text-gray-500 block">{{ __('prescriptions.remaining_qty') }}</span>
                                <span class="text-xl font-extrabold text-indigo-700">{{ $activeLine->remainingQuantity() }}</span>
                            </div>
                            @if($activeLine->refills_allowed > 0)
                                <div class="text-center sm:text-right border-l border-gray-200 pl-4">
                                    <span class="text-xs text-gray-500 block">{{ __('prescriptions.refills_allowed') }}</span>
                                    <span class="text-sm font-bold text-gray-800">{{ $activeLine->refills_used }} / {{ $activeLine->refills_allowed }}</span>
                                </div>
                            @endif
                            @if($activeLine->canRefill())
                                <div class="border-l border-gray-200 pl-4">
                                    <button wire:click="toggleRefill" type="button"
                                            class="px-3 py-1.5 rounded text-xs font-semibold {{ $isRefill ? 'bg-indigo-600 text-white' : 'bg-emerald-600 text-white hover:bg-emerald-700' }}">
                                        {{ $isRefill ? __('prescriptions.mode_partial') : __('prescriptions.refill') }}
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <h2 class="text-lg font-semibold text-gray-800">{{ __('prescriptions.dispense_summary') }}</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Quantity --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ __('prescriptions.remaining_qty') }}
                        @if($activeLine)
                            <span class="text-indigo-600">({{ $activeLine->remainingQuantity() }} {{ __('prescriptions.remaining_qty') }})</span>
                        @endif
                    </label>
                    <input type="number" wire:model.live="quantity" min="1"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                    @error('quantity') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Next Refill Date --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ __('prescriptions.next_refill_date') }}
                        <span class="text-xs text-gray-400">({{ __('prescriptions.optional') }})</span>
                    </label>
                    <input type="date" wire:model="nextRefillDate"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
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
                            class="inline-flex items-center px-6 py-2 {{ $isRefill ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-indigo-600 hover:bg-indigo-700' }} text-white text-sm font-semibold rounded-md shadow disabled:opacity-50">
                        <span wire:loading.remove>{{ $isRefill ? __('prescriptions.refill') : __('prescriptions.dispense') }}</span>
                        <span wire:loading>{{ __('common.please_wait') }}</span>
                    </button>
                </div>
            @endif
        </div>
    @endif
</div>
