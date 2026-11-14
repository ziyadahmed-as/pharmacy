<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('catalogue.stock_adjustments_title') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('catalogue.stock_adjustments_subtitle') }}</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('inventory.adjustments.create') }}" class="inline-flex items-center px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-sm font-semibold transition shadow-sm">
                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ __('catalogue.new_adjustment_request') }}
            </a>
            <a href="{{ route('inventory.stock') }}" class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                &larr; {{ __('catalogue.stock_on_hand_title') }}
            </a>
        </div>
    </div>

    @if ($successMessage || session('success'))
        <div class="p-4 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-sm text-emerald-800 dark:text-emerald-200">
            {{ $successMessage ?? session('success') }}
        </div>
    @endif

    @if ($errorMessage)
        <div class="p-4 rounded-lg bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800 text-sm text-rose-800 dark:text-rose-200">
            {{ $errorMessage }}
        </div>
    @endif

    {{-- Filter Panel --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ __('common.search') }}</label>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('catalogue.search_stock_placeholder') }}"
                class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-teal-500"
            />
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ __('catalogue.reason_type') }}</label>
            <select
                wire:model.live="reasonFilter"
                class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-teal-500"
            >
                <option value="">{{ __('common.all') }}</option>
                @foreach ($reasonOptions as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ __('common.status') }}</label>
            <select
                wire:model.live="statusFilter"
                class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-teal-500"
            >
                <option value="">{{ __('common.all') }}</option>
                @foreach ($statusOptions as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Adjustments Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm text-left">
                <thead class="bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-300 font-medium text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3.5">{{ __('common.date') }}</th>
                        <th class="px-6 py-3.5">{{ __('catalogue.product') }}</th>
                        <th class="px-6 py-3.5">{{ __('catalogue.batch_no') }}</th>
                        <th class="px-6 py-3.5">{{ __('catalogue.reason_type') }}</th>
                        <th class="px-6 py-3.5 text-right">{{ __('catalogue.quantity') }}</th>
                        <th class="px-6 py-3.5">{{ __('catalogue.recount_status') }}</th>
                        <th class="px-6 py-3.5">{{ __('catalogue.approval_status') }}</th>
                        <th class="px-6 py-3.5 text-right">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse ($adjustments as $adj)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                            <td class="px-6 py-4 text-xs text-slate-500 whitespace-nowrap">
                                {{ $adj->created_at->format('Y-m-d H:i') }}
                                <span class="block text-slate-400">by {{ $adj->requester->name ?? 'Staff' }}</span>
                            </td>
                            <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">
                                {{ $adj->batch->product->brand }}
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $adj->batch->product->generic_name }}</span>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                {{ $adj->batch->batch_no }}
                                <span class="block text-slate-400 text-xs">Exp: {{ $adj->batch->expiry_date?->format('Y-m-d') }}</span>
                            </td>
                            <td class="px-6 py-4 text-xs">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200">
                                    {{ $reasonOptions[$adj->reason_type] ?? $adj->reason_type }}
                                </span>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-xs truncate" title="{{ $adj->written_reason }}">
                                    {{ $adj->written_reason }}
                                </p>
                            </td>
                            <td class="px-6 py-4 text-right font-mono font-bold whitespace-nowrap {{ $adj->quantity > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ $adj->quantity > 0 ? '+'.$adj->quantity : $adj->quantity }}
                            </td>
                            <td class="px-6 py-4 text-xs whitespace-nowrap">
                                @if ($adj->recount_done)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                        ✓ {{ __('catalogue.recount_done_badge') }}
                                    </span>
                                    <span class="block text-xs text-slate-400 mt-0.5">by {{ $adj->recounter->name ?? 'Staff' }}</span>
                                @elseif ($adj->requiresRecount())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                        ⚠ {{ __('catalogue.recount_needed_badge') }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400">{{ __('catalogue.recount_not_required') }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs whitespace-nowrap">
                                @if ($adj->isPending())
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                        {{ __('common.status_pending') }}
                                    </span>
                                @elseif ($adj->isApproved())
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                        {{ __('approvals.status.approved') }}
                                    </span>
                                @elseif ($adj->isRejected())
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                                        {{ __('approvals.status.rejected') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                        {{ __('approvals.status.expired') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right text-xs whitespace-nowrap space-x-2">
                                @if ($adj->isPending() && ! $adj->recount_done && $adj->requiresRecount())
                                    <button
                                        type="button"
                                        wire:click="openRecountModal({{ $adj->id }})"
                                        class="px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded text-xs font-medium transition"
                                    >
                                        {{ __('catalogue.record_recount_btn') }}
                                    </button>
                                @endif

                                @if ($adj->approval_request_id)
                                    <a href="{{ route('approvals.show', $adj->approval_request_id) }}" class="text-teal-600 hover:text-teal-800 dark:text-teal-400 font-medium">
                                        Approval &rarr;
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                {{ __('catalogue.no_adjustments_found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-slate-200 dark:border-slate-700">
            {{ $adjustments->links() }}
        </div>
    </div>

    {{-- Recount Modal --}}
    @if ($showRecountModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-md w-full p-6 space-y-4 border border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">
                    {{ __('catalogue.record_recount_title') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('catalogue.record_recount_desc') }}
                </p>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('catalogue.recount_notes_label') }}
                    </label>
                    <textarea
                        wire:model="recountNotes"
                        rows="3"
                        placeholder="State recount observations, independent counter details, or verification notes..."
                        class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-teal-500"
                    ></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        wire:click="closeRecountModal"
                        class="px-4 py-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 rounded-lg text-sm hover:bg-slate-50 transition"
                    >
                        {{ __('common.cancel') }}
                    </button>
                    <button
                        type="button"
                        wire:click="submitRecount"
                        class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-sm font-medium transition"
                    >
                        {{ __('common.confirm') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
