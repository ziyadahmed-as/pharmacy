<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                {{ __('disposal.title') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('disposal.subtitle') }}
            </p>
        </div>
        <div>
            <button
                type="button"
                wire:click="openCreateModal"
                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('disposal.new_request') }}
            </button>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="rounded-md bg-emerald-50 p-4 dark:bg-emerald-900/30">
            <p class="text-sm font-medium text-emerald-800 dark:text-emerald-200">
                {{ session('success') }}
            </p>
        </div>
    @endif

    {{-- Filters --}}
    <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center dark:border-slate-800 dark:bg-slate-900">
        <div class="flex-1">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('disposal.search_placeholder') }}"
                class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
            />
        </div>
        <div class="w-full sm:w-48">
            <select
                wire:model.live="statusFilter"
                class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
            >
                <option value="">{{ __('disposal.all_statuses') }}</option>
                <option value="pending">{{ __('disposal.status_pending') }}</option>
                <option value="approved">{{ __('disposal.status_approved') }}</option>
                <option value="rejected">{{ __('disposal.status_rejected') }}</option>
            </select>
        </div>
        <div class="w-full sm:w-48">
            <select
                wire:model.live="methodFilter"
                class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
            >
                <option value="">{{ __('disposal.all_methods') }}</option>
                <option value="return_to_supplier">{{ __('disposal.method_return_to_supplier') }}</option>
                <option value="incineration">{{ __('disposal.method_incineration') }}</option>
                <option value="authorised_collector">{{ __('disposal.method_authorised_collector') }}</option>
                <option value="other">{{ __('disposal.method_other') }}</option>
            </select>
        </div>
    </div>

    {{-- Disposals Table --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <table class="min-w-full divide-y divide-slate-200 text-left text-sm dark:divide-slate-800">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-3">{{ __('disposal.table_date') }}</th>
                    <th class="px-4 py-3">{{ __('disposal.table_product') }}</th>
                    <th class="px-4 py-3">{{ __('disposal.table_quantity') }}</th>
                    <th class="px-4 py-3">{{ __('disposal.table_method') }}</th>
                    <th class="px-4 py-3">{{ __('disposal.table_reason') }}</th>
                    <th class="px-4 py-3">{{ __('disposal.table_requested_by') }}</th>
                    <th class="px-4 py-3">{{ __('disposal.table_status') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('disposal.table_actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                @forelse ($disposals as $disp)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">
                            {{ $disp->created_at->format('Y-m-d H:i') }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900 dark:text-slate-100">
                                {{ $disp->batch->product->brand }}
                            </div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">
                                {{ __('disposal.batch_no') }}: {{ $disp->batch->batch_no }}
                                @if ($disp->batch->product->classification === 'controlled')
                                    <span class="ml-1 inline-flex items-center rounded bg-purple-100 px-1.5 py-0.2 text-[10px] font-medium text-purple-800 dark:bg-purple-900/40 dark:text-purple-300">
                                        {{ __('disposal.controlled_drug') }}
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-900 dark:text-slate-100">
                            {{ number_format($disp->quantity) }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-700 dark:text-slate-300">
                            <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                {{ $disp->methodLabel() }}
                            </span>
                            @if ($disp->isReturnToSupplier() && $disp->supplier_name)
                                <div class="text-xs text-slate-500">{{ $disp->supplier_name }}</div>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">
                            {{ $disp->reasonLabel() }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">
                            {{ $disp->requestedBy->name }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            @include('livewire.inventory.disposals._status_badge', ['status' => $disp->status])
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a
                                    href="{{ route('inventory.disposals.show', $disp) }}"
                                    class="text-xs font-semibold text-emerald-600 hover:text-emerald-500 dark:text-emerald-400"
                                >
                                    {{ __('disposal.view_details') }}
                                </a>
                                @if ($disp->isApproved())
                                    <span class="text-slate-300 dark:text-slate-700">|</span>
                                    <a
                                        href="{{ route('inventory.disposals.certificate', $disp) }}"
                                        class="text-xs font-semibold text-blue-600 hover:text-blue-500 dark:text-blue-400"
                                    >
                                        {{ __('disposal.certificate_btn') }}
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                            {{ __('disposal.no_records') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($disposals->hasPages())
            <div class="border-t border-slate-200 p-4 dark:border-slate-800">
                {{ $disposals->links() }}
            </div>
        @endif
    </div>

    {{-- Create Request Modal --}}
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
            <div class="w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-200 pb-4 dark:border-slate-800">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">
                        {{ __('disposal.modal_title') }}
                    </h2>
                    <button
                        type="button"
                        wire:click="closeCreateModal"
                        class="text-slate-400 hover:text-slate-500"
                    >
                        <span class="sr-only">Close</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                @if ($errorMessage)
                    <div class="mt-4 rounded-md bg-rose-50 p-3 text-sm text-rose-700 dark:bg-rose-900/40 dark:text-rose-200">
                        {{ $errorMessage }}
                    </div>
                @endif

                <form wire:submit="submitDisposal" class="mt-4 space-y-4">
                    {{-- Quarantine Record Picker --}}
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                            {{ __('disposal.select_quarantined_batch') }} *
                        </label>
                        <select
                            wire:model.live="quarantine_record_id"
                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                        >
                            <option value="">{{ __('disposal.choose_quarantine_record') }}</option>
                            @foreach ($openQuarantineRecords as $qr)
                                <option value="{{ $qr->id }}">
                                    {{ $qr->batch->product->brand }} — Batch: {{ $qr->batch->batch_no }} (Available: {{ $qr->disposableQuantity() }} | {{ $qr->reason }})
                                </option>
                            @endforeach
                        </select>
                        @error('quarantine_record_id')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    @if ($isControlledSelected)
                        <div class="rounded-lg border border-purple-200 bg-purple-50 p-3 text-xs text-purple-900 dark:border-purple-900/50 dark:bg-purple-950/40 dark:text-purple-200">
                            <strong>{{ __('disposal.controlled_drug_notice_title') }}</strong>: {{ __('disposal.controlled_drug_notice_body') }}
                        </div>
                    @endif

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        {{-- Quantity --}}
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                {{ __('disposal.quantity') }} *
                            </label>
                            <input
                                type="number"
                                wire:model="quantity"
                                min="1"
                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                            />
                            @error('quantity')
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Reason --}}
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                {{ __('disposal.reason') }} *
                            </label>
                            <select
                                wire:model="reason"
                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                            >
                                <option value="expired">{{ __('disposal.reason_expired') }}</option>
                                <option value="damaged">{{ __('disposal.reason_damaged') }}</option>
                                <option value="recalled">{{ __('disposal.reason_recalled') }}</option>
                                <option value="contaminated">{{ __('disposal.reason_contaminated') }}</option>
                                <option value="other">{{ __('disposal.reason_other') }}</option>
                            </select>
                            @error('reason')
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Method --}}
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                            {{ __('disposal.method') }} *
                        </label>
                        <select
                            wire:model.live="method"
                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                        >
                            <option value="return_to_supplier">{{ __('disposal.method_return_to_supplier') }}</option>
                            <option value="incineration">{{ __('disposal.method_incineration') }}</option>
                            <option value="authorised_collector">{{ __('disposal.method_authorised_collector') }}</option>
                            <option value="other">{{ __('disposal.method_other') }}</option>
                        </select>
                        @error('method')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    @if ($method === 'return_to_supplier')
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                {{ __('disposal.supplier_name') }} *
                            </label>
                            <input
                                type="text"
                                wire:model="supplier_name"
                                placeholder="{{ __('disposal.supplier_name_placeholder') }}"
                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                            />
                            @error('supplier_name')
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    @if ($method === 'authorised_collector')
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                {{ __('disposal.collector_name') }}
                            </label>
                            <input
                                type="text"
                                wire:model="collector_name"
                                placeholder="{{ __('disposal.collector_name_placeholder') }}"
                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                            />
                            @error('collector_name')
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    {{-- Controlled Drug / Witnesses --}}
                    @if ($isControlledSelected)
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                    {{ __('disposal.licensed_performer') }} *
                                </label>
                                <select
                                    wire:model="performer_id"
                                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                >
                                    @foreach ($eligibleStaff as $st)
                                        <option value="{{ $st->id }}">{{ $st->name }} ({{ $st->actingRoleName() }})</option>
                                    @endforeach
                                </select>
                                @error('performer_id')
                                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                    {{ __('disposal.independent_witness') }} *
                                </label>
                                <select
                                    wire:model="witness_id"
                                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                >
                                    <option value="">{{ __('disposal.choose_witness') }}</option>
                                    @foreach ($eligibleStaff as $st)
                                        <option value="{{ $st->id }}">{{ $st->name }} ({{ $st->actingRoleName() }})</option>
                                    @endforeach
                                </select>
                                @error('witness_id')
                                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    @endif

                    {{-- Evidence / Document Attachment --}}
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                            {{ __('disposal.evidence_file') }}
                        </label>
                        <input
                            type="file"
                            wire:model="evidenceFile"
                            accept="image/*,.pdf"
                            class="mt-1 block w-full text-xs text-slate-500 file:mr-4 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-emerald-700 hover:file:bg-emerald-100 dark:file:bg-slate-800 dark:file:text-slate-300"
                        />
                        @error('evidenceFile')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Notes --}}
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                            {{ __('disposal.notes') }}
                        </label>
                        <textarea
                            wire:model="notes"
                            rows="2"
                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                        <button
                            type="button"
                            wire:click="closeCreateModal"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                        >
                            {{ __('disposal.cancel') }}
                        </button>
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500 disabled:opacity-50"
                        >
                            <span wire:loading.remove>{{ __('disposal.submit_request') }}</span>
                            <span wire:loading>{{ __('disposal.submitting') }}...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
