<div class="space-y-6">
    {{-- ── Page Header ────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                {{ __('sales.sale_view_title', ['receipt' => $sale->receipt_no]) }}
            </h1>
            <p class="text-sm text-slate-500 mt-0.5">
                {{ $sale->completed_at?->format('d M Y, H:i') }}
            </p>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            {{-- Status badge --}}
            @if ($sale->isVoided())
                <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 border border-red-300 px-3 py-1 text-xs font-bold text-red-700 uppercase tracking-widest">
                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                    {{ __('sales.voided_badge') }}
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-teal-50 border border-teal-200 px-3 py-1 text-xs font-semibold text-teal-700">
                    <span class="h-2 w-2 rounded-full bg-teal-500"></span>
                    {{ __('sales.status_completed') }}
                </span>
            @endif

            {{-- Back to POS --}}
            <a href="{{ route('pos.sale') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-sm">
                {{ __('sales.back_to_pos') }}
            </a>

            {{-- Request Void button --}}
            @if ($canVoid && $sale->isCompleted())
                @php $pendingRequest = $voidRequest?->isPending(); @endphp
                @if (!$pendingRequest)
                    <button type="button"
                            wire:click="openVoidModal"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100 transition shadow-sm">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        {{ __('sales.request_void_button') }}
                    </button>
                @endif
            @endif
        </div>
    </div>

    {{-- ── Success / Error flash ───────────────────────────────────────── --}}
    @if ($voidSuccess)
        <div class="rounded-lg bg-teal-50 border border-teal-200 px-4 py-3 text-sm font-medium text-teal-800 flex items-center gap-2" role="alert">
            <svg class="h-4 w-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            {{ $voidSuccess }}
        </div>
    @endif
    @if ($voidError)
        <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm font-medium text-red-800" role="alert">{{ $voidError }}</div>
    @endif

    {{-- ── Approval-request status panel ──────────────────────────────── --}}
    @if ($voidRequest)
        <div @class([
            'rounded-xl border px-5 py-4 text-sm',
            'bg-amber-50 border-amber-200 text-amber-800'  => $voidRequest->isPending(),
            'bg-green-50 border-green-200 text-green-800'  => $voidRequest->status === 'approved',
            'bg-red-50   border-red-200   text-red-800'    => in_array($voidRequest->status, ['rejected', 'expired', 'cancelled']),
        ])>
            @if ($voidRequest->isPending())
                <div class="flex items-center gap-2 font-semibold mb-1">
                    <svg class="h-4 w-4 animate-pulse" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    {{ __('sales.void_pending_badge') }}
                </div>
                <p>{{ __('sales.void_request_pending') }}</p>
            @elseif ($voidRequest->status === 'rejected')
                <div class="font-semibold mb-1">{{ __('sales.void_rejected_badge') }}</div>
                <p>{{ __('sales.void_rejection_message', ['comment' => $voidRequest->decision_comment ?? '—']) }}</p>
            @endif
            <p class="mt-1 text-xs opacity-70">
                {{ __('approvals.initiator') }}: {{ $voidRequest->initiator?->name }}
                · {{ $voidRequest->created_at?->diffForHumans() }}
            </p>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ── Sale Lines ──────────────────────────────────────────────────── --}}
        <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-3.5 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">{{ __('sales.sale_lines_heading') }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <tr>
                            <th class="px-5 py-3">{{ __('sales.item') }}</th>
                            <th class="px-3 py-3 text-right">{{ __('sales.qty') }}</th>
                            <th class="px-3 py-3 text-right">{{ __('sales.price') }}</th>
                            <th class="px-3 py-3 text-right">{{ __('sales.subtotal') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($sale->lines as $line)
                            <tr wire:key="line-{{ $line->id }}" class="hover:bg-slate-50 transition">
                                <td class="px-5 py-3 font-medium text-slate-800">
                                    {{ $line->product?->brand ?? '—' }}
                                    @if ($line->batch)
                                        <span class="ml-1.5 text-xs text-slate-400 font-normal">{{ $line->batch->batch_no }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-right tabular-nums text-slate-600">{{ $line->quantity }}</td>
                                <td class="px-3 py-3 text-right tabular-nums text-slate-600">{{ $line->unitPriceEtb() }}</td>
                                <td class="px-3 py-3 text-right tabular-nums font-semibold text-slate-800">{{ $line->subtotalEtb() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-6 text-center text-slate-400">—</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-slate-50 font-bold text-slate-800 border-t border-slate-200">
                        <tr>
                            <td colspan="3" class="px-5 py-3 text-right uppercase text-xs tracking-widest">{{ __('sales.total') }}</td>
                            <td class="px-3 py-3 text-right tabular-nums text-teal-700 text-base">{{ $sale->totalEtb() }} ETB</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- ── Right Panel: Info + Payments ───────────────────────────────── --}}
        <div class="space-y-5">

            {{-- Sale Info --}}
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-3.5">
                    <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">{{ __('sales.sale_info_heading') }}</h2>
                </div>
                <dl class="divide-y divide-slate-100 text-sm">
                    <div class="flex justify-between px-5 py-3">
                        <dt class="text-slate-500">{{ __('sales.receipt_no') }}</dt>
                        <dd class="font-mono font-semibold text-slate-800">{{ $sale->receipt_no }}</dd>
                    </div>
                    <div class="flex justify-between px-5 py-3">
                        <dt class="text-slate-500">{{ __('sales.served_by_label') }}</dt>
                        <dd class="font-medium text-slate-800">{{ $sale->user?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between px-5 py-3">
                        <dt class="text-slate-500">{{ __('sales.till_label') }}</dt>
                        <dd class="font-medium text-slate-800">{{ $sale->shift?->till?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between px-5 py-3">
                        <dt class="text-slate-500">{{ __('sales.status_label') }}</dt>
                        <dd>
                            @if ($sale->isVoided())
                                <span class="text-red-600 font-bold">{{ __('sales.status_voided') }}</span>
                            @else
                                <span class="text-teal-600 font-semibold">{{ __('sales.status_completed') }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Payments --}}
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-3.5">
                    <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">{{ __('sales.sale_payments_heading') }}</h2>
                </div>
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($sale->payments as $payment)
                        <li wire:key="payment-{{ $payment->id }}" class="flex items-center justify-between px-5 py-3">
                            <span class="text-slate-600">{{ $payment->methodLabel() }}</span>
                            <span class="font-semibold tabular-nums text-slate-800">{{ $payment->amountEtb() }} ETB</span>
                        </li>
                    @empty
                        <li class="px-5 py-4 text-center text-slate-400">—</li>
                    @endforelse
                </ul>
            </div>

            {{-- Print receipt link --}}
            <a href="{{ route('sales.receipt', $sale) }}"
               target="_blank"
               class="flex w-full items-center justify-center gap-2 rounded-xl border border-teal-300 bg-teal-50 px-4 py-3 text-sm font-semibold text-teal-700 hover:bg-teal-100 transition shadow-sm">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                {{ __('sales.view_print_receipt') }}
            </a>
        </div>
    </div>

    {{-- ── Void Modal ──────────────────────────────────────────────────────── --}}
    @if ($showVoidModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200 overflow-hidden">
                {{-- Modal Header --}}
                <div class="border-b border-slate-100 bg-red-50 px-6 py-4 flex items-center justify-between">
                    <h3 class="text-base font-bold text-red-800">{{ __('sales.void_sale_title') }}</h3>
                    <button type="button" wire:click="closeVoidModal" class="rounded-lg p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="px-6 py-5 space-y-4">
                    <p class="text-sm text-slate-600">
                        Receipt <span class="font-mono font-semibold text-slate-800">{{ $sale->receipt_no }}</span>
                        · <span class="font-semibold">{{ $sale->totalEtb() }} ETB</span>
                    </p>

                    @if ($voidError)
                        <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">{{ $voidError }}</div>
                    @endif

                    <div>
                        <label for="void-reason" class="block text-xs font-semibold text-slate-700 mb-1.5">
                            {{ __('sales.void_reason_label') }} <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            id="void-reason"
                            wire:model="voidReason"
                            rows="3"
                            placeholder="{{ __('sales.void_reason_placeholder') }}"
                            class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-teal-400 focus:ring-2 focus:ring-teal-200 focus:bg-white transition"
                        ></textarea>
                        @error('voidReason')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <p class="text-xs text-slate-500">
                        This request will be sent to a Pharmacy Manager for approval. Stock will only be reversed after approval.
                    </p>
                </div>

                {{-- Modal Footer --}}
                <div class="border-t border-slate-100 bg-slate-50 px-6 py-4 flex justify-end gap-3">
                    <button type="button"
                            wire:click="closeVoidModal"
                            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 transition">
                        {{ __('common.cancel') }}
                    </button>
                    <button type="button"
                            wire:click="submitVoidRequest"
                            wire:loading.attr="disabled"
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50 transition">
                        <span wire:loading.remove wire:target="submitVoidRequest">{{ __('sales.request_void_button') }}</span>
                        <span wire:loading wire:target="submitVoidRequest">{{ __('common.please_wait') }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
