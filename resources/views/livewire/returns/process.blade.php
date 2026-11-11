<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('sales.returns_title') }}</h1>
            <p class="text-sm text-slate-500">{{ __('sales.returns_subtitle') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('pos.sale') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-sm">
                {{ __('sales.return_to_pos') }}
            </a>
        </div>
    </div>

    {{-- Error / Success Alerts --}}
    @if ($errorMessage)
        <div class="rounded-xl bg-red-50 border border-red-200 p-4 text-sm font-medium text-red-800" role="alert">
            {{ $errorMessage }}
        </div>
    @endif
    @if ($successMessage)
        <div class="rounded-xl bg-teal-50 border border-teal-200 p-4 text-sm font-medium text-teal-800" role="alert">
            {{ $successMessage }}
        </div>
    @endif

    {{-- Search Form --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <form wire:submit.prevent="searchSale" class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <input type="text"
                       wire:model="receiptNo"
                       placeholder="{{ __('sales.search_receipt_placeholder') }}"
                       class="w-full rounded-xl border border-slate-300 bg-slate-50 pl-10 pr-4 py-2.5 text-sm font-mono text-slate-900 focus:border-teal-500 focus:bg-white focus:ring-1 focus:ring-teal-500 transition">
            </div>
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-teal-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-teal-700 transition shadow-sm">
                <span wire:loading.remove wire:target="searchSale">{{ __('sales.find_sale') }}</span>
                <span wire:loading wire:target="searchSale">{{ __('common.please_wait') }}</span>
            </button>
        </form>
    </div>

    @if ($sale)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Return Lines Selection --}}
            <div class="lg:col-span-2 space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-slate-100 bg-slate-50/50 px-6 py-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide">{{ __('sales.return_items_heading') }}</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Receipt #{{ $sale->receipt_no }} · {{ $sale->completed_at?->format('d M Y, H:i') }}</p>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-teal-50 border border-teal-200 px-2.5 py-0.5 text-xs font-semibold text-teal-700">
                            {{ $sale->status }}
                        </span>
                    </div>

                    <div class="p-6">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                                    <th class="pb-3 text-left w-10"></th>
                                    <th class="pb-3 text-left">{{ __('sales.item') }}</th>
                                    <th class="pb-3 text-right">{{ __('sales.price') }}</th>
                                    <th class="pb-3 text-center w-28">{{ __('sales.qty') }}</th>
                                    <th class="pb-3 text-center w-36">{{ __('sales.assessment') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($sale->lines as $line)
                                    @php $remQty = $line->remainingReturnableQuantity(); @endphp
                                    <tr wire:key="line-{{ $line->id }}" class="{{ $remQty === 0 ? 'opacity-40' : '' }}">
                                        <td class="py-3.5">
                                            <input type="checkbox"
                                                   wire:model.live="returnLines.{{ $line->id }}.selected"
                                                   {{ $remQty === 0 ? 'disabled' : '' }}
                                                   class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                                        </td>
                                        <td class="py-3.5">
                                            <div class="font-medium text-slate-800">{{ $line->product?->brand ?? 'Item' }}</div>
                                            <div class="text-xs text-slate-400 font-mono">
                                                Batch: {{ $line->batch?->batch_no ?? '—' }} · Sold: {{ $line->quantity }} ({{ $remQty }} left)
                                            </div>
                                        </td>
                                        <td class="py-3.5 text-right font-mono text-slate-600 tabular-nums">
                                            {{ $line->unitPriceEtb() }}
                                        </td>
                                        <td class="py-3.5 text-center">
                                            <input type="number"
                                                   min="1"
                                                   max="{{ $remQty }}"
                                                   wire:model.live="returnLines.{{ $line->id }}.quantity"
                                                   {{ empty($returnLines[$line->id]['selected']) ? 'disabled' : '' }}
                                                   class="w-20 text-center rounded-lg border border-slate-300 bg-slate-50 py-1 text-sm font-mono text-slate-900 disabled:opacity-50">
                                        </td>
                                        <td class="py-3.5 text-center">
                                            <select wire:model.live="returnLines.{{ $line->id }}.assessment"
                                                    {{ empty($returnLines[$line->id]['selected']) ? 'disabled' : '' }}
                                                    class="rounded-lg border border-slate-300 bg-slate-50 py-1 px-2 text-xs font-semibold text-slate-700 disabled:opacity-50">
                                                <option value="resaleable">{{ __('sales.resaleable') }}</option>
                                                <option value="not_resaleable">{{ __('sales.not_resaleable_quarantine') }}</option>
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="mt-6 border-t border-slate-100 pt-4">
                            <label for="return-reason" class="block text-xs font-semibold text-slate-700 mb-1">
                                {{ __('common.reason') }}
                            </label>
                            <input id="return-reason"
                                   type="text"
                                   wire:model="generalReason"
                                   placeholder="{{ __('sales.return_reason_placeholder') }}"
                                   class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-sm text-slate-900 focus:border-teal-500 focus:bg-white focus:ring-1 focus:ring-teal-500 transition">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Sidebar: Refund Preview & Confirm --}}
            <div class="space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide border-b border-slate-100 pb-3">
                        {{ __('sales.refund_summary') }}
                    </h3>

                    <div class="flex justify-between items-center text-sm">
                        <span class="text-slate-500">{{ __('sales.method') }}</span>
                        <span class="font-bold text-slate-800 capitalize">{{ $sale->payments->first()?->methodLabel() ?? 'Cash' }}</span>
                    </div>

                    <div class="flex justify-between items-center text-sm border-t border-slate-100 pt-3">
                        <span class="text-slate-500">{{ __('sales.total') }}</span>
                        <span class="text-xl font-bold font-mono text-teal-700 tabular-nums">
                            {{ $previewRefundEtb }} ETB
                        </span>
                    </div>

                    @if ($willRequireApproval)
                        <div class="rounded-xl bg-amber-50 border border-amber-300 p-3.5 text-xs text-amber-900 flex items-start gap-2">
                            <svg class="h-4 w-4 text-amber-600 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                            <span>{{ __('sales.refund_approval_required_notice') }}</span>
                        </div>
                    @else
                        <div class="rounded-xl bg-teal-50 border border-teal-200 p-3 text-xs text-teal-800">
                            {{ __('sales.refund_within_limit_notice') }}
                        </div>
                    @endif

                    <div class="pt-2">
                        <button type="button"
                                wire:click="submitReturn"
                                wire:loading.attr="disabled"
                                class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-teal-600 px-6 py-3 text-sm font-bold text-white hover:bg-teal-700 disabled:opacity-50 transition shadow-sm">
                            <span wire:loading.remove wire:target="submitReturn">{{ __('sales.process_refund_button') }}</span>
                            <span wire:loading wire:target="submitReturn">{{ __('common.please_wait') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

