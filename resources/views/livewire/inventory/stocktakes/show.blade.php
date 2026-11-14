<div class="max-w-7xl mx-auto space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                {{ __('stocktake.title') }} #{{ $stocktake->id }}
                &mdash;
                <span class="capitalize">{{ $stocktake->type }}</span>
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ __('stocktake.created_by') }}: {{ $stocktake->createdBy?->name }}
                &bull; {{ $stocktake->created_at->format('M d, Y H:i') }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            @include('livewire.inventory.stocktakes._status_badge', ['status' => $stocktake->status])
            <a href="{{ route('inventory.stocktakes.index') }}" wire:navigate class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                &larr; {{ __('common.back_to_list') }}
            </a>
        </div>
    </div>

    {{-- Success / Error alerts --}}
    @if ($successMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ $successMessage }}</div>
    @endif
    @if ($errorMessage)
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errorMessage }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            @foreach ($errors->all() as $err) <p>{{ $err }}</p> @endforeach
        </div>
    @endif

    {{-- Action bar: submit for review / approve / reject --}}
    @if ($stocktake->status === 'counting')
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-blue-800">{{ __('stocktake.counting_in_progress') }}</p>
                <p class="text-xs text-blue-600 mt-0.5">
                    {{ $lines->whereNotNull('counted_qty')->count() }} / {{ $lines->count() }} {{ __('stocktake.lines_counted') }}
                </p>
            </div>
            @if ($lines->whereNull('counted_qty')->isEmpty())
                <button
                    wire:click="submitForReview"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 transition disabled:opacity-50"
                >
                    {{ __('stocktake.submit_for_review') }}
                </button>
            @endif
        </div>
    @endif

    @if ($stocktake->status === 'review')
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-amber-900">{{ __('stocktake.awaiting_approval') }}</p>
                <p class="text-xs text-amber-700 mt-0.5">{{ __('stocktake.review_description') }}</p>
            </div>
            @if ($this->canApprove)
                <div class="flex gap-3">
                    <button
                        wire:click="approve"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 transition disabled:opacity-50"
                    >
                        {{ __('stocktake.approve') }}
                    </button>
                    <button
                        wire:click="openRejectModal"
                        class="inline-flex items-center gap-2 rounded-xl border border-rose-300 bg-white px-5 py-2.5 text-sm font-semibold text-rose-700 shadow-sm hover:bg-rose-50 transition"
                    >
                        {{ __('stocktake.reject') }}
                    </button>
                </div>
            @endif
        </div>
    @endif

    @if ($stocktake->status === 'rejected')
        <div class="bg-rose-50 border border-rose-200 rounded-xl p-4">
            <p class="text-sm font-semibold text-rose-800">{{ __('stocktake.rejected_reason') }}:</p>
            <p class="text-sm text-rose-700 mt-1">{{ $stocktake->rejection_reason }}</p>
        </div>
    @endif

    {{-- Count Sheet / Lines table --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-900">{{ __('stocktake.count_sheet') }}</h2>
            <p class="text-xs text-slate-500">{{ __('stocktake.cost_hidden_from_staff') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-600">{{ __('catalogue.product') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-600">{{ __('catalogue.batch_no') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-600">{{ __('catalogue.expiry_date') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-600">{{ __('stocktake.system_qty') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-600">{{ __('stocktake.counted_qty') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-600">{{ __('stocktake.variance') }}</th>
                        @if ($canSeeCost)
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-600">{{ __('stocktake.variance_value') }}</th>
                        @endif
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-600">{{ __('stocktake.recount') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-600">{{ __('stocktake.counted_by') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($lines as $line)
                        <tr class="hover:bg-slate-50 transition {{ $selectedLineId === $line->id ? 'bg-teal-50 ring-2 ring-inset ring-teal-400' : '' }}">
                            <td class="px-4 py-3 text-sm font-medium text-slate-900">
                                {{ $line->batch->product->brand }}
                                @if ($line->batch->product->classification === 'controlled')
                                    <span class="inline-flex items-center rounded-full bg-purple-50 px-1.5 py-0.5 text-xs font-semibold text-purple-700 border border-purple-200 ml-1">CD</span>
                                @endif
                                <span class="block text-xs text-slate-500">{{ $line->batch->product->generic_name }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs font-mono text-slate-700">{{ $line->batch->batch_no }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600">{{ $line->batch->expiry_date->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-800">{{ $line->system_qty }}</td>
                            <td class="px-4 py-3 text-right font-semibold {{ $line->counted_qty === null ? 'text-slate-400' : 'text-slate-900' }}">
                                {{ $line->counted_qty ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-bold {{ ($line->variance ?? 0) < 0 ? 'text-rose-600' : (($line->variance ?? 0) > 0 ? 'text-emerald-600' : 'text-slate-400') }}">
                                {{ $line->variance !== null ? sprintf('%+d', $line->variance) : '—' }}
                            </td>
                            @if ($canSeeCost)
                                <td class="px-4 py-3 text-right text-xs text-slate-600">
                                    @if ($line->variance !== null && $line->variance !== 0)
                                        {{ number_format(abs($line->variance) * $line->batch->unit_cost / 100, 2) }} ETB
                                    @else
                                        —
                                    @endif
                                </td>
                            @endif
                            <td class="px-4 py-3 text-xs">
                                @if ($line->recount_qty !== null)
                                    <span class="font-semibold text-teal-700">{{ $line->recount_qty }}</span>
                                    <span class="block text-slate-400 text-[11px]">{{ __('stocktake.recount_done') }}</span>
                                @elseif ($line->hasVariance() && $line->requiresRecount())
                                    @if ($stocktake->status === 'review' && $recountLineId !== $line->id)
                                        <button wire:click="openRecountEntry({{ $line->id }})" class="text-amber-700 font-semibold hover:text-amber-900 text-xs">
                                            {{ __('stocktake.enter_recount') }}
                                        </button>
                                    @elseif ($recountLineId === $line->id)
                                        <div class="flex items-center gap-1">
                                            <input
                                                type="number"
                                                min="0"
                                                wire:model="recountQty"
                                                class="w-20 rounded-lg border border-amber-300 py-1 px-2 text-sm focus:ring-2 focus:ring-amber-400"
                                                placeholder="Qty"
                                            />
                                            <button wire:click="saveRecount" class="text-xs font-semibold text-emerald-700 hover:text-emerald-900">{{ __('common.save') }}</button>
                                            <button wire:click="$set('recountLineId', null)" class="text-xs text-slate-500">✕</button>
                                        </div>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 border border-amber-200">
                                            {{ __('stocktake.recount_required') }}
                                        </span>
                                    @endif
                                @else
                                    <span class="text-slate-400 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                {{ $line->countedBy?->name ?? '—' }}
                                @if ($line->secondPerson)
                                    <span class="block text-[11px] text-purple-600">+ {{ $line->secondPerson->name }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if ($stocktake->status === 'counting' && $selectedLineId !== $line->id)
                                    <button
                                        wire:click="selectLine({{ $line->id }})"
                                        class="text-teal-600 hover:text-teal-800 text-xs font-semibold"
                                    >
                                        {{ $line->counted_qty !== null ? __('stocktake.edit_count') : __('stocktake.enter_count') }}
                                    </button>
                                @endif
                            </td>
                        </tr>

                        {{-- Inline count entry row --}}
                        @if ($selectedLineId === $line->id && $stocktake->status === 'counting')
                            <tr class="bg-teal-50/60">
                                <td colspan="{{ $canSeeCost ? 10 : 9 }}" class="px-4 py-4">
                                    <div class="flex flex-wrap items-end gap-4">
                                        <div>
                                            <label class="block text-xs font-semibold text-teal-700 mb-1">
                                                {{ __('stocktake.counted_qty') }} <span class="text-rose-500">*</span>
                                            </label>
                                            <input
                                                type="number"
                                                min="0"
                                                wire:model="countedQty"
                                                autofocus
                                                class="rounded-xl border border-teal-300 py-2 px-3 text-sm font-semibold w-28 focus:border-teal-600 focus:ring-2 focus:ring-teal-500/20"
                                                placeholder="0"
                                            />
                                        </div>

                                        @if ($line->batch->product->classification === 'controlled')
                                            <div>
                                                <label class="block text-xs font-semibold text-purple-700 mb-1">
                                                    {{ __('controlled_drugs.second_person') }} <span class="text-rose-500">*</span>
                                                </label>
                                                <select
                                                    wire:model="secondPersonId"
                                                    class="rounded-xl border border-purple-300 py-2 px-3 text-sm focus:border-purple-600 focus:ring-2 focus:ring-purple-500/20"
                                                >
                                                    <option value="">— Select witness —</option>
                                                    @foreach ($this->witnessUsers as $w)
                                                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                                                    @endforeach
                                                </select>
                                                @error('second_person_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                            </div>
                                        @endif

                                        <div class="flex gap-2">
                                            <button
                                                wire:click="saveCount"
                                                wire:loading.attr="disabled"
                                                class="rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700 transition disabled:opacity-50"
                                            >
                                                {{ __('common.save') }}
                                            </button>
                                            <button
                                                wire:click="$set('selectedLineId', null)"
                                                class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition"
                                            >
                                                {{ __('common.cancel') }}
                                            </button>
                                        </div>
                                        @error('counted_qty') <p class="text-xs text-rose-600 mt-1 w-full">{{ $message }}</p> @enderror
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Reject Modal --}}
    @if ($showRejectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 space-y-4">
                <h2 class="text-lg font-bold text-rose-800">{{ __('stocktake.reject_stocktake') }}</h2>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">{{ __('stocktake.rejection_reason') }} <span class="text-rose-500">*</span></label>
                    <textarea
                        wire:model="rejectionReason"
                        rows="3"
                        placeholder="{{ __('stocktake.rejection_reason_placeholder') }}"
                        class="block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-rose-500 focus:ring-2 focus:ring-rose-400/20"
                    ></textarea>
                    @error('reason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-3 justify-end pt-2">
                    <button wire:click="closeRejectModal" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                        {{ __('common.cancel') }}
                    </button>
                    <button
                        wire:click="reject"
                        wire:loading.attr="disabled"
                        class="rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-rose-700 transition disabled:opacity-50"
                    >
                        {{ __('stocktake.confirm_reject') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

