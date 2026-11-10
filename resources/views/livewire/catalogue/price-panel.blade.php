<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
        <div>
            <h3 class="text-base font-bold text-slate-900">{{ __('catalogue.selling_price') }}</h3>
            <p class="text-xs text-slate-500 mt-0.5">{{ __('catalogue.selling_price_description') }}</p>
        </div>
        @if ($canSetPrice)
            <button 
                type="button" 
                wire:click="openChangeModal" 
                class="inline-flex items-center gap-1.5 rounded-xl bg-teal-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                {{ __('catalogue.change_price') }}
            </button>
        @endif
    </div>

    @if ($product->units->isNotEmpty())
        <div class="flex items-center gap-2">
            <label class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('catalogue.unit') }}:</label>
            <select wire:model.live="selectedUnitId" class="text-xs font-medium rounded-lg border border-slate-300 py-1.5 px-2.5 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                <option value="">{{ __('catalogue.base_unit') }} ({{ $product->unit }})</option>
                @foreach ($product->units as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->name }} (x{{ $unit->factor }})</option>
                @endforeach
            </select>
        </div>
    @endif

    <!-- Current Price Display Card -->
    <div class="rounded-xl border border-teal-100 bg-gradient-to-br from-teal-50/70 to-teal-50/20 p-5 flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-3">
        <div>
            <span class="text-xs font-semibold uppercase tracking-wider text-teal-800">{{ __('catalogue.current_effective_price') }}</span>
            <div class="text-3xl font-extrabold text-teal-950 mt-1">
                @if ($currentPrice)
                    {{ number_format($currentPrice->price / 100, 2) }} <span class="text-sm font-normal text-teal-700">ETB</span>
                @else
                    <span class="text-slate-400 font-normal text-xl">{{ __('catalogue.no_price_set') }}</span>
                @endif
            </div>
        </div>
        @if ($currentPrice)
            <div class="text-xs text-teal-800 space-y-0.5">
                <div><span class="text-teal-600 font-medium">{{ __('catalogue.effective_from') }}:</span> {{ local_time($currentPrice->effective_from) }}</div>
                <div><span class="text-teal-600 font-medium">{{ __('catalogue.reason') }}:</span> {{ $currentPrice->reason }}</div>
            </div>
        @endif
    </div>

    <!-- Price History -->
    <div>
        <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2.5">{{ __('catalogue.price_history') }}</h4>
        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-xs">
                <thead class="bg-slate-50/75 uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-3.5 py-2.5 text-left font-semibold">{{ __('catalogue.price') }}</th>
                        <th class="px-3.5 py-2.5 text-left font-semibold">{{ __('catalogue.effective_from') }}</th>
                        <th class="px-3.5 py-2.5 text-left font-semibold">{{ __('catalogue.reason') }}</th>
                        <th class="px-3.5 py-2.5 text-left font-semibold">{{ __('catalogue.set_by') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($history as $h)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-3.5 py-2.5 font-bold text-slate-900">{{ number_format($h->price / 100, 2) }} ETB</td>
                            <td class="px-3.5 py-2.5 text-slate-600">{{ local_time($h->effective_from) }}</td>
                            <td class="px-3.5 py-2.5 text-slate-600">{{ $h->reason }}</td>
                            <td class="px-3.5 py-2.5 text-slate-600">{{ $h->user?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3.5 py-4 text-center text-slate-400 italic">{{ __('catalogue.no_price_history') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Form -->
    @if ($showChangeModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-slate-200">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-900">{{ __('catalogue.change_selling_price') }}</h3>
                    <button type="button" wire:click="$set('showChangeModal', false)" class="text-slate-400 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form wire:submit.prevent="savePrice" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.new_price') }} (ETB) <span class="text-rose-500">*</span></label>
                        <input type="number" step="0.01" wire:model="price" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition" placeholder="0.00" required>
                        @error('price') <span class="text-rose-600 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.effective_from') }} <span class="text-rose-500">*</span></label>
                        <input type="datetime-local" wire:model="effectiveFrom" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition" required>
                        @error('effectiveFrom') <span class="text-rose-600 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.reason') }} <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="reason" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition" placeholder="e.g. Supplier price adjustment" required>
                        @error('reason') <span class="text-rose-600 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end gap-2.5 pt-4 border-t border-slate-100">
                        <button type="button" wire:click="$set('showChangeModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                            {{ __('common.cancel') }}
                        </button>
                        <button type="submit" class="rounded-xl bg-teal-600 px-5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition">
                            {{ __('common.save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
