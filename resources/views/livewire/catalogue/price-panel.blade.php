<div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
    <div class="flex items-center justify-between pb-4 border-b border-gray-200">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">{{ __('catalogue.selling_price') }}</h3>
            <p class="text-sm text-gray-500">{{ __('catalogue.selling_price_description') }}</p>
        </div>
        @if ($canSetPrice)
            <button 
                type="button" 
                wire:click="openChangeModal" 
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md shadow-sm transition">
                {{ __('catalogue.change_price') }}
            </button>
        @endif
    </div>

    @if ($product->units->isNotEmpty())
        <div class="mt-4 flex items-center space-x-2">
            <label class="text-sm font-medium text-gray-700">{{ __('catalogue.unit') }}:</label>
            <select wire:model.live="selectedUnitId" class="text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">{{ __('catalogue.base_unit') }} ({{ $product->unit }})</option>
                @foreach ($product->units as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->name }} (x{{ $unit->factor }})</option>
                @endforeach
            </select>
        </div>
    @endif

    <!-- Current Price Display -->
    <div class="mt-6 p-4 bg-gray-50 rounded-lg flex items-baseline justify-between">
        <div>
            <span class="text-xs uppercase font-medium tracking-wider text-gray-500">{{ __('catalogue.current_effective_price') }}</span>
            <div class="text-3xl font-bold text-gray-900 mt-1">
                @if ($currentPrice)
                    {{ number_format($currentPrice->price / 100, 2) }} <span class="text-sm font-normal text-gray-500">ETB</span>
                @else
                    <span class="text-gray-400 font-normal text-xl">{{ __('catalogue.no_price_set') }}</span>
                @endif
            </div>
        </div>
        @if ($currentPrice)
            <div class="text-right text-xs text-gray-500">
                <div>{{ __('catalogue.effective_from') }}: {{ local_time($currentPrice->effective_from) }}</div>
                <div>{{ __('catalogue.reason') }}: {{ $currentPrice->reason }}</div>
            </div>
        @endif
    </div>

    <!-- Price History -->
    <div class="mt-6">
        <h4 class="text-sm font-medium text-gray-900 mb-3">{{ __('catalogue.price_history') }}</h4>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">
                        <th class="px-4 py-2">{{ __('catalogue.price') }}</th>
                        <th class="px-4 py-2">{{ __('catalogue.effective_from') }}</th>
                        <th class="px-4 py-2">{{ __('catalogue.reason') }}</th>
                        <th class="px-4 py-2">{{ __('catalogue.set_by') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse ($history as $h)
                        <tr>
                            <td class="px-4 py-2 font-medium text-gray-900">{{ number_format($h->price / 100, 2) }} ETB</td>
                            <td class="px-4 py-2 text-gray-600">{{ local_time($h->effective_from) }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $h->reason }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $h->user?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-center text-gray-500 italic">{{ __('catalogue.no_price_history') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Form -->
    @if ($showChangeModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full shadow-xl">
                <h3 class="text-lg font-bold text-gray-900 mb-4">{{ __('catalogue.change_selling_price') }}</h3>

                <form wire:submit.prevent="savePrice" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('catalogue.new_price') }} (ETB)</label>
                        <input type="number" step="0.01" wire:model="price" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="0.00" required>
                        @error('price') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('catalogue.effective_from') }}</label>
                        <input type="datetime-local" wire:model="effectiveFrom" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                        @error('effectiveFrom') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('catalogue.reason') }}</label>
                        <input type="text" wire:model="reason" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="e.g. Supplier price adjustment" required>
                        @error('reason') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" wire:click="$set('showChangeModal', false)" class="px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50">
                            {{ __('common.cancel') }}
                        </button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md shadow-sm">
                            {{ __('common.save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
