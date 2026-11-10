<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $isEdit ? __('catalogue.edit_product') : __('catalogue.new_product') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('catalogue.form_subtitle') }}</p>
        </div>
        <a href="{{ route('catalogue.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-slate-900 transition">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            {{ __('common.back_to_list') }}
        </a>
    </div>

    @if ($duplicateMessage)
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-start gap-3">
                    <svg class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <div>
                        <h4 class="text-sm font-bold text-amber-900">{{ __('catalogue.duplicate_warning_title') }}</h4>
                        <p class="mt-0.5 text-xs text-amber-800">{{ $duplicateMessage }}</p>
                    </div>
                </div>
                @if ($duplicateProductId)
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="{{ route('catalogue.show', $duplicateProductId) }}" class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-amber-700 transition">
                            {{ __('catalogue.view_existing_product') }}
                        </a>
                        <button type="button" wire:click="$set('duplicateMessage', null)" class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100 transition">
                            {{ __('common.cancel') }}
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <form wire:submit.prevent="save" class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-8">
        {{-- Classification Notice --}}
        <div class="flex items-start gap-3 rounded-xl border border-teal-200 bg-teal-50/70 p-3.5 text-xs text-teal-900">
            <svg class="h-4 w-4 text-teal-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                <strong>{{ __('catalogue.note') }}:</strong> {{ __('catalogue.classification_notice') }}
            </div>
        </div>

        {{-- Section 1: Basic Identification --}}
        <div>
            <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                <svg class="h-4 w-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800">Product Identification</h3>
            </div>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.brand') }} <span class="text-rose-500">*</span></label>
                    <input type="text" wire:model="brand" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition" required>
                    @error('brand') <span class="text-xs text-rose-600 mt-1 block font-medium">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.generic_name') }} (INN) <span class="text-rose-500">*</span></label>
                    <input type="text" wire:model="generic_name" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition" required>
                    @error('generic_name') <span class="text-xs text-rose-600 mt-1 block font-medium">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.strength') }}</label>
                    <input type="text" wire:model="strength" placeholder="e.g. 500mg, 10mg/ml" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm placeholder:text-slate-400 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.dosage_form') }}</label>
                    <input type="text" wire:model="dosage_form" placeholder="e.g. Tablet, Syrup, Injection" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm placeholder:text-slate-400 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.pack_size') }}</label>
                    <input type="text" wire:model="pack_size" placeholder="e.g. 10x10, 100ml bottle" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm placeholder:text-slate-400 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.base_unit') }} <span class="text-rose-500">*</span></label>
                    <input type="text" wire:model="unit" placeholder="e.g. piece, tablet, vial" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm placeholder:text-slate-400 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition" required>
                </div>
            </div>
        </div>

        {{-- Section 2: Classification & Logistics --}}
        <div>
            <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                <svg class="h-4 w-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800">Classification & Sourcing</h3>
            </div>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.classification') }} <span class="text-rose-500">*</span></label>
                    <select wire:model="classification" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition" required>
                        <option value="rx">Rx (Prescription)</option>
                        <option value="otc">OTC (Over the counter)</option>
                        <option value="controlled">Controlled Drug</option>
                        <option value="non_drug">Non-Drug / Consumable</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.category') }}</label>
                    <select wire:model="category_id" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                        <option value="">{{ __('common.none') }}</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.barcode') }}</label>
                    <input type="text" wire:model="barcode" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 font-mono text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.manufacturer') }}</label>
                    <input type="text" wire:model="manufacturer" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.storage_condition') }}</label>
                    <input type="text" wire:model="storage_condition" placeholder="e.g. Below 25°C, Cold chain (2-8°C)" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm placeholder:text-slate-400 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.preferred_supplier') }}</label>
                    <input type="text" wire:model="preferred_supplier_name" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                </div>
            </div>

            <div class="mt-4 pt-3">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="loose_allowed" class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                    <span class="text-sm font-medium text-slate-700">{{ __('catalogue.allow_loose_sales') }}</span>
                </label>
            </div>
        </div>

        {{-- Section 3: Stock Levels (Manager & Owner Only) --}}
        <div>
            <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                <svg class="h-4 w-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800">{{ __('catalogue.stock_levels') }}</h3>
            </div>

            @if (! $canManage)
                <div class="mt-3 rounded-lg bg-amber-50 p-2.5 text-xs text-amber-700 font-medium">
                    {{ __('catalogue.staff_levels_read_only') }}
                </div>
            @endif

            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.min_level') }}</label>
                    <input type="number" wire:model="min_level" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition {{ ! $canManage ? 'bg-slate-100 cursor-not-allowed text-slate-500' : '' }}" {{ ! $canManage ? 'disabled' : '' }}>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.reorder_level') }}</label>
                    <input type="number" wire:model="reorder_level" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition {{ ! $canManage ? 'bg-slate-100 cursor-not-allowed text-slate-500' : '' }}" {{ ! $canManage ? 'disabled' : '' }}>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.max_level') }}</label>
                    <input type="number" wire:model="max_level" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition {{ ! $canManage ? 'bg-slate-100 cursor-not-allowed text-slate-500' : '' }}" {{ ! $canManage ? 'disabled' : '' }}>
                </div>
            </div>
        </div>

        {{-- Section 4: Alternative Units Editor --}}
        <div>
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7c0-2-1-3-3-3H7C5 4 4 5 4 7z"/></svg>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800">{{ __('catalogue.alternative_units') }}</h3>
                </div>
                <button type="button" wire:click="addUnitRow" class="inline-flex items-center gap-1 rounded-lg bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-700 hover:bg-teal-100 transition">
                    + {{ __('catalogue.add_unit') }}
                </button>
            </div>

            <div class="mt-4 space-y-2">
                @forelse ($units as $index => $u)
                    <div class="flex items-center gap-3">
                        <input type="text" wire:model="units.{{ $index }}.name" placeholder="{{ __('catalogue.unit_name') }} (e.g. box)" class="w-1/2 rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                        <input type="number" wire:model="units.{{ $index }}.factor" placeholder="{{ __('catalogue.factor_to_base') }}" class="w-1/3 rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                        <button type="button" wire:click="removeUnitRow({{ $index }})" class="p-2 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition" title="{{ __('common.remove') }}">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                @empty
                    <p class="text-xs text-slate-400 italic py-2">No additional units defined. Uses base unit only.</p>
                @endforelse
            </div>
        </div>

        {{-- Form Actions --}}
        <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-100">
            <a href="{{ route('catalogue.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
                {{ __('common.cancel') }}
            </a>
            <button type="submit" class="rounded-xl bg-teal-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition">
                {{ __('common.save') }}
            </button>
        </div>
    </form>
</div>
