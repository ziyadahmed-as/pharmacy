<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('prescriptions.new_prescription') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('prescriptions.subtitle') }}</p>
        </div>
        <a href="{{ route('prescriptions.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
            &larr; {{ __('prescriptions.title') }}
        </a>
    </div>

    @if($errorMessage)
        <div class="p-4 rounded-lg bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800 text-sm text-rose-800 dark:text-rose-200">
            {{ $errorMessage }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">

        {{-- Prescriber Section --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 space-y-4">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-700 pb-2">
                Prescriber Information
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1">
                        {{ __('prescriptions.fields.prescriber_name') }} <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        wire:model="prescriberName"
                        class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-teal-500 focus:outline-none"
                        required
                    />
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1">
                        {{ __('prescriptions.fields.prescriber_registration_no') }} <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        wire:model="prescriberRegNo"
                        class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-teal-500 focus:outline-none"
                        required
                    />
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1">
                        {{ __('prescriptions.fields.prescription_date') }} <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="date"
                        wire:model="prescriptionDate"
                        max="{{ now()->toDateString() }}"
                        class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-teal-500 focus:outline-none"
                        required
                    />
                </div>
            </div>
        </div>

        {{-- Patient Section --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 space-y-4">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-700 pb-2">
                {{ __('prescriptions.fields.patient') }}
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1">
                        Phone Number
                    </label>
                    <div class="flex gap-2">
                        <input
                            type="text"
                            wire:model="patientPhone"
                            placeholder="+251911..."
                            class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-teal-500 focus:outline-none"
                        />
                        <button
                            type="button"
                            wire:click="lookupPatient"
                            class="px-3 py-2 bg-slate-100 dark:bg-slate-700 border border-slate-300 dark:border-slate-600 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition whitespace-nowrap"
                        >
                            Look up
                        </button>
                    </div>
                    @if($customerFoundName)
                        <p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                            ✓ Found: {{ $customerFoundName }}
                        </p>
                    @endif
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1">
                        Patient Name <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        wire:model="patientName"
                        placeholder="Enter name if not in system"
                        class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-teal-500 focus:outline-none"
                    />
                </div>
            </div>
        </div>

        {{-- Product Lines --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 space-y-4">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-700 pb-2">
                Prescription Lines
            </h2>

            <div class="space-y-3">
                @foreach($lines as $i => $line)
                    <div class="p-4 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            {{-- Product Search --}}
                            <div class="relative sm:col-span-1">
                                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1">
                                    {{ __('prescriptions.fields.product') }} <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="lines.{{ $i }}.search"
                                    wire:change="searchProduct({{ $i }})"
                                    placeholder="Search Rx/controlled..."
                                    class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-lg text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-teal-500 focus:outline-none"
                                />
                                @if(!empty($line['results']))
                                    <div class="absolute z-10 mt-1 w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                        @foreach($line['results'] as $result)
                                            <button
                                                type="button"
                                                wire:click="selectProduct({{ $i }}, {{ $result['id'] }}, '{{ addslashes($result['brand'] . ' ' . $result['strength']) }}')"
                                                class="w-full text-left px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-700 text-sm border-b border-slate-100 dark:border-slate-700 last:border-b-0"
                                            >
                                                <span class="font-medium text-slate-900 dark:text-white">{{ $result['brand'] }}</span>
                                                <span class="text-slate-500 dark:text-slate-400 text-xs"> – {{ $result['generic_name'] }} {{ $result['strength'] }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                                @if($line['product_id'])
                                    <p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">✓ Selected</p>
                                @endif
                            </div>

                            {{-- Dose --}}
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1">
                                    {{ __('prescriptions.fields.dose') }} <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    wire:model="lines.{{ $i }}.dose"
                                    placeholder="e.g. 500mg twice daily"
                                    class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-lg text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-teal-500 focus:outline-none"
                                />
                            </div>

                            {{-- Quantity --}}
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1">
                                    {{ __('prescriptions.fields.quantity_prescribed') }} <span class="text-rose-500">*</span>
                                </label>
                                <div class="flex gap-2">
                                    <input
                                        type="number"
                                        wire:model="lines.{{ $i }}.quantity"
                                        min="1"
                                        class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-lg text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-teal-500 focus:outline-none"
                                    />
                                    @if(count($lines) > 1)
                                        <button
                                            type="button"
                                            wire:click="removeLine({{ $i }})"
                                            class="px-2 py-2 text-rose-500 hover:text-rose-700 transition"
                                            title="Remove line"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <button
                type="button"
                wire:click="addLine"
                class="inline-flex items-center gap-1 text-sm font-medium text-teal-600 hover:text-teal-800 dark:text-teal-400 transition"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Another Product
            </button>
        </div>

        {{-- Scan Upload --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 space-y-4">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-700 pb-2">
                {{ __('prescriptions.fields.scan') }} <span class="text-xs font-normal text-slate-500">(optional)</span>
            </h2>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1">
                    Upload image or PDF (max 5 MB)
                </label>
                <input
                    type="file"
                    wire:model="scan"
                    accept="image/*,application/pdf"
                    class="block w-full text-sm text-slate-700 dark:text-slate-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100 dark:file:bg-teal-950 dark:file:text-teal-300"
                />
                @if($scan)
                    <p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">✓ File selected: {{ $scan->getClientOriginalName() }}</p>
                @endif
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('prescriptions.index') }}" class="px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                Cancel
            </a>
            <button
                type="submit"
                class="px-5 py-2 rounded-lg text-sm font-medium text-white bg-teal-600 hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 transition shadow-sm"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove>Register Prescription</span>
                <span wire:loading>Registering...</span>
            </button>
        </div>
    </form>
</div>
