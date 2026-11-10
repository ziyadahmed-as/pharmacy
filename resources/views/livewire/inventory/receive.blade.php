<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('catalogue.receive_stock') }}</h1>
            <p class="text-sm text-gray-500">{{ __('catalogue.receive_stock_subtitle') }}</p>
        </div>
        <a href="{{ route('catalogue.index') }}" class="text-sm text-gray-600 hover:text-gray-900 font-medium">
            &larr; {{ __('common.back_to_list') }}
        </a>
    </div>

    @if ($errors->any())
        <div class="p-4 bg-red-50 border-l-4 border-red-500 text-red-700 text-sm">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form wire:submit.prevent="submit" class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm space-y-6">
        <!-- Header details -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 border-b border-gray-200 pb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('catalogue.supplier') }} *</label>
                <input type="text" wire:model="supplier_name" placeholder="e.g. EPSS, Medtech" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('catalogue.delivery_note_no') }}</label>
                <input type="text" wire:model="delivery_note_no" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('common.date') }} *</label>
                <input type="date" wire:model="receipt_date" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" required>
            </div>
        </div>

        <!-- Receipt Lines -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900">{{ __('catalogue.receipt_items') }}</h3>
                <button type="button" wire:click="addLine" class="px-3 py-1 bg-indigo-50 text-indigo-700 text-xs font-semibold rounded hover:bg-indigo-100">
                    + {{ __('catalogue.add_line') }}
                </button>
            </div>

            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="bg-gray-50 text-gray-500 uppercase">
                        <tr>
                            <th class="px-3 py-2 text-left">{{ __('catalogue.product_name') }} *</th>
                            <th class="px-3 py-2 text-left">{{ __('catalogue.batch_no') }} *</th>
                            <th class="px-3 py-2 text-left">{{ __('catalogue.mfg_date') }}</th>
                            <th class="px-3 py-2 text-left">{{ __('catalogue.expiry_date') }} *</th>
                            <th class="px-3 py-2 text-left">{{ __('catalogue.quantity') }} *</th>
                            @if ($canViewCost)
                                <th class="px-3 py-2 text-left">{{ __('catalogue.unit_cost') }} (ETB)</th>
                            @endif
                            <th class="px-3 py-2 text-left">{{ __('catalogue.condition_flag') }}</th>
                            <th class="px-3 py-2 text-center">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach ($lines as $index => $line)
                            <tr>
                                <td class="p-2 w-1/4">
                                    <select wire:model="lines.{{ $index }}.product_id" class="w-full text-xs rounded-md border-gray-300 shadow-sm" required>
                                        <option value="">-- {{ __('catalogue.select_product') }} --</option>
                                        @foreach ($products as $p)
                                            <option value="{{ $p->id }}">{{ $p->brand }} ({{ $p->generic_name }})</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="p-2">
                                    <input type="text" wire:model="lines.{{ $index }}.batch_no" placeholder="Batch #" class="w-full text-xs rounded-md border-gray-300 font-mono" required>
                                </td>
                                <td class="p-2">
                                    <input type="date" wire:model="lines.{{ $index }}.manufacturing_date" class="w-full text-xs rounded-md border-gray-300">
                                </td>
                                <td class="p-2">
                                    <input type="date" wire:model="lines.{{ $index }}.expiry_date" class="w-full text-xs rounded-md border-gray-300" required>
                                </td>
                                <td class="p-2 w-20">
                                    <input type="number" min="1" wire:model="lines.{{ $index }}.quantity" class="w-full text-xs rounded-md border-gray-300" required>
                                </td>
                                @if ($canViewCost)
                                    <td class="p-2 w-24">
                                        <input type="number" step="0.01" wire:model="lines.{{ $index }}.unit_cost" placeholder="0.00" class="w-full text-xs rounded-md border-gray-300">
                                    </td>
                                @endif
                                <td class="p-2 w-24">
                                    <select wire:model="lines.{{ $index }}.flag" class="w-full text-xs rounded-md border-gray-300">
                                        <option value="ok">{{ __('catalogue.flag_ok') }}</option>
                                        <option value="short">{{ __('catalogue.flag_short') }}</option>
                                        <option value="over">{{ __('catalogue.flag_over') }}</option>
                                        <option value="damaged">{{ __('catalogue.flag_damaged') }}</option>
                                    </select>
                                </td>
                                <td class="p-2 text-center">
                                    @if (count($lines) > 1)
                                        <button type="button" wire:click="removeLine({{ $index }})" class="text-red-500 hover:text-red-700">
                                            &times;
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Shelf life override section (PM / Owner) -->
        <div class="border-t border-gray-200 pt-4">
            <label class="flex items-center space-x-2">
                <input type="checkbox" wire:model.live="overrideShelfLife" class="rounded border-gray-300 text-indigo-600">
                <span class="text-xs font-medium text-gray-700">{{ __('catalogue.override_shelf_life_checkbox') }}</span>
            </label>
            @if ($overrideShelfLife)
                <div class="mt-2">
                    <input type="text" wire:model="overrideReason" placeholder="{{ __('catalogue.override_shelf_life_reason_placeholder') }}" class="w-full text-xs rounded-md border-gray-300">
                </div>
            @endif
        </div>

        <div class="flex justify-end space-x-3 border-t border-gray-200 pt-6">
            <button type="submit" wire:loading.attr="disabled" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-sm rounded-md shadow-sm transition disabled:opacity-50">
                <span wire:loading.remove>{{ __('catalogue.submit_receipt') }}</span>
                <span wire:loading>{{ __('common.please_wait') }}</span>
            </button>
        </div>
    </form>
</div>

