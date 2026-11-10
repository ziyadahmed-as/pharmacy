<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $isEdit ? __('catalogue.edit_product') : __('catalogue.new_product') }}</h1>
            <p class="text-sm text-gray-500">{{ __('catalogue.form_subtitle') }}</p>
        </div>
        <a href="{{ route('catalogue.index') }}" class="text-sm text-gray-600 hover:text-gray-900 font-medium">
            &larr; {{ __('common.back_to_list') }}
        </a>
    </div>

    @if ($duplicateMessage)
        <div class="p-4 bg-amber-50 border-l-4 border-amber-500 rounded-md">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="text-sm font-semibold text-amber-800">{{ __('catalogue.duplicate_warning_title') }}</h4>
                    <p class="text-sm text-amber-700 mt-1">{{ $duplicateMessage }}</p>
                </div>
                @if ($duplicateProductId)
                    <div class="space-x-2">
                        <a href="{{ route('catalogue.show', $duplicateProductId) }}" class="px-3 py-1.5 bg-amber-600 text-white text-xs font-medium rounded hover:bg-amber-700 shadow-sm">
                            {{ __('catalogue.view_existing_product') }}
                        </a>
                        <button type="button" wire:click="$set('duplicateMessage', null)" class="px-3 py-1.5 border border-amber-300 text-amber-800 text-xs font-medium rounded hover:bg-amber-100">
                            {{ __('common.cancel') }}
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <form wire:submit.prevent="save" class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm space-y-6">
        <!-- Classification Note (STEP 4) -->
        <div class="bg-blue-50 border-l-4 border-blue-400 p-3 text-xs text-blue-700">
            <strong>{{ __('catalogue.note') }}:</strong> {{ __('catalogue.classification_notice') }}
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('catalogue.brand') }} *</label>
                <input type="text" wire:model="brand" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                @error('brand') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('catalogue.generic_name') }} (INN) *</label>
                <input type="text" wire:model="generic_name" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                @error('generic_name') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('catalogue.strength') }}</label>
                <input type="text" wire:model="strength" placeholder="e.g. 500mg, 10mg/ml" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('catalogue.dosage_form') }}</label>
                <input type="text" wire:model="dosage_form" placeholder="e.g. Tablet, Syrup, Injection" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('catalogue.pack_size') }}</label>
                <input type="text" wire:model="pack_size" placeholder="e.g. 10x10, 100ml bottle" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('catalogue.base_unit') }} *</label>
                <input type="text" wire:model="unit" placeholder="e.g. piece, tablet, vial" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('catalogue.classification') }} *</label>
                <select wire:model="classification" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                    <option value="rx">Rx (Prescription)</option>
                    <option value="otc">OTC (Over the counter)</option>
                    <option value="controlled">Controlled Drug</option>
                    <option value="non_drug">Non-Drug / Consumable</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('catalogue.category') }}</label>
                <select wire:model="category_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('common.none') }}</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('catalogue.barcode') }}</label>
                <input type="text" wire:model="barcode" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('catalogue.manufacturer') }}</label>
                <input type="text" wire:model="manufacturer" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('catalogue.storage_condition') }}</label>
                <input type="text" wire:model="storage_condition" placeholder="e.g. Below 25°C, Cold chain (2-8°C)" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('catalogue.preferred_supplier') }}</label>
                <input type="text" wire:model="preferred_supplier_name" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
        </div>

        <div class="border-t border-gray-200 pt-4">
            <label class="flex items-center space-x-2">
                <input type="checkbox" wire:model="loose_allowed" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                <span class="text-sm font-medium text-gray-700">{{ __('catalogue.allow_loose_sales') }}</span>
            </label>
        </div>

        <!-- Stock Levels (Manager & Owner Only, Disabled for Pharmacy Staff E2) -->
        <div class="border-t border-gray-200 pt-4">
            <h4 class="text-sm font-semibold text-gray-900 mb-3">{{ __('catalogue.stock_levels') }}</h4>
            @if (! $canManage)
                <p class="text-xs text-amber-600 mb-2">{{ __('catalogue.staff_levels_read_only') }}</p>
            @endif
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700">{{ __('catalogue.min_level') }}</label>
                    <input type="number" wire:model="min_level" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm {{ ! $canManage ? 'bg-gray-100 cursor-not-allowed' : '' }}" {{ ! $canManage ? 'disabled' : '' }}>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">{{ __('catalogue.reorder_level') }}</label>
                    <input type="number" wire:model="reorder_level" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm {{ ! $canManage ? 'bg-gray-100 cursor-not-allowed' : '' }}" {{ ! $canManage ? 'disabled' : '' }}>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">{{ __('catalogue.max_level') }}</label>
                    <input type="number" wire:model="max_level" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm {{ ! $canManage ? 'bg-gray-100 cursor-not-allowed' : '' }}" {{ ! $canManage ? 'disabled' : '' }}>
                </div>
            </div>
        </div>

        <!-- Additional Units Editor -->
        <div class="border-t border-gray-200 pt-4">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-semibold text-gray-900">{{ __('catalogue.alternative_units') }}</h4>
                <button type="button" wire:click="addUnitRow" class="text-xs font-medium text-indigo-600 hover:text-indigo-900">
                    + {{ __('catalogue.add_unit') }}
                </button>
            </div>
            @foreach ($units as $index => $u)
                <div class="flex items-center space-x-3 mb-2">
                    <input type="text" wire:model="units.{{ $index }}.name" placeholder="{{ __('catalogue.unit_name') }} (e.g. box)" class="rounded-md border-gray-300 text-sm shadow-sm w-1/2">
                    <input type="number" wire:model="units.{{ $index }}.factor" placeholder="{{ __('catalogue.factor_to_base') }}" class="rounded-md border-gray-300 text-sm shadow-sm w-1/3">
                    <button type="button" wire:click="removeUnitRow({{ $index }})" class="text-red-500 hover:text-red-700 text-xs">
                        {{ __('common.remove') }}
                    </button>
                </div>
            @endforeach
        </div>

        <div class="flex justify-end space-x-3 border-t border-gray-200 pt-6">
            <a href="{{ route('catalogue.index') }}" class="px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50">
                {{ __('common.cancel') }}
            </a>
            <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md shadow-sm">
                {{ __('common.save') }}
            </button>
        </div>
    </form>
</div>
