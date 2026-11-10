<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('catalogue.quarantine_title') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('catalogue.quarantine_subtitle') }}</p>
        </div>
        <div>
            <button
                type="button"
                wire:click="openQuarantineModal"
                class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500"
            >
                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                {{ __('catalogue.action_quarantine_stock') }}
            </button>
        </div>
    </div>

    {{-- Session alerts --}}
    @if (session()->has('status'))
        <div class="p-4 rounded-md bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">
            {{ session('status') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="p-4 rounded-md bg-rose-50 border border-rose-200 text-rose-800 text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- Metrics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div class="bg-white overflow-hidden shadow rounded-lg border border-gray-100 p-5">
            <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('catalogue.total_quarantined_quantity') }}</dt>
            <dd class="mt-2 text-3xl font-extrabold text-amber-600">{{ number_format($totalQuarantinedQty) }}</dd>
            <p class="text-xs text-gray-400 mt-1">{{ __('catalogue.quarantined_isolated_notice') }}</p>
        </div>
        <div class="bg-white overflow-hidden shadow rounded-lg border border-gray-100 p-5">
            <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('catalogue.open_quarantine_records') }}</dt>
            <dd class="mt-2 text-3xl font-extrabold text-gray-900">{{ number_format($openRecordsCount) }}</dd>
            <p class="text-xs text-gray-400 mt-1">{{ __('catalogue.records_requiring_resolution') }}</p>
        </div>
        <div class="bg-white overflow-hidden shadow rounded-lg border border-gray-100 p-5">
            <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('catalogue.recalled_batches') }}</dt>
            <dd class="mt-2 text-3xl font-extrabold text-rose-600">{{ number_format($recalledCount) }}</dd>
            <p class="text-xs text-gray-400 mt-1">{{ __('catalogue.recalled_cannot_be_released_badge') }}</p>
        </div>
    </div>

    {{-- Filters & Search --}}
    <div class="bg-white shadow rounded-lg p-4 border border-gray-100 flex flex-col md:flex-row gap-4 items-center justify-between">
        <div class="w-full md:w-1/3">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('catalogue.quarantine_search_placeholder') }}"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm"
            />
        </div>
        <div class="flex flex-wrap gap-3 w-full md:w-auto items-center">
            <div>
                <select wire:model.live="status" class="rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm">
                    <option value="all">{{ __('catalogue.filter_all_statuses') }}</option>
                    <option value="open">{{ __('catalogue.status_open') }}</option>
                    <option value="released">{{ __('catalogue.status_released') }}</option>
                    <option value="disposed">{{ __('catalogue.status_disposed') }}</option>
                    <option value="returned">{{ __('catalogue.status_returned') }}</option>
                </select>
            </div>
            <div>
                <select wire:model.live="reason" class="rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm">
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
    <div class="bg-white shadow overflow-hidden rounded-lg border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-semibold">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left">ID / Date</th>
                    <th scope="col" class="px-4 py-3 text-left">Product / Brand</th>
                    <th scope="col" class="px-4 py-3 text-left">Batch # & Expiry</th>
                    <th scope="col" class="px-4 py-3 text-right">Quarantined Qty</th>
                    <th scope="col" class="px-4 py-3 text-center">Reason</th>
                    <th scope="col" class="px-4 py-3 text-center">Status</th>
                    <th scope="col" class="px-4 py-3 text-left">Created By & Note</th>
                    <th scope="col" class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse ($records as $record)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500">
                            <span class="font-mono font-medium text-gray-900">#{{ $record->id }}</span>
                            <div>{{ local_time($record->created_at, 'Y-m-d H:i') }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $record->batch?->product?->brand ?? '-' }}</div>
                            <div class="text-xs text-gray-500">{{ $record->batch?->product?->generic_name ?? '-' }}</div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="font-mono text-xs font-semibold text-gray-900">{{ $record->batch?->batch_no ?? '-' }}</div>
                            <div class="text-xs text-gray-500">Exp: {{ $record->batch?->expiry_date?->format('Y-m-d') ?? '-' }}</div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-right font-bold text-amber-700">
                            {{ number_format($record->quantity) }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-center">
                            @php
                                $reasonBadge = match ($record->reason) {
                                    'expired' => 'bg-rose-100 text-rose-800 border-rose-300',
                                    'recalled' => 'bg-red-200 text-red-900 font-bold border-red-400',
                                    'damaged' => 'bg-amber-100 text-amber-800 border-amber-300',
                                    'contaminated' => 'bg-purple-100 text-purple-800 border-purple-300',
                                    default => 'bg-gray-100 text-gray-800 border-gray-300',
                                };
                            @endphp
                            <span class="px-2 py-0.5 text-xs rounded-full border {{ $reasonBadge }}">
                                {{ ucfirst($record->reason) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-center">
                            @php
                                $statusBadge = match ($record->status) {
                                    'open' => 'bg-amber-100 text-amber-800',
                                    'released' => 'bg-emerald-100 text-emerald-800',
                                    'disposed' => 'bg-gray-100 text-gray-700',
                                    'returned' => 'bg-blue-100 text-blue-800',
                                    default => 'bg-gray-100 text-gray-800',
                                };
                            @endphp
                            <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full {{ $statusBadge }}">
                                {{ ucfirst($record->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600">
                            <span class="font-medium text-gray-800">{{ $record->creator?->name ?? 'System' }}</span>
                            @if ($record->note)
                                <div class="text-gray-500 italic mt-0.5 truncate max-w-xs" title="{{ $record->note }}">{{ $record->note }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-right text-xs">
                            @if ($record->isOpen())
                                @if ($record->isRecalled())
                                    <span class="text-rose-600 text-[11px] font-medium" title="{{ __('catalogue.recalled_cannot_be_released') }}">
                                        {{ __('catalogue.release_prohibited') }}
                                    </span>
                                @else
                                    <button
                                        type="button"
                                        wire:click="openReleaseModal({{ $record->id }})"
                                        class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-indigo-700 bg-indigo-50 hover:bg-indigo-100 focus:outline-none"
                                    >
                                        {{ __('catalogue.request_release') }}
                                    </button>
                                @endif
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                            {{ __('catalogue.no_quarantine_records_found') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($records->hasPages())
            <div class="px-4 py-3 bg-gray-50 border-t border-gray-200">
                {{ $records->links() }}
            </div>
        @endif
    </div>

    {{-- Quarantine Modal --}}
    @if ($showQuarantineModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showQuarantineModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                        {{ __('catalogue.modal_quarantine_stock_title') }}
                    </h3>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ __('catalogue.modal_quarantine_stock_desc') }}
                    </p>

                    <form wire:submit="submitQuarantine" class="mt-4 space-y-4">
                        {{-- Batch Selector --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-700">{{ __('catalogue.select_batch_to_quarantine') }}</label>
                            <select wire:model.live="selectedBatchId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm">
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
                            @error('selectedBatchId') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>

                        @if ($selectedBatchId)
                            <div class="p-3 bg-amber-50 rounded-md border border-amber-200 text-xs text-amber-800">
                                {{ __('catalogue.available_sellable_stock') }}: <span class="font-bold">{{ $availableSellableQty }}</span>
                            </div>
                        @endif

                        {{-- Quantity --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-700">{{ __('catalogue.quantity_to_quarantine') }}</label>
                            <input
                                type="number"
                                wire:model="quarantineQuantity"
                                min="1"
                                max="{{ max(1, $availableSellableQty) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm"
                            />
                            @error('quarantineQuantity') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>

                        {{-- Reason --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-700">{{ __('catalogue.quarantine_reason') }}</label>
                            <select wire:model="quarantineReason" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm">
                                <option value="damaged">{{ __('catalogue.reason_damaged') }}</option>
                                <option value="expired">{{ __('catalogue.reason_expired') }}</option>
                                <option value="recalled">{{ __('catalogue.reason_recalled') }} (Non-releasable)</option>
                                <option value="contaminated">{{ __('catalogue.reason_contaminated') }}</option>
                                <option value="other">{{ __('catalogue.reason_other') }}</option>
                            </select>
                            @error('quarantineReason') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>

                        {{-- Note --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-700">{{ __('catalogue.quarantine_note') }}</label>
                            <textarea
                                wire:model="quarantineNote"
                                rows="3"
                                placeholder="{{ __('catalogue.quarantine_note_placeholder') }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm"
                            ></textarea>
                            @error('quarantineNote') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                            <button
                                type="submit"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-amber-600 text-base font-medium text-white hover:bg-amber-700 focus:outline-none sm:col-start-2 sm:text-sm"
                            >
                                {{ __('catalogue.confirm_quarantine') }}
                            </button>
                            <button
                                type="button"
                                wire:click="$set('showQuarantineModal', false)"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:col-start-1 sm:text-sm"
                            >
                                {{ __('catalogue.cancel') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Release Modal --}}
    @if ($showReleaseModal && $selectedReleaseRecord)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-release-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showReleaseModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-release-title">
                        {{ __('catalogue.modal_request_release_title') }}
                    </h3>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ __('catalogue.modal_request_release_desc') }}
                    </p>

                    <div class="mt-4 p-3 bg-gray-50 rounded-md border border-gray-200 space-y-1 text-xs">
                        <div><span class="font-semibold">{{ __('catalogue.product') }}:</span> {{ $selectedReleaseRecord->batch?->product?->brand }}</div>
                        <div><span class="font-semibold">{{ __('catalogue.batch_no') }}:</span> {{ $selectedReleaseRecord->batch?->batch_no }}</div>
                        <div><span class="font-semibold">{{ __('catalogue.quarantine_quantity') }}:</span> {{ $selectedReleaseRecord->quantity }} units</div>
                        <div><span class="font-semibold">{{ __('catalogue.quarantine_reason') }}:</span> {{ ucfirst($selectedReleaseRecord->reason) }}</div>
                    </div>

                    <form wire:submit="submitRelease" class="mt-4 space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700">{{ __('catalogue.release_reason_label') }} *</label>
                            <textarea
                                wire:model="releaseReason"
                                rows="3"
                                placeholder="{{ __('catalogue.release_reason_placeholder') }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                required
                            ></textarea>
                            @error('releaseReason') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                            <button
                                type="submit"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none sm:col-start-2 sm:text-sm"
                            >
                                {{ __('catalogue.submit_release_request') }}
                            </button>
                            <button
                                type="button"
                                wire:click="$set('showReleaseModal', false)"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:col-start-1 sm:text-sm"
                            >
                                {{ __('catalogue.cancel') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

