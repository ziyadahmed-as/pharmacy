<div class="space-y-6">
    {{-- Header with Till & Shift details --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('sales.pos_title') }}</h1>
            <p class="text-sm text-slate-500">{{ __('sales.pos_subtitle') }}</p>
        </div>
        <div class="flex items-center gap-3">
            @if ($currentShift)
                <div class="inline-flex items-center gap-2 rounded-xl bg-teal-50 border border-teal-200 px-3.5 py-1.5 text-xs font-semibold text-teal-800 shadow-sm">
                    <span class="h-2.5 w-2.5 rounded-full bg-teal-500 animate-pulse"></span>
                    <span>{{ $currentShift->till->name }}</span>
                    <span class="text-teal-600 font-mono">({{ $currentShift->openingFloatEtb() }} ETB)</span>
                </div>
            @endif
            <a href="{{ route('shifts.open') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-sm">
                {{ __('shifts.active_shift_title') }}
            </a>
        </div>
    </div>

    {{-- Error / Warning alerts --}}
    @if ($errorMessage)
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 shadow-sm flex items-start gap-3">
            <svg class="h-5 w-5 text-rose-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="text-sm font-medium text-rose-800 flex-1">{{ $errorMessage }}</div>
            <button wire:click="$set('errorMessage', null)" class="text-rose-400 hover:text-rose-600">✕</button>
        </div>
    @endif

    {{-- A3: Prescription / Controlled warning banner --}}
    @if ($warningMessage)
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 shadow-sm flex items-start gap-3">
            <svg class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div class="text-sm text-amber-900 flex-1">
                <div class="font-bold">{{ __('sales.prescription_warning_title') }}</div>
                <div>{{ $warningMessage }}</div>
                <div class="mt-2">
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-800 bg-amber-100 border border-amber-300 rounded-lg px-2.5 py-1">
                        {{ __('sales.handover_to_rx') }} (UC-21)
                    </span>
                </div>
            </div>
            <button wire:click="$set('warningMessage', null)" class="text-amber-500 hover:text-amber-700">✕</button>
        </div>
    @endif

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 shadow-sm flex items-center justify-between">
            <span>{{ session('status') }}</span>
            @if ($lastCompletedSaleId)
                <a href="{{ route('sales.receipt', $lastCompletedSaleId) }}" target="_blank" class="underline text-emerald-900 font-bold ml-3">
                    {{ __('sales.view_print_receipt') }} ({{ $lastCompletedReceiptNo }})
                </a>
            @endif
        </div>
    @endif

    {{-- Main POS Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        {{-- LEFT COLUMN: Scan, Search & Cart (8 cols) --}}
        <div class="lg:col-span-8 space-y-4">

            {{-- Keyboard-first Scan & Search Bar --}}
            <div class="relative bg-white rounded-2xl border border-slate-200 p-3 shadow-sm">
                <form wire:submit.prevent="submitScan" class="relative">
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                            <input
                                type="text"
                                wire:model.live.debounce.250ms="search"
                                autofocus
                                placeholder="{{ __('sales.search_or_scan_placeholder') }}"
                                class="w-full rounded-xl border border-slate-300 pl-10 pr-20 py-2.5 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-200 transition"
                            />
                            <div class="absolute inset-y-0 right-0 flex items-center pr-2">
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-mono text-slate-500 border border-slate-200">Enter ↵</span>
                            </div>
                        </div>
                    </div>
                </form>

                {{-- Autocomplete Dropdown --}}
                @if (!empty($searchResults))
                    <div class="absolute left-0 right-0 top-full mt-1 z-30 bg-white rounded-xl border border-slate-200 shadow-xl overflow-hidden divide-y divide-slate-100">
                        @foreach ($searchResults as $res)
                            <button
                                type="button"
                                wire:click="addProduct({{ $res['id'] }})"
                                class="w-full text-left p-3 hover:bg-teal-50/70 transition flex items-center justify-between group">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-sm text-slate-900 group-hover:text-teal-700">{{ $res['brand'] }}</span>
                                        @if ($res['strength'])
                                            <span class="text-xs text-slate-500 font-medium">({{ $res['strength'] }})</span>
                                        @endif
                                        <span @class([
                                            'text-xs px-2 py-0.5 rounded-full font-semibold',
                                            'bg-emerald-50 text-emerald-700' => in_array($res['classification'], ['otc', 'non_drug']),
                                            'bg-amber-100 text-amber-800' => in_array($res['classification'], ['rx', 'controlled']),
                                        ])>
                                            {{ strtoupper($res['classification']) }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-500 truncate">
                                        {{ $res['generic_name'] }} • {{ $res['dosage_form'] }}
                                        @if ($res['barcode'])
                                            • <span class="font-mono text-slate-400">{{ $res['barcode'] }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right ml-4 shrink-0">
                                    <div class="text-sm font-bold text-teal-700">{{ $res['price_etb'] }} ETB</div>
                                    <div class="text-xs text-slate-500">
                                        {{ __('sales.stock') }}: <span class="font-semibold text-slate-700">{{ $res['total_stock'] }}</span>
                                        • Exp: {{ $res['earliest_expiry'] }}
                                    </div>
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Cart Items Table --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <span>{{ __('sales.cart_items') }}</span>
                        <span class="rounded-full bg-teal-100 text-teal-800 px-2 py-0.5 text-xs font-semibold">{{ count($cart) }}</span>
                    </h2>
                    @if (!empty($cart))
                        <div class="flex items-center gap-2">
                            {{-- A4 Hold button --}}
                            <button
                                type="button"
                                wire:click="holdSale"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                                <svg class="h-3.5 w-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ __('sales.hold_cart') }}
                            </button>
                            <button
                                type="button"
                                wire:click="$set('cart', [])"
                                class="text-xs font-semibold text-rose-600 hover:text-rose-700 px-2 py-1">
                                {{ __('sales.clear_cart') }}
                            </button>
                        </div>
                    @endif
                </div>

                @if (empty($cart))
                    <div class="p-12 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <p class="mt-3 text-sm font-semibold text-slate-900">{{ __('sales.empty_cart_title') }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ __('sales.empty_cart_hint') }}</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500 border-b border-slate-100">
                                <tr>
                                    <th class="px-4 py-2.5">{{ __('sales.product') }}</th>
                                    <th class="px-3 py-2.5">{{ __('sales.batch_expiry') }}</th>
                                    <th class="px-3 py-2.5 text-center">{{ __('sales.unit') }}</th>
                                    <th class="px-3 py-2.5 text-center">{{ __('sales.quantity') }}</th>
                                    <th class="px-3 py-2.5 text-right">{{ __('sales.price') }}</th>
                                    <th class="px-4 py-2.5 text-right">{{ __('sales.subtotal') }}</th>
                                    <th class="px-2 py-2.5 text-center"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($cart as $line)
                                    <tr wire:key="cart-line-{{ $line['temp_id'] }}" class="hover:bg-slate-50/50">
                                        {{-- Product --}}
                                        <td class="px-4 py-3">
                                            <div class="font-bold text-slate-900">{{ $line['brand'] }}</div>
                                            <div class="text-xs text-slate-500">{{ $line['generic_name'] }}</div>
                                        </td>

                                        {{-- Batch & FEFO --}}
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            <div class="flex items-center gap-1.5">
                                                <span @class([
                                                    'font-mono text-xs px-2 py-0.5 rounded font-medium',
                                                    'bg-emerald-50 text-emerald-800 border border-emerald-200' => ! $line['is_fefo_override'],
                                                    'bg-amber-50 text-amber-800 border border-amber-200' => $line['is_fefo_override'],
                                                ])>
                                                    {{ $line['batch_no'] }}
                                                </span>
                                                @if ($line['is_fefo_override'])
                                                    <span class="text-[10px] bg-amber-200 text-amber-900 px-1 rounded font-bold" title="{{ $line['override_reason'] }}">OVERRIDE</span>
                                                @endif
                                            </div>
                                            <div class="text-[11px] text-slate-500 mt-0.5">
                                                Exp: {{ $line['expiry_date'] }}
                                                @if (count($line['available_batches']) > 1)
                                                    • <button type="button" wire:click="openBatchOverride('{{ $line['temp_id'] }}')" class="text-teal-600 hover:underline font-semibold">{{ __('sales.change_batch') }}</button>
                                                @endif
                                            </div>
                                        </td>

                                        {{-- Unit Selector --}}
                                        <td class="px-3 py-3 text-center whitespace-nowrap">
                                            @if (!empty($line['available_units']))
                                                <select
                                                    wire:change="changeUnit('{{ $line['temp_id'] }}', $event.target.value ? parseInt($event.target.value) : null)"
                                                    class="rounded-lg border-slate-300 py-1 px-2 text-xs focus:border-teal-500 focus:ring-teal-200">
                                                    <option value="">{{ $line['unit_name'] }} (Base)</option>
                                                    @foreach ($line['available_units'] as $u)
                                                        <option value="{{ $u['id'] }}" @selected($line['unit_id'] === $u['id'])>
                                                            {{ $u['name'] }} (x{{ $u['factor'] }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <span class="text-xs text-slate-600 font-medium">{{ $line['unit_name'] }}</span>
                                            @endif
                                        </td>

                                        {{-- Quantity --}}
                                        <td class="px-3 py-3 text-center whitespace-nowrap">
                                            <div class="inline-flex items-center rounded-lg border border-slate-200 bg-white shadow-sm">
                                                <button
                                                    type="button"
                                                    wire:click="updateQuantity('{{ $line['temp_id'] }}', -1)"
                                                    class="px-2 py-1 text-slate-600 hover:bg-slate-100 rounded-l-lg transition">
                                                    -
                                                </button>
                                                <span class="px-3 py-1 text-xs font-bold font-mono text-slate-900">{{ $line['quantity'] }}</span>
                                                <button
                                                    type="button"
                                                    wire:click="updateQuantity('{{ $line['temp_id'] }}', 1)"
                                                    class="px-2 py-1 text-slate-600 hover:bg-slate-100 rounded-r-lg transition">
                                                    +
                                                </button>
                                            </div>
                                        </td>

                                        {{-- Unit Price --}}
                                        <td class="px-3 py-3 text-right whitespace-nowrap text-xs font-mono text-slate-700">
                                            {{ number_format($line['unit_price'] / 100, 2) }}
                                        </td>

                                        {{-- Subtotal --}}
                                        <td class="px-4 py-3 text-right whitespace-nowrap text-sm font-bold font-mono text-slate-900">
                                            {{ number_format($line['subtotal'] / 100, 2) }}
                                        </td>

                                        {{-- Remove --}}
                                        <td class="px-2 py-3 text-center whitespace-nowrap">
                                            <button
                                                type="button"
                                                wire:click="removeLine('{{ $line['temp_id'] }}')"
                                                class="text-slate-400 hover:text-rose-600 transition p-1">
                                                ✕
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>

        {{-- RIGHT COLUMN: Payment Panel & Checkout (4 cols) --}}
        <div class="lg:col-span-4 space-y-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-5">
                <h2 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-3 flex items-center justify-between">
                    <span>{{ __('sales.payment_summary') }}</span>
                    <span class="text-xs font-normal text-slate-500">ETB</span>
                </h2>

                {{-- Totals Breakdown --}}
                <div class="space-y-2 text-sm">
                    <div class="flex items-center justify-between text-slate-600">
                        <span>{{ __('sales.total_payable') }}</span>
                        <span class="text-xl font-extrabold text-slate-900 font-mono">{{ number_format($cartTotalSantim / 100, 2) }}</span>
                    </div>

                    <div class="flex items-center justify-between text-slate-600">
                        <span>{{ __('sales.total_paid') }}</span>
                        <span class="font-bold text-emerald-700 font-mono">{{ number_format($totalPaidSantim / 100, 2) }}</span>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                        <span class="font-bold text-slate-800">{{ __('sales.balance_due') }}</span>
                        <span @class([
                            'text-lg font-extrabold font-mono',
                            'text-rose-600' => $balanceSantim > 0,
                            'text-emerald-600' => $balanceSantim === 0,
                            'text-amber-600' => $balanceSantim < 0,
                        ])>
                            {{ number_format($balanceSantim / 100, 2) }}
                        </span>
                    </div>
                </div>

                {{-- Quick Exact Cash Button --}}
                @if ($cartTotalSantim > 0)
                    <div class="flex gap-2">
                        <button
                            type="button"
                            wire:click="setExactCash"
                            class="flex-1 rounded-xl bg-teal-50 border border-teal-200 px-3 py-2 text-xs font-bold text-teal-800 hover:bg-teal-100 transition shadow-sm">
                            {{ __('sales.exact_cash') }} ({{ number_format($cartTotalSantim / 100, 2) }})
                        </button>
                    </div>

                    {{-- Denomination Chips --}}
                    <div class="grid grid-cols-4 gap-1.5 pt-1">
                        @foreach ([50, 100, 200, 500] as $denom)
                            <button
                                type="button"
                                wire:click="addCashDenomination({{ $denom }})"
                                class="rounded-lg border border-slate-200 bg-slate-50 py-1 text-xs font-bold text-slate-700 hover:bg-slate-100 transition">
                                +{{ $denom }}
                            </button>
                        @endforeach
                    </div>
                @endif

                {{-- Tenders Recorded List --}}
                @if (!empty($payments))
                    <div class="space-y-2 pt-2 border-t border-slate-100">
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('sales.payments') }}</div>
                        <div class="space-y-1.5">
                            @foreach ($payments as $idx => $p)
                                <div class="flex items-center justify-between bg-slate-50 rounded-xl px-3 py-2 text-xs border border-slate-200">
                                    <div class="min-w-0">
                                        <div class="font-bold text-slate-900">{{ $this->paymentMethodLabel($p['method']) }}</div>
                                        @if ($p['reference'])
                                            <div class="text-[10px] text-slate-500 font-mono truncate">Ref: {{ $p['reference'] }}</div>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold font-mono text-slate-900">{{ number_format($p['amount'] / 100, 2) }}</span>
                                        <button type="button" wire:click="removePayment({{ $idx }})" class="text-slate-400 hover:text-rose-600">✕</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Add Payment Form --}}
                <div class="space-y-3 pt-2 border-t border-slate-100">
                    <div class="text-xs font-bold text-slate-800">{{ __('sales.add_payment_tender') }}</div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-[11px] font-medium text-slate-600">{{ __('sales.method') }}</label>
                            <select
                                wire:model="selectedPaymentMethod"
                                class="w-full rounded-xl border-slate-300 text-xs py-2 focus:border-teal-500 focus:ring-teal-200">
                                @foreach ($activeMethods as $m)
                                    <option value="{{ $m }}">{{ $this->paymentMethodLabel($m) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-[11px] font-medium text-slate-600">{{ __('sales.amount') }} (ETB)</label>
                            <input
                                type="number"
                                step="0.01"
                                wire:model="paymentAmountInput"
                                class="w-full rounded-xl border-slate-300 text-xs py-2 font-mono text-right focus:border-teal-500 focus:ring-teal-200"
                            />
                        </div>
                    </div>

                    @if ($selectedPaymentMethod !== 'cash')
                        <div>
                            <label class="text-[11px] font-medium text-slate-600">{{ __('sales.reference_hint') }}</label>
                            <input
                                type="text"
                                wire:model="paymentReferenceInput"
                                placeholder="{{ __('sales.reference_placeholder') }}"
                                class="w-full rounded-xl border-slate-300 text-xs py-2 font-mono focus:border-teal-500 focus:ring-teal-200"
                            />
                        </div>
                    @endif

                    <button
                        type="button"
                        wire:click="addPayment"
                        class="w-full rounded-xl border border-slate-300 bg-slate-50 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 transition shadow-sm">
                        + {{ __('sales.add_payment_button') }}
                    </button>
                </div>

                {{-- Complete Sale Submit Button --}}
                <div class="pt-3 border-t border-slate-100">
                    <button
                        type="button"
                        wire:click="submitSale"
                        wire:loading.attr="disabled"
                        @disabled(empty($cart) || $balanceSantim !== 0)
                        class="w-full rounded-xl bg-teal-600 py-3 text-sm font-bold text-white hover:bg-teal-700 transition shadow-sm disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                        <span wire:loading.remove wire:target="submitSale">{{ __('sales.complete_sale_button') }}</span>
                        <span wire:loading wire:target="submitSale">{{ __('sales.processing_sale') }}...</span>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            </div>
        </div>

    </div>

    {{-- A2: Manual Batch Override Modal --}}
    @if ($overrideModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">{{ __('sales.batch_override_title') }}</h3>
                    <button wire:click="$set('overrideModalOpen', false)" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">{{ __('sales.select_batch') }}</label>
                        @php
                            $targetLine = collect($cart)->firstWhere('temp_id', $overrideCartTempId);
                        @endphp
                        @if ($targetLine)
                            <select
                                wire:model="overrideSelectedBatchId"
                                class="w-full rounded-xl border-slate-300 text-xs py-2 focus:border-teal-500 focus:ring-teal-200">
                                @foreach ($targetLine['available_batches'] as $b)
                                    <option value="{{ $b['id'] }}">
                                        {{ $b['batch_no'] }} (Exp: {{ $b['expiry'] }} • Stock: {{ $b['stock'] }})
                                    </option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            {{ __('sales.override_reason_label') }} <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            wire:model="overrideReason"
                            placeholder="{{ __('sales.override_reason_placeholder') }}"
                            class="w-full rounded-xl border-slate-300 text-xs py-2 focus:border-teal-500 focus:ring-teal-200"
                        />
                        @error('overrideReason')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button
                        type="button"
                        wire:click="$set('overrideModalOpen', false)"
                        class="rounded-xl border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        {{ __('common.cancel') }}
                    </button>
                    <button
                        type="button"
                        wire:click="saveBatchOverride"
                        class="rounded-xl bg-teal-600 px-4 py-2 text-xs font-bold text-white hover:bg-teal-700 shadow-sm">
                        {{ __('sales.apply_override') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
