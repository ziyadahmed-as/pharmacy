<div class="max-w-4xl mx-auto space-y-6" x-data="{ clinicalOpen: @entangle('clinicalOpen') }">

    {{-- Page header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                {{ $isEdit ? __('catalogue.edit_product') : __('catalogue.new_product') }}
            </h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('catalogue.form_subtitle') }}</p>
        </div>
        <a href="{{ route('catalogue.index') }}"
           class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-slate-900 transition">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            {{ __('common.back_to_list') }}
        </a>
    </div>

    {{-- Success flash --}}
    @if (session('message'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">
            {{ session('message') }}
        </div>
    @endif

    {{-- Duplicate warning --}}
    @if ($duplicateMessage)
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-start gap-3">
                    <svg class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <h4 class="text-sm font-bold text-amber-900">{{ __('catalogue.duplicate_warning_title') }}</h4>
                        <p class="mt-0.5 text-xs text-amber-800">{{ $duplicateMessage }}</p>
                    </div>
                </div>
                @if ($duplicateProductId)
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="{{ route('catalogue.show', $duplicateProductId) }}"
                           class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-amber-700 transition">
                            {{ __('catalogue.view_existing_product') }}
                        </a>
                        <a href="{{ route('catalogue.edit', $duplicateProductId) }}"
                           class="rounded-lg border border-amber-400 bg-white px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-50 transition">
                            {{ __('common.edit') }}
                        </a>
                        <button type="button" wire:click="$set('duplicateMessage', null)"
                                class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100 transition">
                            {{ __('common.cancel') }}
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <form wire:submit.prevent="save" class="space-y-6">

        {{-- Regulatory note --}}
        <div class="flex items-start gap-3 rounded-xl border border-teal-200 bg-teal-50/70 p-3.5 text-xs text-teal-900">
            <svg class="h-4 w-4 text-teal-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div><strong>{{ __('catalogue.note') }}:</strong> {{ __('catalogue.classification_notice') }}</div>
        </div>

        @php $inputCls = 'mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition'; @endphp

        {{-- ================================================================== --}}
        {{-- Section 1: Product Identification                                  --}}
        {{-- ================================================================== --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
            <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                <svg class="h-4 w-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800">{{ __('catalogue.product_identification') }}</h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Brand --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.brand') }} <span class="text-rose-500">*</span></label>
                    <input type="text" wire:model.blur="brand" class="{{ $inputCls }}">
                    @error('brand') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                {{-- Product Code --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.product_code') }}</label>
                    <input type="text" wire:model="product_code" placeholder="{{ __('catalogue.product_code_auto_hint') }}"
                           class="{{ $inputCls }} font-mono">
                    <p class="mt-0.5 text-[10px] text-slate-400">{{ __('catalogue.product_code_auto_note') }}</p>
                    @error('product_code') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                {{-- Generic Name --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.generic_name') }} (INN) <span class="text-rose-500">*</span></label>
                    <input type="text" wire:model.blur="generic_name" class="{{ $inputCls }}">
                    @error('generic_name') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                {{-- Strength --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.strength') }}</label>
                    <input type="text" wire:model="strength" placeholder="e.g. 500mg" class="{{ $inputCls }}">
                </div>
                {{-- Dosage Form --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.dosage_form') }}</label>
                    <input type="text" wire:model="dosage_form" placeholder="e.g. Tablet, Syrup" class="{{ $inputCls }}">
                </div>
                {{-- Pack Size --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.pack_size') }}</label>
                    <input type="text" wire:model="pack_size" placeholder="e.g. 10x10" class="{{ $inputCls }}">
                </div>
                {{-- Base Unit (locked if product has stock) --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                        {{ __('catalogue.base_unit') }} <span class="text-rose-500">*</span>
                        @if ($this->unitIsLocked)
                            <span class="ml-1 text-[10px] font-normal text-amber-600">🔒 {{ __('catalogue.locked') }}</span>
                        @endif
                    </label>
                    <input type="text" wire:model="unit"
                           placeholder="e.g. piece, tablet, vial"
                           class="{{ $inputCls }} {{ $this->unitIsLocked ? 'bg-slate-100 cursor-not-allowed text-slate-500' : '' }}"
                           @if($this->unitIsLocked) readonly @endif>
                    @if($this->unitIsLocked)
                        <p class="mt-0.5 text-[10px] text-amber-600">{{ __('catalogue.base_unit_locked') }}</p>
                    @endif
                    @error('unit') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- ================================================================== --}}
        {{-- Section 2: Classification & Sourcing                               --}}
        {{-- ================================================================== --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
            <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                <svg class="h-4 w-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                </svg>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800">{{ __('catalogue.classification_sourcing') }}</h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Classification --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.classification') }} <span class="text-rose-500">*</span></label>
                    <select wire:model.live="classification" class="{{ $inputCls }}">
                        <option value="rx">Rx ({{ __('catalogue.prescription') }})</option>
                        <option value="otc">OTC ({{ __('catalogue.over_the_counter') }})</option>
                        <option value="controlled">{{ __('catalogue.controlled_drug') }}</option>
                        <option value="non_drug">{{ __('catalogue.non_drug_consumable') }}</option>
                    </select>
                    @error('classification') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                {{-- Category --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.category') }} <span class="text-rose-500">*</span></label>
                    <select wire:model="category_id" class="{{ $inputCls }}">
                        <option value="">{{ __('common.none') }}</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                {{-- Barcode --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.barcode') }}</label>
                    <input type="text" wire:model.blur="barcode" class="{{ $inputCls }} font-mono">
                    @error('barcode') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                {{-- Manufacturer --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.manufacturer') }}</label>
                    <input type="text" wire:model="manufacturer" class="{{ $inputCls }}">
                </div>
                {{-- Storage Condition --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.storage_condition') }}</label>
                    <input type="text" wire:model="storage_condition" placeholder="e.g. Below 25°C" class="{{ $inputCls }}">
                </div>
                {{-- Min Shelf Life --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.min_shelf_life_days') }}</label>
                    <input type="number" wire:model="min_shelf_life_days" placeholder="e.g. 180" min="0" class="{{ $inputCls }}">
                </div>
            </div>
            {{-- Checkboxes --}}
            <div class="flex flex-wrap items-center gap-6 pt-2">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="loose_allowed" class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                    <span class="text-sm font-medium text-slate-700">{{ __('catalogue.allow_loose_sales') }}</span>
                </label>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="is_cold_chain" class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                    <span class="text-sm font-medium text-slate-700">{{ __('catalogue.cold_chain_required') }}</span>
                </label>
            </div>

            {{-- Classification-change confirmation (Edit only) --}}
            @if ($this->classificationChanged)
                <div class="mt-3 rounded-xl border border-amber-300 bg-amber-50 p-4">
                    <p class="text-xs font-semibold text-amber-900 mb-2">{{ __('catalogue.classification_changed_notice') }}</p>
                    <label class="inline-flex items-start gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="classificationConfirmed"
                               class="mt-0.5 h-4 w-4 rounded border-amber-400 text-amber-600 focus:ring-amber-500">
                        <span class="text-xs font-medium text-amber-900">{{ __('catalogue.classification_confirm_checkbox') }}</span>
                    </label>
                    @error('classificationConfirmed') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
            @endif
        </div>

        {{-- ================================================================== --}}
        {{-- Section 3: Pricing                                                 --}}
        {{-- ================================================================== --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                <svg class="h-4 w-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800">{{ __('catalogue.pricing_section_title') }}</h3>
                    <p class="text-xs text-slate-400 font-normal">{{ __('catalogue.pricing_section_subtitle') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Selling Price --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                        {{ __('catalogue.selling_price_per_unit') }}
                        @if (!$isEdit) <span class="text-rose-500">*</span> @endif
                    </label>
                    <div class="mt-1 relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 text-xs font-semibold">ETB</span>
                        <input type="number" step="0.01" min="0.01" wire:model.live="sellingPrice"
                               placeholder="0.00"
                               class="{{ $inputCls }} pl-10">
                    </div>
                    @error('sellingPrice') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror

                    {{-- Price-below-cost warning (non-blocking) --}}
                    @if ($this->priceBelowCostWarning)
                        <div class="mt-1.5 rounded-lg bg-amber-50 border border-amber-200 p-2 text-[11px] text-amber-800 font-medium">
                            ⚠️ {{ $this->priceBelowCostWarning }}
                        </div>
                    @endif
                </div>

                {{-- Effective from --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.price_effective_from') }}</label>
                    <input type="datetime-local" wire:model="sellingPriceEffective" class="{{ $inputCls }}">
                    @error('sellingPriceEffective') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- Reason (required on Edit when price changes) --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                        {{ __('catalogue.price_change_reason') }}
                        @if ($isEdit) <span class="text-rose-500">*</span> @endif
                    </label>
                    <input type="text" wire:model="sellingPriceReason"
                           placeholder="{{ __('catalogue.price_change_reason_placeholder') }}"
                           class="{{ $inputCls }}">
                    @error('sellingPriceReason') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- ================================================================== --}}
        {{-- Section 4: Stock Levels                                            --}}
        {{-- ================================================================== --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                <svg class="h-4 w-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800">{{ __('catalogue.stock_levels') }}</h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.min_level') }}</label>
                    <input type="number" wire:model.blur="min_level" min="0" class="{{ $inputCls }}">
                    @error('min_level') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.reorder_level') }}</label>
                    <input type="number" wire:model.blur="reorder_level" min="0" class="{{ $inputCls }}">
                    @error('reorder_level') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.max_level') }}</label>
                    <input type="number" wire:model.blur="max_level" min="0" class="{{ $inputCls }}">
                    @error('max_level') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
            @if ($min_level > 0 && $reorder_level > 0 && $min_level > $reorder_level)
                <p class="text-xs text-rose-600 font-medium">⚠️ {{ __('catalogue.level_min_gt_reorder') }}</p>
            @elseif ($reorder_level > 0 && $max_level > 0 && $reorder_level > $max_level)
                <p class="text-xs text-rose-600 font-medium">⚠️ {{ __('catalogue.level_reorder_gt_max') }}</p>
            @endif
        </div>

        {{-- ================================================================== --}}
        {{-- Section 5: Alternative Packaging Units                             --}}
        {{-- ================================================================== --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7c0-2-1-3-3-3H7C5 4 4 5 4 7z"/>
                    </svg>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800">{{ __('catalogue.alternative_units') }}</h3>
                </div>
                <button type="button" wire:click="addUnitRow"
                        class="inline-flex items-center gap-1 rounded-lg bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-700 hover:bg-teal-100 transition">
                    + {{ __('catalogue.add_unit') }}
                </button>
            </div>
            <div class="space-y-2">
                @forelse ($units as $index => $u)
                    <div class="flex items-center gap-3">
                        <input type="text" wire:model="units.{{ $index }}.name"
                               placeholder="{{ __('catalogue.unit_name') }} (e.g. box)"
                               class="w-5/12 rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                        <span class="text-slate-400 text-xs shrink-0">= </span>
                        <input type="number" wire:model="units.{{ $index }}.factor"
                               placeholder="{{ __('catalogue.factor_to_base') }}"
                               min="1"
                               class="w-3/12 rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                        <span class="text-slate-400 text-xs shrink-0">{{ $unit ?: 'base' }}</span>
                        <button type="button" wire:click="removeUnitRow({{ $index }})"
                                class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                @empty
                    <p class="text-xs text-slate-400 italic py-2">{{ __('catalogue.no_alternative_units') }}</p>
                @endforelse
            </div>
        </div>

        {{-- ================================================================== --}}
        {{-- Section 6: Product Image                                           --}}
        {{-- ================================================================== --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                <svg class="h-4 w-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800">{{ __('catalogue.image') }}</h3>
                <span class="text-xs text-slate-400 font-normal">(JPG · PNG · WEBP · max 2 MB)</span>
            </div>
            <div class="flex items-start gap-6">
                {{-- Preview --}}
                <div class="shrink-0">
                    @if ($imageFile)
                        <img src="{{ $imageFile->temporaryUrl() }}" alt="{{ __('catalogue.image') }}"
                             class="h-20 w-20 rounded-xl object-cover border-2 border-teal-400 shadow">
                        <p class="mt-1 text-[10px] text-teal-600 font-medium text-center">{{ __('common.preview') }}</p>
                    @elseif ($currentThumbnailPath)
                        <img src="{{ \App\Services\ProductImageService::thumbnailUrl($currentThumbnailPath) }}"
                             alt="{{ __('catalogue.image') }}"
                             class="h-20 w-20 rounded-xl object-cover border border-slate-200 shadow-sm">
                        <p class="mt-1 text-[10px] text-slate-400 text-center">{{ __('catalogue.current_image') }}</p>
                    @else
                        <div class="h-20 w-20 rounded-xl border-2 border-dashed border-slate-300 flex items-center justify-center bg-slate-50">
                            <svg class="h-7 w-7 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <p class="mt-1 text-[10px] text-slate-400 text-center">{{ __('catalogue.no_image') }}</p>
                    @endif
                </div>
                {{-- Upload --}}
                <div class="flex-1 space-y-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1">
                            {{ $currentThumbnailPath || $imageFile ? __('catalogue.replace_image') : __('catalogue.upload_image') }}
                        </label>
                        <input type="file" wire:model="imageFile" accept="image/jpeg,image/png,image/webp"
                               class="block w-full text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-teal-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-teal-700 hover:file:bg-teal-100 transition">
                        <div wire:loading wire:target="imageFile" class="mt-1 text-xs text-teal-600 animate-pulse">{{ __('common.uploading') }}…</div>
                        @error('imageFile') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    @if ($currentThumbnailPath && ! $imageFile)
                        <button type="button" wire:click="removeImage" wire:confirm="{{ __('catalogue.confirm_remove_image') }}"
                                class="inline-flex items-center gap-1.5 text-xs font-semibold text-rose-600 hover:text-rose-800 transition">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            {{ __('catalogue.remove_image') }}
                        </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- ================================================================== --}}
        {{-- Section 7a: First Batch (Add screen)                               --}}
        {{-- ================================================================== --}}
        @if (! $isEdit)
        <div class="rounded-2xl border {{ $batchStarted ? 'border-teal-300' : 'border-slate-200' }} bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                <svg class="h-4 w-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800">
                        {{ __('catalogue.first_batch_section_title') }}
                        @if ($batchStarted) <span class="text-teal-600 font-normal text-xs ml-1">({{ __('catalogue.required_fields_active') }})</span> @endif
                    </h3>
                    <p class="text-xs text-slate-400 font-normal">{{ __('catalogue.first_batch_section_subtitle') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Batch Number --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                        {{ __('catalogue.batch_no') }} @if($batchStarted)<span class="text-rose-500">*</span>@endif
                    </label>
                    <input type="text" wire:model="batchNo" class="{{ $inputCls }} font-mono"
                           placeholder="{{ __('catalogue.batch_no_auto_hint') }}">
                    <p class="mt-0.5 text-[10px] text-slate-400">{{ __('catalogue.batch_no_auto_note') }}</p>
                    @error('batchNo') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                {{-- Quantity --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                        {{ __('catalogue.quantity') }} @if($batchStarted)<span class="text-rose-500">*</span>@endif
                    </label>
                    <input type="number" wire:model="batchQuantity" min="1" class="{{ $inputCls }}">
                    @error('batchQuantity') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                {{-- Manufactured Date --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                        {{ __('catalogue.mfg_date') }} @if($batchStarted)<span class="text-rose-500">*</span>@endif
                    </label>
                    <input type="date" wire:model.live="batchMfgDate" class="{{ $inputCls }}">
                    @error('batchMfgDate') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                {{-- Expiry Date --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                        {{ __('catalogue.expiry_date') }} @if($batchStarted)<span class="text-rose-500">*</span>@endif
                    </label>
                    <input type="date" wire:model.live="batchExpiryDate" class="{{ $inputCls }}">
                    @error('batchExpiryDate') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                {{-- Buying Price --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                        {{ __('catalogue.buying_price') }} @if($batchStarted)<span class="text-rose-500">*</span>@endif
                    </label>
                    <div class="mt-1 relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 text-xs font-semibold">ETB</span>
                        <input type="number" step="0.01" min="0.01" wire:model.live="batchBuyingPrice"
                               placeholder="0.00" class="{{ $inputCls }} pl-10">
                    </div>
                    @error('batchBuyingPrice') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                {{-- Est. Profit per unit --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.profit_per_unit') }}</label>
                    <div class="mt-1 h-9 flex items-center px-3 rounded-xl bg-slate-50 border border-slate-200 text-sm font-mono font-semibold
                        @php
                            $sp = $sellingPrice !== '' ? (float)$sellingPrice : 0;
                            $bp = $batchBuyingPrice !== '' ? (float)$batchBuyingPrice : 0;
                            $profit = $sp - $bp;
                        @endphp
                        {{ $profit > 0 ? 'text-emerald-700' : ($profit < 0 ? 'text-rose-600' : 'text-slate-400') }}">
                        @if ($sp > 0 && $bp > 0)
                            {{ $profit >= 0 ? '+' : '' }}{{ number_format($profit, 2) }} ETB
                        @else
                            —
                        @endif
                    </div>
                </div>
                {{-- Supplier Name --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.supplier') }}</label>
                    <input type="text" wire:model="batchSupplier" class="{{ $inputCls }}">
                </div>
                {{-- Delivery Note --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.delivery_note_no') }}</label>
                    <input type="text" wire:model="batchDeliveryNo" class="{{ $inputCls }} font-mono">
                </div>
            </div>
        </div>
        @endif

        {{-- ================================================================== --}}
        {{-- Section 7b: Batch Table (Edit screen)                              --}}
        {{-- ================================================================== --}}
        @if ($isEdit)
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800">{{ __('catalogue.batches_section_title') }}</h3>
                        <p class="text-xs text-slate-400">{{ __('catalogue.batches_section_subtitle') }}</p>
                    </div>
                </div>
                <a href="{{ route('inventory.receive') }}"
                   class="inline-flex items-center gap-1.5 rounded-xl bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-teal-700 transition">
                    + {{ __('catalogue.add_batch_button') }}
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-xs">
                    <thead class="bg-slate-50/75 text-slate-500 font-semibold uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-2.5 text-left">{{ __('catalogue.batch_no') }}</th>
                            <th class="px-4 py-2.5 text-left">{{ __('catalogue.mfg_date') }}</th>
                            <th class="px-4 py-2.5 text-left">{{ __('catalogue.expiry_date') }}</th>
                            <th class="px-4 py-2.5 text-right">{{ __('catalogue.sellable_stock') }}</th>
                            <th class="px-4 py-2.5 text-left">{{ __('catalogue.batch_status') }}</th>
                            @if ($canManage)
                                <th class="px-4 py-2.5 text-right">{{ __('catalogue.buying_price') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($existingBatches as $eb)
                            @php
                                $exp = $eb->expiry_date?->toDateString();
                                $today = now()->toDateString();
                                $isExp = $exp && $exp <= $today;
                            @endphp
                            <tr class="hover:bg-slate-50/60 {{ $isExp ? 'opacity-60' : '' }}">
                                <td class="px-4 py-2 font-mono font-semibold text-slate-900">{{ $eb->batch_no }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $eb->manufacturing_date?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-4 py-2">
                                    <span class="{{ $isExp ? 'text-rose-600 font-semibold' : 'text-slate-700' }}">{{ $exp ?? '—' }}</span>
                                    @if ($isExp) <span class="ml-1 text-[10px] bg-rose-100 text-rose-700 px-1 rounded font-bold">EXPIRED</span> @endif
                                </td>
                                <td class="px-4 py-2 text-right font-semibold font-mono {{ ((int)$eb->quantity_on_hand) === 0 ? 'text-slate-400' : 'text-slate-900' }}">
                                    {{ number_format((int) $eb->quantity_on_hand) }}
                                </td>
                                <td class="px-4 py-2">
                                    @php $bs = $eb->status ?? 'SELLABLE'; @endphp
                                    <span @class([
                                        'inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold',
                                        'bg-emerald-50 text-emerald-800' => $bs === 'SELLABLE',
                                        'bg-amber-100 text-amber-800'    => $bs === 'QUARANTINED',
                                        'bg-rose-100 text-rose-800'      => $bs === 'RECALLED',
                                        'bg-slate-100 text-slate-500'    => $bs === 'DEPLETED',
                                    ])>{{ $bs }}</span>
                                </td>
                                @if ($canManage)
                                    <td class="px-4 py-2 text-right font-mono text-slate-600">
                                        {{ $eb->unit_cost ? number_format($eb->unit_cost / 100, 2) . ' ETB' : '—' }}
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canManage ? 6 : 5 }}" class="px-4 py-8 text-center text-slate-400 italic text-xs">
                                    {{ __('catalogue.no_batches_yet') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- ================================================================== --}}
        {{-- Section 8: Clinical Drug Information (collapsed, hidden for non_drug) --}}
        {{-- ================================================================== --}}
        @if ($classification !== 'non_drug')
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <button type="button" @click="clinicalOpen = !clinicalOpen"
                    class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-slate-50/50 transition">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                    </svg>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800">{{ __('catalogue.clinical_information') }}</h3>
                    <span class="text-xs text-slate-400 font-normal">({{ __('catalogue.optional') }})</span>
                </div>
                <svg :class="clinicalOpen ? 'rotate-180' : ''" class="h-4 w-4 text-slate-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="clinicalOpen" x-collapse class="border-t border-slate-100">
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.indications') }}</label>
                        <textarea wire:model="indications" rows="2" class="{{ $inputCls }}"></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.dosage_guidance') }}</label>
                            <textarea wire:model="dosage_guidance" rows="2" class="{{ $inputCls }}"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.contraindications') }}</label>
                            <textarea wire:model="contraindications" rows="2" class="{{ $inputCls }}"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.side_effects') }}</label>
                            <textarea wire:model="side_effects" rows="2" class="{{ $inputCls }}"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.interactions') }}</label>
                            <textarea wire:model="interactions" rows="2" class="{{ $inputCls }}"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- ================================================================== --}}
        {{-- Form Actions                                                        --}}
        {{-- ================================================================== --}}
        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('catalogue.index') }}"
               class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
                {{ __('common.cancel') }}
            </a>
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="rounded-xl bg-teal-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition disabled:opacity-60">
                <span wire:loading wire:target="save" class="mr-1">⏳</span>
                {{ $isEdit ? __('catalogue.save_changes') : __('catalogue.save_product') }}
            </button>
        </div>

    </form>
</div>
