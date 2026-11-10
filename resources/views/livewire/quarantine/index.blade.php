<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('catalogue.quarantine_title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('catalogue.quarantine_subtitle') }}</p>
        </div>
        <div>
            <button
                type="button"
                wire:click="openQuarantineModal"
                class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                {{ __('catalogue.action_quarantine_stock') }}
            </button>
        </div>
    </div>

    {{-- Session alerts --}}
    @if (session()->has('status'))
        <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800" role="status">
            <svg class="h-5 w-5 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
            {{ session('status') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" role="alert">
            <svg class="h-5 w-5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- Metrics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('catalogue.total_quarantined_quantity') }}</dt>
            <dd class="mt-2 text-3xl font-extrabold text-amber-600">{{ number_format($totalQuarantinedQty) }}</dd>
            <p class="text-xs text-slate-400 mt-1">{{ __('catalogue.quarantined_isolated_notice') }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('catalogue.open_quarantine_records') }}</dt>
            <dd class="mt-2 text-3xl font-extrabold text-slate-900">{{ number_format($openRecordsCount) }}</dd>
            <p class="text-xs text-slate-400 mt-1">{{ __('catalogue.records_requiring_resolution') }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('catalogue.recalled_batches') }}</dt>
            <dd class="mt-2 text-3xl font-extrabold text-rose-600">{{ number_format($recalledCount) }}</dd>
            <p class="text-xs text-slate-400 mt-1">{{ __('catalogue.recalled_cannot_be_released_badge') }}</p>
        </div>
    </div>

    {{-- Filters & Search --}}
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm flex flex-col sm:flex-row gap-3 items-center justify-between">
        <div class="w-full sm:w-1/3">
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('catalogue.quarantine_search_placeholder') }}"
                    class="block w-full rounded-lg border border-slate-300 py-2 pl-9 pr-3 text-sm placeholder:text-slate-400 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition"
                />
            </div>
        </div>
        <div class="flex flex-wrap gap-2.5 w-full sm:w-auto items-center">
            <div>
                <select wire:model.live="status" class="rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                    <option value="all">{{ __('catalogue.filter_all_statuses') }}</option>
                    <option value="open">{{ __('catalogue.status_open') }}</option>
                    <option value="released">{{ __('catalogue.status_released') }}</option>
                    <option value="disposed">{{ __('catalogue.status_disposed') }}</option>
                    <option value="returned">{{ __('catalogue.status_returned') }}</option>
                </select>
            </div>
            <div>
                <select wire:model.live="reason" class="rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                    <option value="">{{ __('catalogue.filter_all_reasons') }}</option>
                    <option value="expired">{{ __('catalogue.reason_expired') }}</option>
                    <option value="damaged">{{ __('catalogue.reason_damaged') }}</option>
                    <option value="recalled">{{ __('catalogue.reason_recalled') }}</option>
                    <option value="contaminated">{{ __('catalogue.reason_contaminated') }}</option>
                    <option value="other">{{ __('catalogue.reason_other') }}</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Quarantine Records Table --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50/75 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th scope="col" class="px-4 py-3.5 text-left">ID / Date</th>
                        <th scope="col" class="px-4 py-3.5 text-left">Product / Brand</th>
                        <th scope="col" class="px-4 py-3.5 text-left">Batch # & Expiry</th>
                        <th scope="col" class="px-4 py-3.5 text-right">Quarantined Qty</th>
                        <th scope="col" class="px-4 py-3.5 text-center">Reason</th>
                        <th scope="col" class="px-4 py-3.5 text-center">Status</th>
                        <th scope="col" class="px-4 py-3.5 text-left">Created By & Note</th>
                        <th scope="col" class="px-4 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($records as $record)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="px-4 py-3.5 whitespace-nowrap text-xs text-slate-500">
                                <span class="font-mono font-bold text-slate-900">#{{ $record->id }}</span>
                                <div class="text-[11px] text-slate-400 mt-0.5">{{ local_time($record->created_at, 'Y-m-d H:i') }}</div>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="font-bold text-slate-900">{{ $record->batch?->product?->brand ?? '-' }}</div>
                                <div class="text-xs text-slate-400 mt-0.5">{{ $record->batch?->product?->generic_name ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <div class="font-mono text-xs font-bold text-slate-800">{{ $record->batch?->batch_no ?? '-' }}</div>
                                <div class="text-xs text-slate-400 mt-0.5">Exp: {{ $record->batch?->expiry_date?->format('Y-m-d') ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-right font-mono font-bold text-amber-700">
                                {{ number_format($record->quantity) }}
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-center">
                                @php
                                    $reasonBadge = match ($record->reason) {
                                        'expired' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-600/20',
                                        'recalled' => 'bg-rose-100 text-rose-900 font-bold ring-1 ring-rose-700/30',
                                        'damaged' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20',
                                        'contaminated' => 'bg-purple-50 text-purple-700 ring-1 ring-purple-600/20',
                                        default => 'bg-slate-100 text-slate-700 ring-1 ring-slate-500/20',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 text-xs rounded-full font-semibold {{ $reasonBadge }}">
                                    {{ ucfirst($record->reason) }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-center">
                                @php
                                    $statusBadge = match ($record->status) {
                                        'open' => 'bg-amber-50 text-amber-800 ring-1 ring-amber-600/20',
                                        'released' => 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-600/20',
                                        'disposed' => 'bg-slate-100 text-slate-700 ring-1 ring-slate-500/20',
                                        'returned' => 'bg-blue-50 text-blue-800 ring-1 ring-blue-600/20',
                                        default => 'bg-slate-100 text-slate-800 ring-1 ring-slate-500/20',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold rounded-full {{ $statusBadge }}">
                                    {{ ucfirst($record->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-xs text-slate-600">
                                <span class="font-semibold text-slate-800">{{ $record->creator?->name ?? 'System' }}</span>
                                @if ($record->note)
                                    <div class="text-slate-400 italic mt-0.5 truncate max-w-xs" title="{{ $record->note }}">{{ $record->note }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-right text-xs">
                                @if ($record->isOpen())
                                    @if ($record->isRecalled())
                                        <span class="text-rose-600 text-xs font-semibold" title="{{ __('catalogue.recalled_cannot_be_released') }}">
                                            {{ __('catalogue.release_prohibited') }}
                                        </span>
                                    @else
                                        <button
                                            type="button"
                                            wire:click="openReleaseModal({{ $record->id }})"
                                            class="inline-flex items-center gap-1 rounded-lg bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-700 hover:bg-teal-100 transition"
                                        >
                                            {{ __('catalogue.request_release') }}
                                        </button>
                                    @endif
                                @else
                                    <span class="text-slate-300">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-slate-400 italic">
                                {{ __('catalogue.no_quarantine_records_found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($records->hasPages())
            <div class="px-5 py-3 bg-slate-50/50 border-t border-slate-200">
                {{ $records->links() }}
            </div>
        @endif
    </div>

    {{-- Quarantine Modal --}}
    @if ($showQuarantineModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-slate-200 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-900">
                        {{ __('catalogue.modal_quarantine_stock_title') }}
                    </h3>
                    <button type="button" wire:click="$set('showQuarantineModal', false)" class="text-slate-400 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <p class="text-xs text-slate-500">
                    {{ __('catalogue.modal_quarantine_stock_desc') }}
                </p>

                <form wire:submit="submitQuarantine" class="space-y-4">
                    {{-- Batch Selector --}}
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.select_batch_to_quarantine') }} <span class="text-rose-500">*</span></label>
                        <select wire:model.live="selectedBatchId" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                            <option value="">-- {{ __('catalogue.choose_batch') }} --</option>
                            @foreach ($sellableBatches as $b)
                                @php
                                    $sellable = (int) $b->stocks->sum('quantity');
                                @endphp
                                <option value="{{ $b->id }}">
                                    {{ $b->product->brand }} | Batch: {{ $b->batch_no }} | Exp: {{ $b->expiry_date?->format('Y-m-d') }} ({{ $sellable }} sellable)
                                </option>
                            @endforeach
                        </select>
                        @error('selectedBatchId') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                    </div>

                    @if ($selectedBatchId)
                        <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-900 font-medium">
                            {{ __('catalogue.available_sellable_stock') }}: <span class="font-bold font-mono">{{ $availableSellableQty }}</span>
                        </div>
                    @endif

                    {{-- Quantity --}}
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.quantity_to_quarantine') }} <span class="text-rose-500">*</span></label>
                        <input
                            type="number"
                            wire:model="quarantineQuantity"
                            min="1"
                            max="{{ max(1, $availableSellableQty) }}"
                            class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition"
                        />
                        @error('quarantineQuantity') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                    </div>

                    {{-- Reason --}}
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.quarantine_reason') }} <span class="text-rose-500">*</span></label>
                        <select wire:model="quarantineReason" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                            <option value="damaged">{{ __('catalogue.reason_damaged') }}</option>
                            <option value="expired">{{ __('catalogue.reason_expired') }}</option>
                            <option value="recalled">{{ __('catalogue.reason_recalled') }} (Non-releasable)</option>
                            <option value="contaminated">{{ __('catalogue.reason_contaminated') }}</option>
                            <option value="other">{{ __('catalogue.reason_other') }}</option>
                        </select>
                        @error('quarantineReason') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                    </div>

                    {{-- Note --}}
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.quarantine_note') }}</label>
                        <textarea
                            wire:model="quarantineNote"
                            rows="2"
                            placeholder="{{ __('catalogue.quarantine_note_placeholder') }}"
                            class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition"
                        ></textarea>
                        @error('quarantineNote') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end gap-2.5 pt-4 border-t border-slate-100">
                        <button
                            type="button"
                            wire:click="$set('showQuarantineModal', false)"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                        >
                            {{ __('catalogue.cancel') }}
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-amber-600 px-5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition"
                        >
                            {{ __('catalogue.confirm_quarantine') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Release Modal --}}
    @if ($showReleaseModal && $selectedReleaseRecord)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-slate-200 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-900">
                        {{ __('catalogue.modal_request_release_title') }}
                    </h3>
                    <button type="button" wire:click="$set('showReleaseModal', false)" class="text-slate-400 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <p class="text-xs text-slate-500">
                    {{ __('catalogue.modal_request_release_desc') }}
                </p>

                <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-1.5 text-xs">
                    <div><span class="font-semibold text-slate-500">{{ __('catalogue.product') }}:</span> <span class="font-bold text-slate-800">{{ $selectedReleaseRecord->batch?->product?->brand }}</span></div>
                    <div><span class="font-semibold text-slate-500">{{ __('catalogue.batch_no') }}:</span> <span class="font-mono font-bold text-slate-800">{{ $selectedReleaseRecord->batch?->batch_no }}</span></div>
                    <div><span class="font-semibold text-slate-500">{{ __('catalogue.quarantine_quantity') }}:</span> <span class="font-bold text-amber-700">{{ $selectedReleaseRecord->quantity }} units</span></div>
                    <div><span class="font-semibold text-slate-500">{{ __('catalogue.quarantine_reason') }}:</span> <span class="font-medium text-slate-700">{{ ucfirst($selectedReleaseRecord->reason) }}</span></div>
                </div>

                <form wire:submit="submitRelease" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.release_reason_label') }} <span class="text-rose-500">*</span></label>
                        <textarea
                            wire:model="releaseReason"
                            rows="3"
                            placeholder="{{ __('catalogue.release_reason_placeholder') }}"
                            class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition"
                            required
                        ></textarea>
                        @error('releaseReason') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end gap-2.5 pt-4 border-t border-slate-100">
                        <button
                            type="button"
                            wire:click="$set('showReleaseModal', false)"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                        >
                            {{ __('catalogue.cancel') }}
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-teal-600 px-5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition"
                        >
                            {{ __('catalogue.submit_release_request') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
