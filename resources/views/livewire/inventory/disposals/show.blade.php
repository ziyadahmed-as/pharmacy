<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-3">
                <a
                    href="{{ route('inventory.disposals.index') }}"
                    class="rounded-lg border border-slate-200 p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:border-slate-800 dark:text-slate-400 dark:hover:bg-slate-800"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    {{ __('disposal.details_title', ['id' => $disposal->id]) }}
                </h1>
                @include('livewire.inventory.disposals._status_badge', ['status' => $disposal->status])
            </div>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ __('disposal.requested_on', ['date' => $disposal->created_at->format('Y-m-d H:i'), 'user' => $disposal->requestedBy->name]) }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            @if ($disposal->isApproved())
                <a
                    href="{{ route('inventory.disposals.certificate', $disposal) }}"
                    class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    {{ __('disposal.view_certificate') }}
                </a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Product & Batch details --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                {{ __('disposal.section_item') }}
            </h2>
            <div class="mt-4 space-y-3">
                <div>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('disposal.product_brand') }}</span>
                    <p class="text-base font-bold text-slate-900 dark:text-slate-100">
                        {{ $disposal->batch->product->brand }}
                    </p>
                    <p class="text-xs text-slate-500">{{ $disposal->batch->product->generic_name }}</p>
                </div>
                <div>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('disposal.batch_no') }}</span>
                    <p class="font-mono text-sm font-semibold text-slate-800 dark:text-slate-200">
                        {{ $disposal->batch->batch_no }}
                    </p>
                </div>
                <div>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('disposal.expiry_date') }}</span>
                    <p class="text-sm text-slate-700 dark:text-slate-300">
                        {{ $disposal->batch->expiry_date?->format('Y-m-d') ?? 'N/A' }}
                    </p>
                </div>
                <div>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('disposal.classification') }}</span>
                    <p class="text-sm font-medium uppercase text-slate-800 dark:text-slate-200">
                        {{ $disposal->batch->product->classification }}
                        @if ($disposal->isControlled())
                            <span class="ml-1 inline-flex items-center rounded bg-purple-100 px-2 py-0.5 text-xs font-semibold text-purple-800 dark:bg-purple-900/40 dark:text-purple-300">
                                {{ __('disposal.controlled_drug') }}
                            </span>
                        @endif
                    </p>
                </div>
            </div>
        </div>

        {{-- Disposal Parameters --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                {{ __('disposal.section_disposal') }}
            </h2>
            <div class="mt-4 space-y-3">
                <div>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('disposal.quantity') }}</span>
                    <p class="text-xl font-extrabold text-slate-900 dark:text-slate-100">
                        {{ number_format($disposal->quantity) }}
                    </p>
                </div>
                <div>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('disposal.method') }}</span>
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                        {{ $disposal->methodLabel() }}
                    </p>
                    @if ($disposal->isReturnToSupplier() && $disposal->supplier_name)
                        <p class="text-xs text-slate-500">{{ __('disposal.supplier_name') }}: {{ $disposal->supplier_name }}</p>
                    @endif
                    @if ($disposal->collector_name)
                        <p class="text-xs text-slate-500">{{ __('disposal.collector_name') }}: {{ $disposal->collector_name }}</p>
                    @endif
                </div>
                <div>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('disposal.reason') }}</span>
                    <p class="text-sm text-slate-700 dark:text-slate-300">
                        {{ $disposal->reasonLabel() }}
                    </p>
                </div>
                @if ($disposal->notes)
                    <div>
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('disposal.notes') }}</span>
                        <p class="text-xs italic text-slate-600 dark:text-slate-300">{{ $disposal->notes }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Signatures & Accountability --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                {{ __('disposal.section_accountability') }}
            </h2>
            <div class="mt-4 space-y-3">
                <div>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('disposal.licensed_performer') }}</span>
                    <p class="text-sm font-medium text-slate-800 dark:text-slate-200">
                        {{ $disposal->performer?->name ?? $disposal->requestedBy->name }}
                    </p>
                </div>
                @if ($disposal->witness)
                    <div>
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('disposal.independent_witness') }}</span>
                        <p class="text-sm font-medium text-purple-700 dark:text-purple-300">
                            {{ $disposal->witness->name }}
                        </p>
                    </div>
                @endif
                <div>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('disposal.status') }}</span>
                    <div class="mt-0.5">
                        @include('livewire.inventory.disposals._status_badge', ['status' => $disposal->status])
                    </div>
                </div>
                @if ($disposal->isApproved())
                    <div>
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('disposal.approved_by') }}</span>
                        <p class="text-sm font-medium text-emerald-700 dark:text-emerald-300">
                            {{ $disposal->approvedBy?->name }} ({{ $disposal->disposed_at?->format('Y-m-d H:i') }})
                        </p>
                        <p class="text-xs font-mono text-slate-500">{{ $disposal->certificate_no }}</p>
                    </div>
                @elseif ($disposal->isRejected())
                    <div>
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('disposal.rejected_by') }}</span>
                        <p class="text-sm font-medium text-rose-700 dark:text-rose-300">
                            {{ $disposal->rejectedBy?->name }}
                        </p>
                        @if ($disposal->rejection_reason)
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $disposal->rejection_reason }}</p>
                        @endif
                    </div>
                @elseif ($disposal->isPending())
                    <div class="rounded-lg bg-amber-50 p-3 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                        {{ __('disposal.awaiting_approval_msg') }}
                        @if ($disposal->approvalRequest)
                            <div class="mt-1">
                                <a
                                    href="/approvals"
                                    class="font-bold underline hover:text-amber-900 dark:hover:text-amber-100"
                                >
                                    {{ __('disposal.go_to_approvals') }} #{{ $disposal->approvalRequest->id }}
                                </a>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Valuation / Cost (Owners & PM only) --}}
                @if ($isManager)
                    <div class="border-t border-slate-100 pt-3 dark:border-slate-800">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('disposal.loss_valuation') }}</span>
                        <p class="text-sm font-extrabold text-slate-900 dark:text-slate-100">
                            {{ number_format($disposal->lossValue() / 100, 2) }} {{ setting('profile.currency', 'ETB') }}
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Attached Evidence File --}}
    @if ($disposal->evidence_path)
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                {{ __('disposal.evidence_document') }}
            </h2>
            <div class="mt-4">
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    {{ __('disposal.evidence_attached') }}: <span class="font-mono text-xs">{{ basename($disposal->evidence_path) }}</span>
                </p>
            </div>
        </div>
    @endif
</div>
