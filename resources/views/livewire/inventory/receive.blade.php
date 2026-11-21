{{--
    Stock Receipt Entry Form (UC-04)
    Sections:
      A — Delivery & Purchase Information (supplier, invoice, delivery note, date)
      B — Receipt Line Items (product, batch, dates, qty, cost, flag, notes)
      C — Shelf-Life Override (PM/Owner)
      D — Purchase Summary (totals)
      E — Form Actions
--}}
<div class="max-w-7xl mx-auto space-y-6">

    {{-- ── Page header ─────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('catalogue.receive_stock') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('catalogue.receive_stock_subtitle') }}</p>
        </div>
        <a href="{{ route('catalogue.index') }}"
           class="inline-flex items-center gap-1.5 self-start text-sm font-semibold text-slate-600 hover:text-slate-900 transition">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            {{ __('common.back_to_list') }}
        </a>
    </div>

    {{-- ── Validation error summary ─────────────────────────────────────────── --}}
    @if ($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" role="alert">
            <div class="flex items-center gap-2 font-semibold mb-2">
                <svg class="h-5 w-5 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                {{ __('catalogue.receipt_errors_title') }}
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-xs text-rose-700">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $inp = 'block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm
                focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition';
        $inpSm = 'w-full text-xs rounded-lg border border-slate-300 py-1.5 px-2
                  focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition';
        $lbl   = 'block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1';
        $sec   = 'rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5';
        $hdr   = 'flex items-center gap-2 pb-3 border-b border-slate-100';
        $ttl   = 'text-sm font-bold uppercase tracking-wider text-slate-800';
        $ico   = 'h-4 w-4 text-teal-600 shrink-0';
        $err   = 'mt-1 text-xs text-rose-600 block';
    @endphp

    <form wire:submit.prevent="submit" class="space-y-6" novalidate>

        {{-- ================================================================ --}}
        {{-- SECTION A — Delivery & Purchase Information                      --}}
        {{-- ================================================================ --}}
        <div class="{{ $sec }}">
            <div class="{{ $hdr }}">
                <svg class="{{ $ico }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <div>
                    <h3 class="{{ $ttl }}">{{ __('catalogue.receipt_delivery_info') }}</h3>
                    <p class="text-xs text-slate-400 font-normal mt-0.5">{{ __('catalogue.receipt_delivery_info_sub') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-4">
                {{-- Supplier --}}
                <div class="lg:col-span-2">
                    <label class="{{ $lbl }}">{{ __('catalogue.supplier') }} <span class="text-rose-500">*</span></label>
                    <input type="text" wire:model="supplier_name"
                           placeholder="{{ __('catalogue.supplier_placeholder') }}"
                           class="{{ $inp }}">
                    @error('supplier_name') <span class="{{ $err }}">{{ $message }}</span> @enderror
                </div>

                {{-- Receipt Date --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.receipt_date') }} <span class="text-rose-500">*</span></label>
                    <input type="date" wire:model="receipt_date" class="{{ $inp }}">
                    @error('receipt_date') <span class="{{ $err }}">{{ $message }}</span> @enderror
                </div>

                {{-- Purchase Invoice No. --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.purchase_invoice_no') }}</label>
                    <input type="text" wire:model="purchase_invoice_no"
                           placeholder="{{ __('catalogue.invoice_placeholder') }}"
                           class="{{ $inp }} font-mono">
                </div>

                {{-- Delivery Note No. --}}
                <div>
                    <label class="{{ $lbl }}">{{ __('catalogue.delivery_note_no') }}</label>
                    <input type="text" wire:model="delivery_note_no"
                           placeholder="{{ __('catalogue.delivery_note_placeholder') }}"
                           class="{{ $inp }} font-mono">
                </div>

                {{-- Notes --}}
                <div class="lg:col-span-3">
                    <label class="{{ $lbl }}">{{ __('catalogue.receipt_notes') }}</label>
                    <input type="text" wire:model="notes"
                           placeholder="{{ __('catalogue.receipt_notes_placeholder') }}"
                           class="{{ $inp }}">
                </div>
            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- SECTION B — Receipt Line Items                                   --}}
        {{-- ================================================================ --}}
        <div class="{{ $sec }}">
            <div class="flex items-center justify-between {{ $hdr }}">
                <div class="flex items-center gap-2">
                    <svg class="{{ $ico }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    <div>
                        <h3 class="{{ $ttl }}">{{ __('catalogue.receipt_items') }}</h3>
                        <p class="text-xs text-slate-400 font-normal mt-0.5">{{ __('catalogue.receipt_items_sub') }}</p>
                    </div>
                </div>
                <button type="button" wire:click="addLine"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-teal-50 border border-teal-200 px-3 py-1.5 text-xs font-semibold text-teal-700 hover:bg-teal-100 transition">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    {{ __('catalogue.add_line') }}
                </button>
            </div>

            <div class="space-y-4">
                @foreach ($lines as $index => $line)
                <div class="rounded-xl border {{ $errors->hasAny(["lines.{$index}", "lines.{$index}.batch_no", "lines.{$index}.expiry_date", "lines.{$index}.quantity"]) ? 'border-rose-300 bg-rose-50/30' : 'border-slate-200 bg-slate-50/40' }} p-4 space-y-4"
                     wire:key="line-{{ $index }}">

                    {{-- Line header --}}
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-600 uppercase tracking-wider">
                            {{ __('catalogue.receipt_line') }} #{{ $index + 1 }}
                        </span>
                        @if (count($lines) > 1)
                            <button type="button" wire:click="removeLine({{ $index }})"
                                    class="p-1 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-100 transition"
                                    title="{{ __('common.remove') }}">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        @endif
                    </div>

                    {{-- Row 1: Product + Location + Flag --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        {{-- Product --}}
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">{{ __('catalogue.product_name') }} <span class="text-rose-500">*</span></label>
                            <select wire:model="lines.{{ $index }}.product_id"
                                    class="{{ $inpSm }} bg-white">
                                <option value="">— {{ __('catalogue.select_product') }} —</option>
                                @foreach ($products as $p)
                                    <option value="{{ $p->id }}">
                                        {{ $p->generic_name }}{{ $p->brand && $p->brand !== $p->generic_name ? ' ('.$p->brand.')' : '' }}
                                        {{ $p->strength ? '— '.$p->strength : '' }}
                                        {{ $p->dosage_form ? $p->dosage_form : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error("lines.{$index}.product_id") <span class="{{ $err }}">{{ $message }}</span> @enderror
                            @error("lines.{$index}") <span class="{{ $err }}">{{ $message }}</span> @enderror
                        </div>

                        {{-- Location --}}
                        <div>
                            <label class="{{ $lbl }}">{{ __('catalogue.location') }}</label>
                            <select wire:model="lines.{{ $index }}.location_id"
                                    class="{{ $inpSm }} bg-white">
                                <option value="">— {{ __('catalogue.location_default') }} —</option>
                                @foreach ($locations as $loc)
                                    <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Row 2: Batch identification --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        {{-- Batch No --}}
                        <div>
                            <label class="{{ $lbl }}">{{ __('catalogue.batch_no') }} <span class="text-rose-500">*</span></label>
                            <input type="text"
                                   wire:model="lines.{{ $index }}.batch_no"
                                   placeholder="{{ __('catalogue.batch_no_auto_hint') }}"
                                   class="{{ $inpSm }} font-mono">
                            @error("lines.{$index}.batch_no") <span class="{{ $err }}">{{ $message }}</span> @enderror
                        </div>

                        {{-- Mfg Date --}}
                        <div>
                            <label class="{{ $lbl }}">{{ __('catalogue.mfg_date') }}</label>
                            <input type="date"
                                   wire:model="lines.{{ $index }}.manufacturing_date"
                                   class="{{ $inpSm }}">
                        </div>

                        {{-- Expiry Date --}}
                        <div>
                            <label class="{{ $lbl }}">{{ __('catalogue.expiry_date') }} <span class="text-rose-500">*</span></label>
                            <input type="date"
                                   wire:model.live="lines.{{ $index }}.expiry_date"
                                   class="{{ $inpSm }}">
                            @error("lines.{$index}.expiry_date") <span class="{{ $err }}">{{ $message }}</span> @enderror
                        </div>

                        {{-- Condition Flag --}}
                        <div>
                            <label class="{{ $lbl }}">{{ __('catalogue.condition_flag') }}</label>
                            <select wire:model="lines.{{ $index }}.flag"
                                    class="{{ $inpSm }} bg-white">
                                <option value="ok">{{ __('catalogue.flag_ok') }}</option>
                                <option value="short">{{ __('catalogue.flag_short') }}</option>
                                <option value="over">{{ __('catalogue.flag_over') }}</option>
                                <option value="damaged">{{ __('catalogue.flag_damaged') }}</option>
                            </select>
                        </div>
                    </div>

                    {{-- Row 3: Quantities and cost --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 items-end">
                        {{-- Quantity --}}
                        <div>
                            <label class="{{ $lbl }}">{{ __('catalogue.quantity') }} <span class="text-rose-500">*</span></label>
                            <input type="number" min="1"
                                   wire:model.live="lines.{{ $index }}.quantity"
                                   class="{{ $inpSm }}">
                            @error("lines.{$index}.quantity") <span class="{{ $err }}">{{ $message }}</span> @enderror
                        </div>

                        @if ($canViewCost)
                        {{-- Unit Cost --}}
                        <div>
                            <label class="{{ $lbl }}">{{ __('catalogue.unit_cost') }} (ETB)</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-2 flex items-center text-slate-400 text-[10px] font-semibold pointer-events-none">ETB</span>
                                <input type="number" step="0.01" min="0"
                                       wire:model.live="lines.{{ $index }}.unit_cost"
                                       placeholder="0.00"
                                       class="{{ $inpSm }} pl-8">
                            </div>
                        </div>

                        {{-- Total Cost (read-only computed) --}}
                        <div>
                            <label class="{{ $lbl }}">{{ __('catalogue.total_cost') }} (ETB)</label>
                            <div class="h-[30px] flex items-center px-2 rounded-lg bg-white border border-slate-200 text-xs font-mono font-semibold
                                {{ isset($lineTotals[$index]) ? 'text-slate-800' : 'text-slate-400' }}">
                                @if (isset($lineTotals[$index]) && $lineTotals[$index] !== null)
                                    {{ number_format($lineTotals[$index], 2) }}
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                        @endif

                        {{-- Line Notes --}}
                        <div class="{{ $canViewCost ? '' : 'sm:col-span-3' }}">
                            <label class="{{ $lbl }}">{{ __('catalogue.line_notes') }}</label>
                            <input type="text"
                                   wire:model="lines.{{ $index }}.line_notes"
                                   placeholder="{{ __('catalogue.line_notes_placeholder') }}"
                                   class="{{ $inpSm }}">
                        </div>
                    </div>

                </div>
                @endforeach
            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- SECTION C — Shelf-Life Override (PM/Owner only)                  --}}
        {{-- ================================================================ --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
            <label class="flex items-center gap-2.5 cursor-pointer">
                <input type="checkbox" wire:model.live="overrideShelfLife"
                       class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                <span class="text-xs font-semibold text-slate-700">{{ __('catalogue.override_shelf_life_checkbox') }}</span>
            </label>
            @if ($overrideShelfLife)
                <div class="max-w-lg">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1">
                        {{ __('catalogue.override_shelf_life_reason') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" wire:model="overrideReason"
                           placeholder="{{ __('catalogue.override_shelf_life_reason_placeholder') }}"
                           class="w-full text-sm rounded-xl border border-amber-300 py-2 px-3 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-400/20 transition bg-amber-50">
                </div>
            @endif
        </div>

        {{-- ================================================================ --}}
        {{-- SECTION D — Purchase Summary (OW/PM with cost.view)              --}}
        {{-- ================================================================ --}}
        @if ($canViewCost && $grandTotal !== null)
        <div class="rounded-2xl border border-teal-200 bg-teal-50/60 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-teal-900">{{ __('catalogue.total_purchase_cost') }}</span>
                <span class="text-xl font-bold font-mono text-teal-900">
                    {{ number_format($grandTotal, 2) }} ETB
                </span>
            </div>
            <p class="mt-1 text-xs text-teal-700">{{ __('catalogue.total_purchase_cost_note') }}</p>
        </div>
        @endif

        {{-- ================================================================ --}}
        {{-- SECTION E — Form Actions                                         --}}
        {{-- ================================================================ --}}
        <div class="sticky bottom-0 z-20 -mx-4 sm:-mx-6 lg:-mx-8 bg-white/95 backdrop-blur border-t border-slate-200 px-6 py-4">
            <div class="max-w-7xl mx-auto flex items-center justify-between gap-3">
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
                        <svg wire:loading wire:target="submit" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <svg wire:loading.remove wire:target="submit" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        {{ __('catalogue.submit_receipt') }}
                    </button>
                </div>
            </div>
        </div>

    </form>
</div>
