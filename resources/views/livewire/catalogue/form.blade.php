{{--
    New / Edit Medicine Form
    Sections:
      1 — Product Identification
      2 — Pharmaceutical Specifications (active ingredients, dosage form, route, therapeutic class)
      3 — Packaging & Units
      4 — Classification & Regulatory Control
      5 — Storage & Shelf Life
      6 — Pricing  (OW/PM only)
      7 — Inventory Configuration
      8 — Clinical & Dispensing Information  (collapsible, hidden for non_drug)
      +   First Batch / Batch Table
--}}
<div
    class="max-w-5xl mx-auto space-y-6"
    x-data="{
        clinicalOpen: @entangle('clinicalOpen'),
        productType: @entangle('product_type'),
        isMedicine: function(){ return this.productType === 'medicine' || this.productType === 'supplement'; }
    }"
>

    {{-- ── Page header ─────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                {{ $isEdit ? __('catalogue.edit_product') : __('catalogue.new_medicine') }}
            </h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('catalogue.medicine_form_subtitle') }}</p>
        </div>
        <a href="{{ route('catalogue.index') }}"
           class="inline-flex items-center gap-1.5 self-start text-sm font-semibold text-slate-600 hover:text-slate-900 transition">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            {{ __('common.back_to_list') }}
        </a>
    </div>

    {{-- ── Success flash ───────────────────────────────────────────────────── --}}
    @if (session('message'))
        <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">
            <svg class="h-5 w-5 shrink-0 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
            </svg>
            {{ session('message') }}
        </div>
    @endif

    {{-- ── Duplicate warning ───────────────────────────────────────────────── --}}
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

    {{-- ── Regulatory notice ───────────────────────────────────────────────── --}}
    <div class="flex items-start gap-3 rounded-xl border border-teal-200 bg-teal-50/60 px-4 py-3 text-xs text-teal-900">
        <svg class="h-4 w-4 text-teal-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span><strong>{{ __('catalogue.note') }}:</strong> {{ __('catalogue.classification_notice') }}</span>
    </div>

    @php
        $inp = 'mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm
                focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition';
        $sel = $inp . ' bg-white';
        $lbl = 'block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1';
        $sec = 'rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5';
        $hdr = 'flex items-center gap-2 pb-3 border-b border-slate-100';
        $ico = 'h-4 w-4 text-teal-600 shrink-0';
        $ttl = 'text-sm font-bold uppercase tracking-wider text-slate-800';
        $err = 'mt-1 text-xs text-rose-600 block';
    @endphp

    <form wire:submit.prevent="save" class="space-y-6" novalidate>

        {{-- ================================================================ --}}
        {{-- SECTION 1 — Product Identification                               --}}
        {{-- ================================================================ --}}
        <div class="{{ $sec }}">
            <div class="{{ $hdr }}">
                <svg class="{{ $ico }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <div>
                    <h3 class="{{ $ttl }}">{{ __('catalogue.product_identification') }}</h3>
                    <p class="text-xs text-slate-400 font-normal mt-0.5">{{ __('catalogue.section_product_identification_sub') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">

                {{-- Generic Name (INN) — required --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.generic_name') }} (INN) <span class="text-rose-500">*</span></label>
                    <input type="text" wire:model.blur="generic_name" autocomplete="off" class="{{ $inp }}"
                           placeholder="{{ __('catalogue.generic_name_placeholder') }}">
                    @error('generic_name') <span class="{{ $err }}">{{ $message }}</span> @enderror
                </div>

                {{-- Brand Name --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.brand') }}</label>
                    <input type="text" wire:model.blur="brand" autocomplete="off" class="{{ $inp }}"
                           placeholder="{{ __('catalogue.brand_placeholder') }}">
                    <p class="mt-0.5 text-[10px] text-slate-400">{{ __('catalogue.brand_optional_note') }}</p>
                    @error('brand') <span class="{{ $err }}">{{ $message }}</span> @enderror
                </div>

                {{-- Product Code --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.product_code') }}</label>
                    <input type="text" wire:model="product_code" autocomplete="off"
                           placeholder="{{ __('catalogue.product_code_auto_hint') }}"
                           class="{{ $inp }} font-mono">
                    <p class="mt-0.5 text-[10px] text-slate-400">{{ __('catalogue.product_code_auto_note') }}</p>
                    @error('product_code') <span class="{{ $err }}">{{ $message }}</span> @enderror
                </div>

                {{-- Product Type --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.product_type') }}</label>
                    <select wire:model.live="product_type" class="{{ $sel }}">
                        <option value="medicine">{{ __('catalogue.type_medicine') }}</option>
                        <option value="medical_device">{{ __('catalogue.type_medical_device') }}</option>
                        <option value="cosmetic">{{ __('catalogue.type_cosmetic') }}</option>
                        <option value="supplement">{{ __('catalogue.type_supplement') }}</option>
                        <option value="other">{{ __('catalogue.type_other') }}</option>
                    </select>
                </div>

                {{-- Barcode --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.barcode') }}</label>
                    <input type="text" wire:model.blur="barcode" autocomplete="off" class="{{ $inp }} font-mono"
                           placeholder="{{ __('catalogue.barcode_placeholder') }}">
                    @error('barcode') <span class="{{ $err }}">{{ $message }}</span> @enderror
                </div>

                {{-- Country of Origin --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.country_of_origin') }}</label>
                    <input type="text" wire:model="country_of_origin" class="{{ $inp }}"
                           placeholder="{{ __('catalogue.country_placeholder') }}">
                </div>

                {{-- Manufacturer (FK select + free text fallback) --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.manufacturer') }}</label>
                    @if ($manufacturers->count())
                        <select wire:model="manufacturer_id" class="{{ $sel }}">
                            <option value="">— {{ __('catalogue.select_or_type_below') }} —</option>
                            @foreach ($manufacturers as $mfr)
                                <option value="{{ $mfr->id }}">{{ $mfr->name }}{{ $mfr->country ? ' ('.$mfr->country.')' : '' }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[10px] text-slate-400">{{ __('catalogue.or_enter_free_text') }}</p>
                    @endif
                    <input type="text" wire:model="manufacturer" class="{{ $inp }} {{ $manufacturers->count() ? 'mt-1' : '' }}"
                           placeholder="{{ __('catalogue.manufacturer_placeholder') }}">
                </div>

                {{-- Product Description --}}
                <div class="md:col-span-2">
                    <label class="{{ $lbl }}">{{ __('catalogue.product_description') }}</label>
                    <textarea wire:model="product_description" rows="2" class="{{ $inp }}"
                              placeholder="{{ __('catalogue.product_description_placeholder') }}"></textarea>
                </div>

                {{-- Product Image --}}
                <div class="md:col-span-2">
                    <label class="{{ $lbl }}">{{ __('catalogue.image') }} <span class="text-slate-400 font-normal text-[10px] ml-1">(JPG · PNG · WEBP · max 2 MB)</span></label>
                    <div class="flex items-start gap-5 mt-1">
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
                        {{-- Upload controls --}}
                        <div class="flex-1 space-y-2">
                            <input type="file" wire:model="imageFile" accept="image/jpeg,image/png,image/webp"
                                   class="block w-full text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-teal-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-teal-700 hover:file:bg-teal-100 transition">
                            <div wire:loading wire:target="imageFile" class="text-xs text-teal-600 animate-pulse">{{ __('common.uploading') }}…</div>
                            @error('imageFile') <span class="{{ $err }}">{{ $message }}</span> @enderror
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

            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- SECTION 2 — Pharmaceutical Specifications                        --}}
        {{-- ================================================================ --}}
        <div class="{{ $sec }}">
            <div class="{{ $hdr }}">
                <svg class="{{ $ico }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                </svg>
                <div>
                    <h3 class="{{ $ttl }}">{{ __('catalogue.section_pharma_specs') }}</h3>
                    <p class="text-xs text-slate-400 font-normal mt-0.5">{{ __('catalogue.section_pharma_specs_sub') }}</p>
                </div>
            </div>

            {{-- Active Ingredients --}}
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <label class="text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.active_ingredients') }}</label>
                    <button type="button" wire:click="addIngredientRow"
                            class="inline-flex items-center gap-1 rounded-lg bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-700 hover:bg-teal-100 border border-teal-200 transition">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        {{ __('catalogue.add_ingredient') }}
                    </button>
                </div>

                @if (count($activeIngredients) === 0)
                    <p class="text-xs text-slate-400 italic py-1">{{ __('catalogue.no_ingredients_note') }}</p>
                @endif

                @foreach ($activeIngredients as $idx => $ai)
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 space-y-3"
                         wire:key="ai-{{ $idx }}">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-600">{{ __('catalogue.ingredient') }} #{{ $idx + 1 }}</span>
                            <button type="button" wire:click="removeIngredientRow({{ $idx }})"
                                    class="p-1 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition"
                                    title="{{ __('common.remove') }}">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div class="lg:col-span-2">
                                <label class="{{ $lbl }}">{{ __('catalogue.ingredient_name') }} <span class="text-rose-500">*</span></label>
                                <input type="text"
                                       wire:model="activeIngredients.{{ $idx }}.ingredient_name"
                                       placeholder="{{ __('catalogue.ingredient_name_placeholder') }}"
                                       class="{{ $inp }}">
                                @error("activeIngredients.{$idx}.ingredient_name") <span class="{{ $err }}">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="{{ $lbl }}">{{ __('catalogue.ingredient_amount') }} <span class="text-rose-500">*</span></label>
                                <input type="number" step="0.0001" min="0.0001"
                                       wire:model="activeIngredients.{{ $idx }}.amount"
                                       placeholder="e.g. 500"
                                       class="{{ $inp }}">
                                @error("activeIngredients.{$idx}.amount") <span class="{{ $err }}">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="{{ $lbl }}">{{ __('catalogue.strength_unit') }} <span class="text-rose-500">*</span></label>
                                <input type="text"
                                       wire:model="activeIngredients.{{ $idx }}.strength_unit"
                                       placeholder="mg / mcg / IU / %"
                                       class="{{ $inp }}">
                                @error("activeIngredients.{$idx}.strength_unit") <span class="{{ $err }}">{{ $message }}</span> @enderror
                            </div>
                            <div class="lg:col-span-2">
                                <label class="{{ $lbl }}">{{ __('catalogue.ingredient_code') }} <span class="text-slate-400 font-normal">({{ __('catalogue.optional') }})</span></label>
                                <input type="text"
                                       wire:model="activeIngredients.{{ $idx }}.ingredient_code"
                                       placeholder="{{ __('catalogue.ingredient_code_placeholder') }}"
                                       class="{{ $inp }} font-mono">
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Legacy strength + unit (always visible for reference / backward compat) --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 pt-2 border-t border-slate-100">
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.strength') }} <span class="text-slate-400 font-normal">({{ __('catalogue.legacy_text_field') }})</span></label>
                    <input type="text" wire:model="strength" placeholder="e.g. 500mg+125mg" class="{{ $inp }}">
                    <p class="mt-0.5 text-[10px] text-slate-400">{{ __('catalogue.strength_legacy_note') }}</p>
                </div>
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.strength_unit') }}</label>
                    <input type="text" wire:model="strength_unit" placeholder="mg / mcg / IU / ml / %" class="{{ $inp }}">
                </div>

                {{-- Dosage Form --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.dosage_form') }}</label>
                    @if ($dosageForms->count())
                        <select wire:model="dosage_form_id" class="{{ $sel }}">
                            <option value="">— {{ __('catalogue.select_dosage_form') }} —</option>
                            @foreach ($dosageForms as $df)
                                <option value="{{ $df->id }}">{{ $df->name }}{{ $df->abbreviation ? ' ('.$df->abbreviation.')' : '' }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[10px] text-slate-400">{{ __('catalogue.or_enter_free_text') }}</p>
                    @endif
                    <input type="text" wire:model="dosage_form" placeholder="e.g. Tablet, Syrup, Injection"
                           class="{{ $inp }} {{ $dosageForms->count() ? 'mt-1' : '' }}">
                </div>

                {{-- Route of Administration --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.route_of_administration') }}</label>
                    <select wire:model="route_of_administration_id" class="{{ $sel }}">
                        <option value="">— {{ __('catalogue.select_route') }} —</option>
                        @foreach ($routes as $route)
                            <option value="{{ $route->id }}">{{ $route->name }}{{ $route->abbreviation ? ' ('.$route->abbreviation.')' : '' }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Therapeutic Class --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.therapeutic_class') }}</label>
                    <select wire:model="therapeutic_class_id" class="{{ $sel }}">
                        <option value="">— {{ __('catalogue.select_therapeutic_class') }} —</option>
                        @foreach ($therapeuticClasses->whereNull('parent_id') as $parent)
                            <optgroup label="{{ $parent->atc_code ? $parent->atc_code.' — ' : '' }}{{ $parent->name }}">
                                @foreach ($therapeuticClasses->where('parent_id', $parent->id) as $child)
                                    <option value="{{ $child->id }}">{{ $child->atc_code ? $child->atc_code.' — ' : '' }}{{ $child->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>

                {{-- ATC Code --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.atc_code') }}</label>
                    <input type="text" wire:model="atc_code" placeholder="e.g. J01CA04" class="{{ $inp }} font-mono">
                    <p class="mt-0.5 text-[10px] text-slate-400">{{ __('catalogue.atc_code_note') }}</p>
                </div>

                {{-- Registration Number --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.registration_number') }}</label>
                    <input type="text" wire:model="registration_number" placeholder="{{ __('catalogue.registration_number_placeholder') }}" class="{{ $inp }} font-mono">
                </div>
            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- SECTION 3 — Packaging & Units                                    --}}
        {{-- ================================================================ --}}
        <div class="{{ $sec }}">
            <div class="{{ $hdr }}">
                <svg class="{{ $ico }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7c0-2-1-3-3-3H7C5 4 4 5 4 7z M9 11h6"/>
                </svg>
                <div>
                    <h3 class="{{ $ttl }}">{{ __('catalogue.section_packaging') }}</h3>
                    <p class="text-xs text-slate-400 font-normal mt-0.5">{{ __('catalogue.section_packaging_sub') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4">
                {{-- Pack Size --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.pack_size') }}</label>
                    <input type="text" wire:model="pack_size" placeholder="e.g. 10×10, 100 mL" class="{{ $inp }}">
                </div>
                {{-- Base Unit --}}
                <div>
                    <label class="{{ $lbl }}">
                        {{ __('catalogue.base_unit') }} <span class="text-rose-500">*</span>
                        @if ($this->unitIsLocked)
                            <span class="ml-1 text-[10px] font-normal text-amber-600">🔒 {{ __('catalogue.locked') }}</span>
                        @endif
                    </label>
                    <input type="text" wire:model="unit"
                           placeholder="e.g. tablet, vial, mL"
                           class="{{ $inp }} {{ $this->unitIsLocked ? 'bg-slate-100 cursor-not-allowed text-slate-500' : '' }}"
                           @if($this->unitIsLocked) readonly @endif>
                    @if ($this->unitIsLocked)
                        <p class="mt-0.5 text-[10px] text-amber-600">{{ __('catalogue.base_unit_locked') }}</p>
                    @endif
                    @error('unit') <span class="{{ $err }}">{{ $message }}</span> @enderror
                </div>
                {{-- Loose Sale --}}
                <div class="flex items-end pb-1">
                    <label class="inline-flex items-start gap-2.5 cursor-pointer">
                        <input type="checkbox" wire:model="loose_allowed"
                               class="mt-0.5 h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                        <span class="text-sm font-medium text-slate-700">{{ __('catalogue.allow_loose_sales') }}</span>
                    </label>
                </div>
            </div>

            {{-- Alternative Packaging Units --}}
            <div class="space-y-3 pt-2 border-t border-slate-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.alternative_units') }}</p>
                        <p class="text-[10px] text-slate-400 mt-0.5">{{ __('catalogue.alternative_units_example') }}</p>
                    </div>
                    <button type="button" wire:click="addUnitRow"
                            class="inline-flex items-center gap-1 rounded-lg bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-700 hover:bg-teal-100 border border-teal-200 transition">
                        + {{ __('catalogue.add_unit') }}
                    </button>
                </div>
                @forelse ($units as $index => $u)
                    <div class="flex items-center gap-3" wire:key="unit-{{ $index }}">
                        <input type="text" wire:model="units.{{ $index }}.name"
                               placeholder="{{ __('catalogue.unit_name') }} (e.g. Box)"
                               class="flex-1 rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                        <span class="text-slate-400 text-xs shrink-0">=</span>
                        <input type="number" wire:model="units.{{ $index }}.factor"
                               placeholder="{{ __('catalogue.factor_to_base') }}"
                               min="1"
                               class="w-24 rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                        <span class="text-slate-500 text-xs shrink-0 font-medium">{{ $unit ?: 'unit' }}</span>
                        <button type="button" wire:click="removeUnitRow({{ $index }})"
                                class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition shrink-0">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                @empty
                    <p class="text-xs text-slate-400 italic py-1">{{ __('catalogue.no_alternative_units') }}</p>
                @endforelse
            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- SECTION 4 — Classification & Regulatory Control                  --}}
        {{-- ================================================================ --}}
        <div class="{{ $sec }}">
            <div class="{{ $hdr }}">
                <svg class="{{ $ico }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <div>
                    <h3 class="{{ $ttl }}">{{ __('catalogue.section_classification') }}</h3>
                    <p class="text-xs text-slate-400 font-normal mt-0.5">{{ __('catalogue.section_classification_sub') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                {{-- Classification --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.classification') }} <span class="text-rose-500">*</span></label>
                    <select wire:model.live="classification" class="{{ $sel }}">
                        <option value="rx">{{ __('catalogue.classification_rx') }}</option>
                        <option value="otc">{{ __('catalogue.classification_otc') }}</option>
                        <option value="controlled">{{ __('catalogue.classification_controlled') }}</option>
                        <option value="non_drug">{{ __('catalogue.classification_non_drug') }}</option>
                    </select>
                    @error('classification') <span class="{{ $err }}">{{ $message }}</span> @enderror
                </div>

                {{-- Category --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.category') }} <span class="text-rose-500">*</span></label>
                    <select wire:model="category_id" class="{{ $sel }}">
                        <option value="">{{ __('common.none') }}</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}">
                                {{ $cat->parent ? $cat->parent->name.' › ' : '' }}{{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('category_id') <span class="{{ $err }}">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- Controlled drug warning --}}
            @if ($classification === 'controlled')
                <div class="flex items-start gap-3 rounded-xl border border-rose-300 bg-rose-50 p-4">
                    <svg class="h-5 w-5 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div class="text-xs text-rose-900">
                        <p class="font-bold">{{ __('catalogue.controlled_drug_warning_title') }}</p>
                        <p class="mt-0.5">{{ __('catalogue.controlled_drug_warning_body') }}</p>
                    </div>
                </div>
            @endif

            {{-- Checkboxes row --}}
            <div class="flex flex-wrap items-center gap-6 pt-1">
                <label class="inline-flex items-start gap-2.5 cursor-pointer">
                    <input type="checkbox" wire:model="prescription_required"
                           class="mt-0.5 h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                    <span class="text-sm font-medium text-slate-700">{{ __('catalogue.prescription_required') }}</span>
                </label>
            </div>

            {{-- Classification-change confirmation (Edit only) --}}
            @if ($this->classificationChanged)
                <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 space-y-2">
                    <p class="text-xs font-bold text-amber-900">{{ __('catalogue.classification_changed_notice') }}</p>
                    <label class="inline-flex items-start gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="classificationConfirmed"
                               class="mt-0.5 h-4 w-4 rounded border-amber-400 text-amber-600 focus:ring-amber-500">
                        <span class="text-xs font-medium text-amber-900">{{ __('catalogue.classification_confirm_checkbox') }}</span>
                    </label>
                    @error('classificationConfirmed') <span class="{{ $err }}">{{ $message }}</span> @enderror
                </div>
            @endif
        </div>

        {{-- ================================================================ --}}
        {{-- SECTION 5 — Storage & Shelf Life                                 --}}
        {{-- ================================================================ --}}
        <div class="{{ $sec }}">
            <div class="{{ $hdr }}">
                <svg class="{{ $ico }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                </svg>
                <div>
                    <h3 class="{{ $ttl }}">{{ __('catalogue.section_storage') }}</h3>
                    <p class="text-xs text-slate-400 font-normal mt-0.5">{{ __('catalogue.section_storage_sub') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.storage_condition') }}</label>
                    <input type="text" wire:model="storage_condition" placeholder="e.g. Store below 25°C, away from light" class="{{ $inp }}">
                </div>
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.min_shelf_life_days') }}</label>
                    <div class="mt-1 relative">
                        <input type="number" wire:model="min_shelf_life_days" min="0" placeholder="e.g. 180"
                               class="{{ $inp }} pr-14">
                        <span class="absolute inset-y-0 right-3 flex items-center text-xs text-slate-400 font-medium pointer-events-none">days</span>
                    </div>
                    <p class="mt-0.5 text-[10px] text-slate-400">{{ __('catalogue.min_shelf_life_note') }}</p>
                </div>
                <div class="md:col-span-2">
                    <label class="{{ $lbl }}">{{ __('catalogue.special_storage_instructions') }}</label>
                    <textarea wire:model="special_storage_instructions" rows="2" class="{{ $inp }}"
                              placeholder="{{ __('catalogue.special_storage_placeholder') }}"></textarea>
                </div>
            </div>

            {{-- Cold-chain toggle --}}
            <div class="flex items-start gap-3 rounded-xl border {{ $is_cold_chain ? 'border-blue-300 bg-blue-50' : 'border-slate-200 bg-slate-50' }} p-4 transition">
                <svg class="h-5 w-5 {{ $is_cold_chain ? 'text-blue-600' : 'text-slate-400' }} shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v3m0 12v3M5.636 5.636l2.122 2.122m8.485 8.485l2.121 2.121M3 12h3m12 0h3M5.636 18.364l2.122-2.122m8.485-8.485l2.121-2.121"/>
                </svg>
                <div class="flex-1">
                    <label class="inline-flex items-center gap-2.5 cursor-pointer">
                        <input type="checkbox" wire:model.live="is_cold_chain"
                               class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm font-semibold {{ $is_cold_chain ? 'text-blue-900' : 'text-slate-700' }}">
                            {{ __('catalogue.cold_chain_required') }}
                        </span>
                    </label>
                    @if ($is_cold_chain)
                        <p class="mt-1 text-xs text-blue-800">{{ __('catalogue.cold_chain_guidance') }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- SECTION 6 — Pricing  (OW/PM only)                               --}}
        {{-- ================================================================ --}}
        <div class="{{ $sec }}">
            <div class="{{ $hdr }}">
                <svg class="{{ $ico }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <h3 class="{{ $ttl }}">{{ __('catalogue.pricing_section_title') }}</h3>
                    <p class="text-xs text-slate-400 font-normal mt-0.5">{{ __('catalogue.pricing_section_subtitle') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4">
                {{-- Selling Price --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.selling_price_per_unit') }}</label>
                    <div class="mt-1 relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 text-xs font-semibold pointer-events-none">ETB</span>
                        <input type="number" step="0.01" min="0.01" wire:model.live="sellingPrice"
                               placeholder="0.00"
                               class="{{ $inp }} pl-10">
                    </div>
                    @error('sellingPrice') <span class="{{ $err }}">{{ $message }}</span> @enderror
                    @if ($this->priceBelowCostWarning)
                        <div class="mt-1.5 flex items-start gap-1.5 rounded-lg bg-amber-50 border border-amber-200 p-2 text-[11px] text-amber-800 font-medium">
                            <svg class="h-3.5 w-3.5 text-amber-600 shrink-0 mt-px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            {{ $this->priceBelowCostWarning }}
                        </div>
                    @endif
                </div>

                {{-- Effective From --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.price_effective_from') }}</label>
                    <input type="datetime-local" wire:model="sellingPriceEffective" class="{{ $inp }}">
                    @error('sellingPriceEffective') <span class="{{ $err }}">{{ $message }}</span> @enderror
                </div>

                {{-- Reason --}}
                <div>
                    <label class="{{ $lbl }}">
                        {{ __('catalogue.price_change_reason') }}
                        @if ($isEdit) <span class="text-rose-500">*</span> @endif
                    </label>
                    <input type="text" wire:model="sellingPriceReason"
                           placeholder="{{ __('catalogue.price_change_reason_placeholder') }}"
                           class="{{ $inp }}">
                    @error('sellingPriceReason') <span class="{{ $err }}">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- SECTION 7 — Inventory Configuration                              --}}
        {{-- ================================================================ --}}
        <div class="{{ $sec }}">
            <div class="{{ $hdr }}">
                <svg class="{{ $ico }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <div>
                    <h3 class="{{ $ttl }}">{{ __('catalogue.stock_levels') }}</h3>
                    <p class="text-xs text-slate-400 font-normal mt-0.5">{{ __('catalogue.section_inventory_config_sub') }}</p>
                </div>
            </div>

            {{-- Stock level thresholds --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4">
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.min_level') }}</label>
                    <input type="number" wire:model.blur="min_level" min="0" class="{{ $inp }}">
                    @error('min_level') <span class="{{ $err }}">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.reorder_level') }}</label>
                    <input type="number" wire:model.blur="reorder_level" min="0" class="{{ $inp }}">
                    @error('reorder_level') <span class="{{ $err }}">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.max_level') }}</label>
                    <input type="number" wire:model.blur="max_level" min="0" class="{{ $inp }}">
                    @error('max_level') <span class="{{ $err }}">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- Level cross-validation hints --}}
            @if ($min_level > 0 && $reorder_level > 0 && $min_level > $reorder_level)
                <p class="text-xs text-rose-600 font-medium flex items-center gap-1">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01"/></svg>
                    {{ __('catalogue.level_min_gt_reorder') }}
                </p>
            @elseif ($reorder_level > 0 && $max_level > 0 && $reorder_level > $max_level)
                <p class="text-xs text-rose-600 font-medium flex items-center gap-1">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01"/></svg>
                    {{ __('catalogue.level_reorder_gt_max') }}
                </p>
            @endif

            {{-- Tracking toggles --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-3 border-t border-slate-100">
                <label class="flex items-start gap-3 p-3 rounded-xl border {{ $inventory_tracking_enabled ? 'border-teal-200 bg-teal-50/60' : 'border-slate-200 bg-slate-50' }} cursor-pointer transition">
                    <input type="checkbox" wire:model.live="inventory_tracking_enabled"
                           class="mt-0.5 h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                    <div>
                        <span class="text-sm font-semibold {{ $inventory_tracking_enabled ? 'text-teal-900' : 'text-slate-600' }}">{{ __('catalogue.inventory_tracking') }}</span>
                        <p class="text-[10px] text-slate-400 mt-0.5">{{ __('catalogue.inventory_tracking_hint') }}</p>
                    </div>
                </label>
                <label class="flex items-start gap-3 p-3 rounded-xl border {{ $batch_tracking_enabled ? 'border-teal-200 bg-teal-50/60' : 'border-slate-200 bg-slate-50' }} cursor-pointer transition">
                    <input type="checkbox" wire:model.live="batch_tracking_enabled"
                           class="mt-0.5 h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                    <div>
                        <span class="text-sm font-semibold {{ $batch_tracking_enabled ? 'text-teal-900' : 'text-slate-600' }}">{{ __('catalogue.batch_tracking') }}</span>
                        <p class="text-[10px] text-slate-400 mt-0.5">{{ __('catalogue.batch_tracking_hint') }}</p>
                    </div>
                </label>
                <label class="flex items-start gap-3 p-3 rounded-xl border {{ $expiry_tracking_enabled ? 'border-teal-200 bg-teal-50/60' : 'border-slate-200 bg-slate-50' }} cursor-pointer transition">
                    <input type="checkbox" wire:model.live="expiry_tracking_enabled"
                           class="mt-0.5 h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                    <div>
                        <span class="text-sm font-semibold {{ $expiry_tracking_enabled ? 'text-teal-900' : 'text-slate-600' }}">{{ __('catalogue.expiry_tracking') }}</span>
                        <p class="text-[10px] text-slate-400 mt-0.5">{{ __('catalogue.expiry_tracking_hint') }}</p>
                    </div>
                </label>
            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- SECTION 8 — Clinical & Dispensing Information  (collapsible)     --}}
        {{-- ================================================================ --}}
        @if ($classification !== 'non_drug')
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            {{-- Toggle header --}}
            <button type="button" @click="clinicalOpen = !clinicalOpen"
                    class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-slate-50/60 transition">
                <div class="flex items-center gap-2">
                    <svg class="{{ $ico }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                    <h3 class="{{ $ttl }}">{{ __('catalogue.clinical_information') }}</h3>
                    <span class="text-xs text-slate-400 font-normal">({{ __('catalogue.optional') }})</span>
                </div>
                <svg :class="clinicalOpen ? 'rotate-180' : ''"
                     class="h-4 w-4 text-slate-400 transition-transform duration-200"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="clinicalOpen" x-collapse class="border-t border-slate-100">
                {{-- Disclaimer --}}
                <div class="mx-6 mt-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                    <svg class="h-4 w-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ __('catalogue.clinical_disclaimer') }}
                </div>

                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                    <div class="md:col-span-2">
                        <label class="{{ $lbl }}">{{ __('catalogue.indications') }}</label>
                        <textarea wire:model="indications" rows="2" class="{{ $inp }}"
                                  placeholder="{{ __('catalogue.indications_placeholder') }}"></textarea>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">{{ __('catalogue.dosage_guidance') }}</label>
                        <textarea wire:model="dosage_guidance" rows="3" class="{{ $inp }}"
                                  placeholder="{{ __('catalogue.dosage_guidance_placeholder') }}"></textarea>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">{{ __('catalogue.administration_instructions') }}</label>
                        <textarea wire:model="administration_instructions" rows="3" class="{{ $inp }}"
                                  placeholder="{{ __('catalogue.administration_instructions_placeholder') }}"></textarea>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">{{ __('catalogue.contraindications') }}</label>
                        <textarea wire:model="contraindications" rows="3" class="{{ $inp }}"
                                  placeholder="{{ __('catalogue.contraindications_placeholder') }}"></textarea>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">{{ __('catalogue.side_effects') }}</label>
                        <textarea wire:model="side_effects" rows="3" class="{{ $inp }}"
                                  placeholder="{{ __('catalogue.side_effects_placeholder') }}"></textarea>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">{{ __('catalogue.interactions') }}</label>
                        <textarea wire:model="interactions" rows="3" class="{{ $inp }}"
                                  placeholder="{{ __('catalogue.interactions_placeholder') }}"></textarea>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">{{ __('catalogue.warnings_precautions') }}</label>
                        <textarea wire:model="warnings_precautions" rows="3" class="{{ $inp }}"
                                  placeholder="{{ __('catalogue.warnings_precautions_placeholder') }}"></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="{{ $lbl }}">{{ __('catalogue.patient_counseling_information') }}</label>
                        <textarea wire:model="patient_counseling_information" rows="3" class="{{ $inp }}"
                                  placeholder="{{ __('catalogue.patient_counseling_placeholder') }}"></textarea>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- ================================================================ --}}
        {{-- SECTION 9a — First Batch  (Add screen only)                      --}}
        {{-- ================================================================ --}}
        @if (! $isEdit)
        <div class="rounded-2xl border {{ $batchStarted ? 'border-teal-300 ring-1 ring-teal-200' : 'border-slate-200' }} bg-white p-6 shadow-sm space-y-5 transition">
            <div class="{{ $hdr }}">
                <svg class="{{ $ico }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
                <div>
                    <h3 class="{{ $ttl }}">
                        {{ __('catalogue.first_batch_section_title') }}
                        @if ($batchStarted)
                            <span class="ml-1 text-teal-600 font-normal text-xs">({{ __('catalogue.required_fields_active') }})</span>
                        @endif
                    </h3>
                    <p class="text-xs text-slate-400 font-normal mt-0.5">{{ __('catalogue.first_batch_section_subtitle') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                {{-- Batch Number --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.batch_no') }} @if($batchStarted)<span class="text-rose-500">*</span>@endif</label>
                    <input type="text" wire:model="batchNo" class="{{ $inp }} font-mono"
                           placeholder="{{ __('catalogue.batch_no_auto_hint') }}">
                    <p class="mt-0.5 text-[10px] text-slate-400">{{ __('catalogue.batch_no_auto_note') }}</p>
                    @error('batchNo') <span class="{{ $err }}">{{ $message }}</span> @enderror
                </div>
                {{-- Quantity --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.quantity') }} @if($batchStarted)<span class="text-rose-500">*</span>@endif</label>
                    <input type="number" wire:model="batchQuantity" min="1" class="{{ $inp }}">
                    @error('batchQuantity') <span class="{{ $err }}">{{ $message }}</span> @enderror
                </div>
                {{-- Mfg Date --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.mfg_date') }} @if($batchStarted)<span class="text-rose-500">*</span>@endif</label>
                    <input type="date" wire:model.live="batchMfgDate" class="{{ $inp }}">
                    @error('batchMfgDate') <span class="{{ $err }}">{{ $message }}</span> @enderror
                </div>
                {{-- Expiry Date --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.expiry_date') }} @if($batchStarted)<span class="text-rose-500">*</span>@endif</label>
                    <input type="date" wire:model.live="batchExpiryDate" class="{{ $inp }}">
                    @error('batchExpiryDate') <span class="{{ $err }}">{{ $message }}</span> @enderror
                </div>
                {{-- Buying Price --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.buying_price') }} @if($batchStarted)<span class="text-rose-500">*</span>@endif</label>
                    <div class="mt-1 relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 text-xs font-semibold pointer-events-none">ETB</span>
                        <input type="number" step="0.01" min="0.01" wire:model.live="batchBuyingPrice"
                               placeholder="0.00" class="{{ $inp }} pl-10">
                    </div>
                    @error('batchBuyingPrice') <span class="{{ $err }}">{{ $message }}</span> @enderror
                </div>
                {{-- Est. Profit --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.profit_per_unit') }}</label>
                    @php
                        $sp = $sellingPrice !== '' ? (float) $sellingPrice : 0;
                        $bp = $batchBuyingPrice !== '' ? (float) $batchBuyingPrice : 0;
                        $profit = $sp - $bp;
                    @endphp
                    <div class="mt-1 h-10 flex items-center px-3 rounded-xl bg-slate-50 border border-slate-200 text-sm font-mono font-semibold
                        {{ $profit > 0 ? 'text-emerald-700' : ($profit < 0 ? 'text-rose-600' : 'text-slate-400') }}">
                        @if ($sp > 0 && $bp > 0)
                            {{ $profit >= 0 ? '+' : '' }}{{ number_format($profit, 2) }} ETB
                        @else
                            —
                        @endif
                    </div>
                </div>
                {{-- Supplier --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.supplier') }}</label>
                    <input type="text" wire:model="batchSupplier" class="{{ $inp }}">
                </div>
                {{-- Delivery Note --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.delivery_note_no') }}</label>
                    <input type="text" wire:model="batchDeliveryNo" class="{{ $inp }} font-mono">
                </div>
            </div>
        </div>
        @endif

        {{-- ================================================================ --}}
        {{-- SECTION 9b — Batch Table (Edit screen)                           --}}
        {{-- ================================================================ --}}
        @if ($isEdit)
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <svg class="{{ $ico }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    <div>
                        <h3 class="{{ $ttl }}">{{ __('catalogue.batches_section_title') }}</h3>
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
                                $exp   = $eb->expiry_date?->toDateString();
                                $today = now()->toDateString();
                                $isExp = $exp && $exp <= $today;
                                $bs    = $eb->status ?? 'SELLABLE';
                            @endphp
                            <tr class="hover:bg-slate-50/60 {{ $isExp ? 'opacity-60' : '' }}">
                                <td class="px-4 py-2 font-mono font-semibold text-slate-900">{{ $eb->batch_no }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $eb->manufacturing_date?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-4 py-2">
                                    <span class="{{ $isExp ? 'text-rose-600 font-semibold' : 'text-slate-700' }}">{{ $exp ?? '—' }}</span>
                                    @if ($isExp) <span class="ml-1 text-[10px] bg-rose-100 text-rose-700 px-1.5 py-0.5 rounded font-bold">EXPIRED</span> @endif
                                </td>
                                <td class="px-4 py-2 text-right font-semibold font-mono {{ ((int)$eb->quantity_on_hand) === 0 ? 'text-slate-400' : 'text-slate-900' }}">
                                    {{ number_format((int) $eb->quantity_on_hand) }}
                                </td>
                                <td class="px-4 py-2">
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
                                        {{ $eb->unit_cost ? number_format($eb->unit_cost / 100, 2).' ETB' : '—' }}
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canManage ? 6 : 5 }}" class="px-4 py-8 text-center text-slate-400 italic">
                                    {{ __('catalogue.no_batches_yet') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- ================================================================ --}}
        {{-- Form Actions                                                      --}}
        {{-- ================================================================ --}}
        <div class="sticky bottom-0 z-20 -mx-4 sm:-mx-6 lg:-mx-8 bg-white/95 backdrop-blur border-t border-slate-200 px-6 py-4">
            <div class="max-w-5xl mx-auto flex items-center justify-between gap-3">
                <p class="text-xs text-slate-400 hidden sm:block">
                    <span class="text-rose-500">*</span> {{ __('catalogue.required_fields_note') }}
                </p>
                <div class="flex items-center gap-3 ml-auto">
                    <a href="{{ route('catalogue.index') }}"
                       class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
                        {{ __('common.cancel') }}
                    </a>
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-70 cursor-not-allowed"
                            class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition">
                        <svg wire:loading wire:target="save" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        {{ $isEdit ? __('catalogue.save_changes') : __('catalogue.save_medicine') }}
                    </button>
                </div>
            </div>
        </div>

    </form>
</div>
