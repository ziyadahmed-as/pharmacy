<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('catalogue.request_stock_adjustment') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('catalogue.adjustments_subtitle') }}</p>
        </div>
        <a href="{{ route('inventory.adjustments.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-slate-900 transition">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            {{ __('common.back_to_list') }}
        </a>
    </div>

    @if ($errorMessage)
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" role="alert">
            <div class="flex items-center gap-2 font-semibold">
                <svg class="h-5 w-5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>{{ $errorMessage }}</span>
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" role="alert">
            <div class="flex items-center gap-2 font-semibold mb-1">
                <svg class="h-5 w-5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>Please correct the errors below:</span>
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-xs text-rose-700">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form wire:submit.prevent="submit" class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-6">
        <!-- 1. Select Product -->
        <div>
            <label class="block text-sm font-semibold text-slate-800">
                {{ __('catalogue.product') }} <span class="text-rose-500">*</span>
            </label>
            <div class="relative mt-1">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="product_search"
                    placeholder="Search product by name, brand, or SKU..."
                    class="block w-full rounded-xl border border-slate-300 py-2.5 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20"
                />

                @if ($this->matchingProducts->isNotEmpty())
                    <div class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-xl border border-slate-200 bg-white shadow-lg">
                        <ul class="divide-y divide-slate-100 py-1">
                            @foreach ($this->matchingProducts as $prod)
                                <li>
                                    <button
                                        type="button"
                                        wire:click="selectProduct({{ $prod->id }})"
                                        class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm hover:bg-teal-50 transition"
                                    >
                                        <div>
                                            <p class="font-medium text-slate-900">{{ $prod->brand }} {{ $prod->strength }}</p>
                                            <p class="text-xs text-slate-500">{{ $prod->generic_name }} &bull; {{ $prod->dosage_form }}</p>
                                        </div>
                                        @if ($prod->classification === 'controlled')
                                            <span class="inline-flex items-center rounded-full bg-purple-50 px-2 py-0.5 text-xs font-semibold text-purple-700 border border-purple-200">
                                                Controlled
                                            </span>
                                        @endif
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
            @error('product_id')
                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- 2. Select Batch -->
        @if ($product_id)
            <div>
                <label class="block text-sm font-semibold text-slate-800">
                    {{ __('catalogue.batch') }} <span class="text-rose-500">*</span>
                </label>
                <div class="mt-1">
                    <select
                        wire:model.live="batch_id"
                        class="block w-full rounded-xl border border-slate-300 py-2.5 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20"
                    >
                        <option value="">-- Choose Batch --</option>
                        @foreach ($this->availableBatches as $b)
                            <option value="{{ $b->id }}">
                                Batch #{{ $b->batch_no }} | Exp: {{ $b->expiry_date->format('Y-m-d') }} | Sellable: {{ $b->sellableStockQuantity() }} | Cost: {{ number_format($b->unit_cost / 100, 2) }} ETB
                            </option>
                        @endforeach
                    </select>
                </div>
                @error('batch_id')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>
        @endif

        @if ($this->selectedBatch)
            <!-- Batch Info Card -->
            <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                <div>
                    <span class="text-slate-500 block uppercase font-semibold">Current Stock</span>
                    <span class="text-base font-bold text-slate-900">{{ $this->selectedBatch->sellableStockQuantity() }}</span>
                </div>
                <div>
                    <span class="text-slate-500 block uppercase font-semibold">Unit Cost</span>
                    <span class="text-base font-bold text-slate-900">{{ number_format($this->selectedBatch->unit_cost / 100, 2) }} ETB</span>
                </div>
                <div>
                    <span class="text-slate-500 block uppercase font-semibold">Expiry Date</span>
                    <span class="text-base font-bold text-slate-900">{{ $this->selectedBatch->expiry_date->format('M d, Y') }}</span>
                </div>
                <div>
                    <span class="text-slate-500 block uppercase font-semibold">Classification</span>
                    <span class="text-base font-bold {{ $this->isControlled ? 'text-purple-700' : 'text-slate-900' }}">
                        {{ ucfirst($this->selectedBatch->product->classification ?? 'General') }}
                    </span>
                </div>
            </div>

            <!-- 3. Adjustment Details -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-800">
                        {{ __('catalogue.adjustment_reason_type') }} <span class="text-rose-500">*</span>
                    </label>
                    <select
                        wire:model.live="reason_type"
                        class="mt-1 block w-full rounded-xl border border-slate-300 py-2.5 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20"
                    >
                        <option value="damage">{{ __('catalogue.reason_damage') }} (Deduct stock)</option>
                        <option value="loss">{{ __('catalogue.reason_loss') }} (Deduct stock)</option>
                        <option value="expiry_write_off">{{ __('catalogue.reason_expiry_write_off') }} (Deduct stock)</option>
                        <option value="found_stock">{{ __('catalogue.reason_found_stock') }} (Increase stock)</option>
                    </select>
                    @error('reason_type')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-800">
                        {{ __('catalogue.quantity') }} <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="number"
                        min="1"
                        wire:model.live.debounce.300ms="quantity"
                        placeholder="Quantity to adjust"
                        class="mt-1 block w-full rounded-xl border border-slate-300 py-2.5 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20"
                    />
                    @error('quantity')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Projected Stock & Recount Alert -->
            @if ($quantity)
                <div class="rounded-xl p-4 border {{ ($this->projectedStock ?? 0) < 0 ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-teal-50 border-teal-200 text-teal-800' }}">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs uppercase font-semibold">Projected Balance After Adjustment</p>
                            <p class="text-lg font-bold">
                                {{ $this->projectedStock }} units
                                <span class="text-xs font-normal">
                                    ({{ $this->signedQuantity > 0 ? '+'.$this->signedQuantity : $this->signedQuantity }})
                                </span>
                            </p>
                        </div>
                        @if ($this->requiresRecountEstimated)
                            <div class="text-right">
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-900 border border-amber-300">
                                    <svg class="h-3.5 w-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    {{ __('catalogue.recount_required_before_approval') }}
                                </span>
                                <p class="text-[11px] text-amber-700 mt-0.5">Policy BR-07: high-value adjustment threshold</p>
                            </div>
                        @endif
                    </div>
                    @if (($this->projectedStock ?? 0) < 0)
                        <p class="text-xs font-semibold text-rose-700 mt-2">
                            Warning: Adjustment cannot make stock negative (E2).
                        </p>
                    @endif
                </div>
            @endif

            <!-- 4. Controlled Drug Witness (STEP 5 / UC-22 rule) -->
            @if ($this->isControlled)
                <div class="rounded-xl border border-purple-200 bg-purple-50/60 p-4 space-y-2">
                    <div class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        <h4 class="text-sm font-bold text-purple-900">{{ __('controlled_drugs.second_person') }} (UC-22 Mandatory Witness)</h4>
                    </div>
                    <p class="text-xs text-purple-700">
                        This is a controlled medication. In compliance with Ethiopian regulatory policy and UC-22 rule E2, any stock movement or adjustment requires a second named person recorded in the register.
                    </p>
                    <div class="mt-2">
                        <select
                            wire:model="second_person_id"
                            class="block w-full rounded-xl border border-purple-300 py-2.5 px-3 text-sm focus:border-purple-600 focus:outline-none focus:ring-2 focus:ring-purple-500/20"
                            required
                        >
                            <option value="">-- Select Witnessing Staff Member --</option>
                            @foreach ($this->witnessUsers as $witness)
                                <option value="{{ $witness->id }}">
                                    {{ $witness->name }} ({{ $witness->roles->pluck('name')->implode(', ') }})
                                </option>
                            @endforeach
                        </select>
                        @error('second_person_id')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            @endif

            <!-- 5. Written Reason -->
            <div>
                <label class="block text-sm font-semibold text-slate-800">
                    {{ __('catalogue.written_reason') }} <span class="text-rose-500">*</span>
                </label>
                <textarea
                    wire:model="written_reason"
                    rows="3"
                    placeholder="Provide detailed justification for this adjustment (e.g., broken bottle during shelf cleaning, discrepancy found during weekly count)..."
                    class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20"
                    required
                ></textarea>
                @error('written_reason')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Submit buttons -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <a
                    href="{{ route('inventory.adjustments.index') }}"
                    wire:navigate
                    class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition"
                >
                    {{ __('common.cancel') }}
                </a>
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition disabled:opacity-50"
                >
                    <span wire:loading.remove>{{ __('catalogue.submit_adjustment_request') }}</span>
                    <span wire:loading>{{ __('common.processing') }}</span>
                </button>
            </div>
        @endif
    </form>
</div>
