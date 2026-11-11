<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('shifts.close_shift_title') }}</h1>
            <p class="text-sm text-slate-500">{{ __('shifts.close_shift_subtitle') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('pos.sale') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-sm">
                {{ __('shifts.return_to_pos') }}
            </a>
            @if ($shift && $shift->isOpen())
                <button type="button"
                        wire:click="openCashMovementModal"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-teal-300 bg-teal-50 px-3 py-1.5 text-xs font-semibold text-teal-700 hover:bg-teal-100 transition shadow-sm">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    {{ __('shifts.record_cash_movement') }}
                </button>
            @endif
        </div>
    </div>

    {{-- Error / Success Alerts --}}
    @if ($errorMessage)
        <div class="rounded-xl bg-red-50 border border-red-200 p-4 text-sm font-medium text-red-800" role="alert">
            {{ $errorMessage }}
        </div>
    @endif
    @if ($movementSuccess)
        <div class="rounded-xl bg-teal-50 border border-teal-200 p-4 text-sm font-medium text-teal-800" role="alert">
            {{ $movementSuccess }}
        </div>
    @endif

    @if (! $shift)
        <div class="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-sm">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 mb-4">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h3 class="text-base font-semibold text-slate-800">{{ __('shifts.no_active_shift_to_close') }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ __('shifts.no_open_shift_error') }}</p>
            <div class="mt-6">
                <a href="{{ route('shifts.open') }}"
                   class="inline-flex items-center rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 transition shadow-sm">
                    {{ __('shifts.open_shift') }}
                </a>
            </div>
        </div>
    @else
        {{-- Held Sales Warning --}}
        @if ($heldSalesCount > 0)
            <div class="rounded-xl bg-amber-50 border border-amber-300 p-4 flex items-start gap-3 text-sm text-amber-900" role="alert">
                <svg class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                <div>
                    <h4 class="font-bold">{{ __('shifts.held_sales_warning', ['count' => $heldSalesCount]) }}</h4>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Main Form: Tender Reconciliation --}}
            <div class="lg:col-span-2 space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-slate-100 bg-slate-50/50 px-6 py-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide">{{ __('shifts.tender_reconciliation') }}</h2>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $shift->till->name }} · {{ $shift->user->name }} · {{ __('shifts.opened_at') }}: {{ $shift->opened_at->format('d M Y, H:i') }}</p>
                        </div>
                        <span class="inline-flex items-center gap-1 rounded-full bg-teal-50 border border-teal-200 px-2.5 py-0.5 text-xs font-semibold text-teal-700">
                            {{ __('shifts.status_open') }}
                        </span>
                    </div>

                    <div class="p-6">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                                    <th class="text-left pb-3">{{ __('sales.method') }}</th>
                                    <th class="text-right pb-3">{{ __('shifts.expected') }} (ETB)</th>
                                    <th class="text-right pb-3 w-48">{{ __('shifts.counted') }} (ETB)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($expected as $method => $expectedAmount)
                                    <tr wire:key="method-{{ $method }}">
                                        <td class="py-3.5 font-medium text-slate-800">
                                            @if ($method === 'cash')
                                                <div class="flex items-center gap-2">
                                                    <span class="h-2 w-2 rounded-full bg-teal-500"></span>
                                                    <span>{{ __('sales.payment_method_cash') }}</span>
                                                    <span class="text-xs text-slate-400">({{ __('shifts.opening_float') }}: {{ number_format($shift->opening_float / 100, 2) }})</span>
                                                </div>
                                            @elseif ($method === 'telebirr')
                                                {{ __('sales.payment_method_telebirr') }}
                                            @elseif ($method === 'cbe_birr')
                                                {{ __('sales.payment_method_cbe_birr') }}
                                            @elseif ($method === 'card')
                                                {{ __('sales.payment_method_card') }}
                                            @elseif ($method === 'bank_transfer')
                                                {{ __('sales.payment_method_bank_transfer') }}
                                            @else
                                                {{ ucfirst(str_replace('_', ' ', $method)) }}
                                            @endif
                                        </td>
                                        <td class="py-3.5 text-right font-mono text-slate-600 tabular-nums">
                                            {{ number_format($expectedAmount / 100, 2) }}
                                        </td>
                                        <td class="py-3.5 text-right">
                                            <input type="number"
                                                   step="0.01"
                                                   min="0"
                                                   wire:model.live.debounce.300ms="counted.{{ $method }}"
                                                   placeholder="0.00"
                                                   class="w-full text-right rounded-lg border border-slate-300 bg-slate-50 px-3 py-1.5 text-sm font-mono text-slate-900 focus:border-teal-500 focus:bg-white focus:ring-1 focus:ring-teal-500 transition">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-t-2 border-slate-200 font-bold text-slate-900">
                                    <td class="pt-4 uppercase text-xs tracking-wider">{{ __('sales.total') }}</td>
                                    <td class="pt-4 text-right font-mono tabular-nums text-slate-700">{{ $expectedTotalEtb }}</td>
                                    <td class="pt-4 text-right font-mono tabular-nums text-teal-700 text-base">{{ $countedTotalEtb }}</td>
                                </tr>
                            </tfoot>
                        </table>

                        {{-- Variance Status Banner --}}
                        <div class="mt-6 rounded-xl border p-4 flex items-center justify-between text-sm {{ $liveVarianceSantim === 0 ? 'bg-teal-50 border-teal-200 text-teal-800' : ($liveVarianceSantim > 0 ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-red-50 border-red-200 text-red-800') }}">
                            <div class="flex items-center gap-2">
                                <span class="font-bold">{{ __('shifts.variance') }}:</span>
                                @if ($liveVarianceSantim === 0)
                                    <span>{{ __('shifts.balanced') }}</span>
                                @elseif ($liveVarianceSantim > 0)
                                    <span class="font-bold">{{ __('shifts.overage', ['amount' => $liveVarianceEtb]) }}</span>
                                @else
                                    <span class="font-bold">{{ __('shifts.shortage', ['amount' => ltrim($liveVarianceEtb, '-')]) }}</span>
                                @endif
                            </div>
                            <span class="font-mono text-base font-bold tabular-nums">
                                {{ $liveVarianceSantim > 0 ? '+' : '' }}{{ $liveVarianceEtb }} ETB
                            </span>
                        </div>

                        {{-- Variance Reason (Mandatory when variance != 0) --}}
                        @if ($liveVarianceSantim !== 0)
                            <div class="mt-5 space-y-1.5">
                                <label for="variance-reason" class="block text-xs font-bold text-red-700 uppercase tracking-wide">
                                    {{ __('shifts.variance_reason') }} <span class="text-red-500">*</span>
                                </label>
                                <textarea id="variance-reason"
                                          wire:model="varianceReason"
                                          rows="3"
                                          placeholder="Explain drawer overage / shortage count (mandatory)..."
                                          class="w-full rounded-xl border border-red-300 bg-red-50/30 p-3 text-sm text-slate-900 focus:border-red-500 focus:bg-white focus:ring-1 focus:ring-red-500 transition"></textarea>
                                @error('varianceReason')
                                    <p class="text-xs font-semibold text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        {{-- Submit Button --}}
                        <div class="mt-6 flex justify-end">
                            <button type="button"
                                    wire:click="closeShift"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-6 py-3 text-sm font-bold text-white hover:bg-teal-700 disabled:opacity-50 transition shadow-sm">
                                <span wire:loading.remove wire:target="closeShift">{{ __('shifts.confirm_close') }}</span>
                                <span wire:loading wire:target="closeShift">{{ __('common.please_wait') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Sidebar: Shift Overview & Cash Movements --}}
            <div class="space-y-6">
                {{-- Shift Overview --}}
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-slate-100 bg-slate-50/50 px-5 py-3.5">
                        <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide">{{ __('shifts.sales_summary') }}</h3>
                    </div>
                    <dl class="divide-y divide-slate-100 text-sm">
                        <div class="flex justify-between px-5 py-3">
                            <dt class="text-slate-500">{{ __('shifts.shift_number') }}</dt>
                            <dd class="font-mono font-semibold text-slate-800">#{{ $shift->id }}</dd>
                        </div>
                        <div class="flex justify-between px-5 py-3">
                            <dt class="text-slate-500">{{ __('shifts.till') }}</dt>
                            <dd class="font-medium text-slate-800">{{ $shift->till->name }}</dd>
                        </div>
                        <div class="flex justify-between px-5 py-3">
                            <dt class="text-slate-500">{{ __('sales.served_by') }}</dt>
                            <dd class="font-medium text-slate-800">{{ $shift->user->name }}</dd>
                        </div>
                        <div class="flex justify-between px-5 py-3">
                            <dt class="text-slate-500">{{ __('shifts.opening_float') }}</dt>
                            <dd class="font-mono font-semibold text-slate-800">{{ number_format($shift->opening_float / 100, 2) }} ETB</dd>
                        </div>
                        <div class="flex justify-between px-5 py-3">
                            <dt class="text-slate-500">{{ __('shifts.duration') }}</dt>
                            <dd class="text-slate-600">{{ $shift->opened_at->diffForHumans(null, true) }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- Cash In / Cash Out Log --}}
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-slate-100 bg-slate-50/50 px-5 py-3.5 flex items-center justify-between">
                        <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide">{{ __('shifts.cash_movements') }}</h3>
                        <span class="text-xs font-mono text-slate-500">({{ $shift->cashMovements->count() }})</span>
                    </div>
                    <div class="divide-y divide-slate-100 text-xs">
                        @forelse ($shift->cashMovements as $movement)
                            <div class="p-4 flex items-center justify-between hover:bg-slate-50 transition">
                                <div>
                                    <span class="inline-flex items-center gap-1 font-bold {{ $movement->isCashIn() ? 'text-teal-700' : 'text-amber-700' }}">
                                        {{ $movement->isCashIn() ? '+ Cash In' : '- Cash Out' }}
                                    </span>
                                    <p class="text-slate-500 text-[11px] mt-0.5">{{ $movement->reason }}</p>
                                </div>
                                <span class="font-mono font-bold text-slate-800 tabular-nums text-sm">
                                    {{ $movement->amountEtb() }} ETB
                                </span>
                            </div>
                        @empty
                            <div class="p-6 text-center text-slate-400">
                                <p>{{ __('common.no_results') }}</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Cash Movement Modal --}}
    @if ($showCashMovementModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200 overflow-hidden">
                <div class="border-b border-slate-100 bg-teal-50 px-6 py-4 flex items-center justify-between">
                    <h3 class="text-base font-bold text-teal-900">{{ __('shifts.record_cash_movement') }}</h3>
                    <button type="button" wire:click="closeCashMovementModal" class="rounded-lg p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    @if ($movementError)
                        <div class="rounded-lg bg-red-50 border border-red-200 p-3 text-xs text-red-800">{{ $movementError }}</div>
                    @endif

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">{{ __('shifts.movement_type') }}</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button"
                                    wire:click="$set('movementType', 'cash_in')"
                                    class="rounded-xl border p-3 text-center text-xs font-bold transition {{ $movementType === 'cash_in' ? 'border-teal-500 bg-teal-50 text-teal-800 ring-2 ring-teal-200' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">
                                + {{ __('shifts.cash_in') }}
                            </button>
                            <button type="button"
                                    wire:click="$set('movementType', 'cash_out')"
                                    class="rounded-xl border p-3 text-center text-xs font-bold transition {{ $movementType === 'cash_out' ? 'border-amber-500 bg-amber-50 text-amber-800 ring-2 ring-amber-200' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">
                                - {{ __('shifts.cash_out') }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <label for="move-amount" class="block text-xs font-semibold text-slate-700 mb-1">{{ __('shifts.movement_amount') }} *</label>
                        <input id="move-amount"
                               type="number"
                               step="0.01"
                               min="0.01"
                               wire:model="movementAmount"
                               placeholder="0.00"
                               class="w-full rounded-xl border border-slate-300 bg-slate-50 p-2.5 text-sm font-mono text-slate-900 focus:border-teal-500 focus:bg-white focus:ring-1 focus:ring-teal-500 transition">
                        @error('movementAmount') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="move-reason" class="block text-xs font-semibold text-slate-700 mb-1">{{ __('shifts.movement_reason') }} *</label>
                        <input id="move-reason"
                               type="text"
                               wire:model="movementReason"
                               placeholder="e.g. Added petty cash float / Bank drop"
                               class="w-full rounded-xl border border-slate-300 bg-slate-50 p-2.5 text-sm text-slate-900 focus:border-teal-500 focus:bg-white focus:ring-1 focus:ring-teal-500 transition">
                        @error('movementReason') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="border-t border-slate-100 bg-slate-50 px-6 py-4 flex justify-end gap-3">
                    <button type="button" wire:click="closeCashMovementModal" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 transition">
                        {{ __('common.cancel') }}
                    </button>
                    <button type="button" wire:click="submitCashMovement" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-bold text-white hover:bg-teal-700 transition">
                        {{ __('common.save') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

