<div class="space-y-6">
    <div class="flex items-center justify-between print:hidden">
        <a
            href="{{ route('inventory.disposals.show', $disposal) }}"
            class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            {{ __('disposal.back_to_disposal') }}
        </a>
        <button
            type="button"
            onclick="window.print()"
            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            {{ __('disposal.print_certificate') }}
        </button>
    </div>

    {{-- Printable Certificate Sheet --}}
    <div class="mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white p-8 shadow-sm print:border-none print:p-0 print:shadow-none dark:border-slate-800 dark:bg-slate-900">
        {{-- Header --}}
        <div class="border-b-2 border-slate-900 pb-6 text-center dark:border-slate-100">
            <h1 class="text-xl font-extrabold uppercase tracking-widest text-slate-900 dark:text-slate-100">
                {{ setting('profile.name', 'Pharmacy Management System') }}
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ setting('profile.address', 'Addis Ababa, Ethiopia') }} | {{ __('disposal.licence_no') }}: {{ setting('profile.licence_number', 'PHARM-2026') }}
            </p>
            <h2 class="mt-4 text-lg font-bold uppercase tracking-wider text-slate-800 dark:text-slate-200">
                {{ __('disposal.cert_header_title') }}
            </h2>
            <p class="font-mono text-sm font-semibold text-emerald-700 dark:text-emerald-400">
                {{ $disposal->certificate_no ?? 'DRAFT' }}
            </p>
        </div>

        {{-- Meta info --}}
        <div class="mt-6 grid grid-cols-2 gap-4 text-xs">
            <div>
                <span class="text-slate-500">{{ __('disposal.cert_date') }}:</span>
                <span class="font-bold text-slate-800 dark:text-slate-200">
                    {{ $disposal->disposed_at?->format('Y-m-d H:i') ?? $disposal->created_at->format('Y-m-d') }}
                </span>
            </div>
            <div class="text-right">
                <span class="text-slate-500">{{ __('disposal.cert_status') }}:</span>
                <span class="font-bold uppercase text-slate-800 dark:text-slate-200">
                    {{ $disposal->statusLabel() }}
                </span>
            </div>
        </div>

        {{-- Disposed Item Details Table --}}
        <div class="mt-6">
            <table class="min-w-full divide-y divide-slate-300 border border-slate-200 text-xs dark:divide-slate-700 dark:border-slate-800">
                <thead class="bg-slate-50 dark:bg-slate-800">
                    <tr>
                        <th class="px-3 py-2 text-left font-bold text-slate-700 dark:text-slate-300">{{ __('disposal.table_product') }}</th>
                        <th class="px-3 py-2 text-left font-bold text-slate-700 dark:text-slate-300">{{ __('disposal.batch_no') }}</th>
                        <th class="px-3 py-2 text-left font-bold text-slate-700 dark:text-slate-300">{{ __('disposal.expiry_date') }}</th>
                        <th class="px-3 py-2 text-right font-bold text-slate-700 dark:text-slate-300">{{ __('disposal.quantity') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    <tr>
                        <td class="px-3 py-2">
                            <span class="font-bold text-slate-900 dark:text-slate-100">{{ $disposal->batch->product->brand }}</span>
                            <div class="text-[10px] text-slate-500">{{ $disposal->batch->product->generic_name }}</div>
                            @if ($disposal->isControlled())
                                <span class="text-[10px] font-bold text-purple-700">[{{ __('disposal.controlled_drug') }}]</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 font-mono">{{ $disposal->batch->batch_no }}</td>
                        <td class="px-3 py-2">{{ $disposal->batch->expiry_date?->format('Y-m-d') ?? 'N/A' }}</td>
                        <td class="px-3 py-2 text-right font-bold">{{ number_format($disposal->quantity) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Reason & Method --}}
        <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4 text-xs dark:border-slate-800 dark:bg-slate-800/40">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="font-bold text-slate-700 dark:text-slate-300">{{ __('disposal.method') }}:</span>
                    <span class="text-slate-900 dark:text-slate-100">{{ $disposal->methodLabel() }}</span>
                    @if ($disposal->isReturnToSupplier() && $disposal->supplier_name)
                        <div class="text-slate-600">({{ __('disposal.supplier_name') }}: {{ $disposal->supplier_name }})</div>
                    @endif
                    @if ($disposal->collector_name)
                        <div class="text-slate-600">({{ __('disposal.collector_name') }}: {{ $disposal->collector_name }})</div>
                    @endif
                </div>
                <div>
                    <span class="font-bold text-slate-700 dark:text-slate-300">{{ __('disposal.reason') }}:</span>
                    <span class="text-slate-900 dark:text-slate-100">{{ $disposal->reasonLabel() }}</span>
                </div>
            </div>
            @if ($disposal->notes)
                <div class="mt-2 text-slate-600 dark:text-slate-400">
                    <span class="font-bold">{{ __('disposal.notes') }}:</span> {{ $disposal->notes }}
                </div>
            @endif
        </div>

        {{-- Signature Lines (FR-2.19) --}}
        <div class="mt-12 grid grid-cols-3 gap-6 pt-6 text-xs">
            {{-- 1. Performing Person --}}
            <div class="flex flex-col justify-between">
                <div>
                    <p class="font-bold text-slate-800 dark:text-slate-200">
                        {{ $disposal->performer?->name ?? $disposal->requestedBy->name }}
                    </p>
                    <p class="text-[10px] text-slate-500">{{ __('disposal.licensed_performer') }}</p>
                </div>
                <div class="mt-8 border-t border-slate-400 pt-1 text-center">
                    <p class="text-[10px] text-slate-400">{{ __('disposal.sig_performer') }}</p>
                </div>
            </div>

            {{-- 2. Independent Witness --}}
            <div class="flex flex-col justify-between">
                <div>
                    <p class="font-bold text-slate-800 dark:text-slate-200">
                        {{ $disposal->witness?->name ?? '____________________' }}
                    </p>
                    <p class="text-[10px] text-slate-500">{{ __('disposal.independent_witness') }}</p>
                </div>
                <div class="mt-8 border-t border-slate-400 pt-1 text-center">
                    <p class="text-[10px] text-slate-400">{{ __('disposal.sig_witness') }}</p>
                </div>
            </div>

            {{-- 3. Approving Authority --}}
            <div class="flex flex-col justify-between">
                <div>
                    <p class="font-bold text-slate-800 dark:text-slate-200">
                        {{ $disposal->approvedBy?->name ?? '____________________' }}
                    </p>
                    <p class="text-[10px] text-slate-500">{{ __('disposal.approving_authority') }}</p>
                </div>
                <div class="mt-8 border-t border-slate-400 pt-1 text-center">
                    <p class="text-[10px] text-slate-400">{{ __('disposal.sig_approver') }}</p>
                </div>
            </div>
        </div>

        <div class="mt-8 border-t border-slate-100 pt-4 text-center text-[10px] text-slate-400 dark:border-slate-800">
            {{ __('disposal.cert_footer_notice') }}
        </div>
    </div>
</div>
