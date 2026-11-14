<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                {{ __('backup.checklist_title') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('backup.checklist_subtitle') }}
            </p>
        </div>
    </div>

    @if ($latestRestore)
        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-xs text-blue-900 dark:border-blue-900/40 dark:bg-blue-950/40 dark:text-blue-200">
            <strong>{{ __('backup.latest_restoration_info') }}</strong>:
            Archive <span class="font-mono font-bold">{{ $latestRestore->backup_filename }}</span> |
            Status: <span class="font-bold uppercase">{{ $latestRestore->status }}</span> |
            Approved by: {{ $latestRestore->approvedBy?->name ?? '-' }} |
            Restored at: {{ $latestRestore->restored_at?->format('Y-m-d H:i') ?? '-' }}
        </div>
    @endif

    {{-- Key Operational Figures --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {{-- Product Catalog --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <span class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">
                {{ __('backup.check_products_title') }}
            </span>
            <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">
                {{ number_format($productCount) }}
            </p>
            <p class="text-xs text-slate-500">
                {{ number_format($batchCount) }} {{ __('backup.active_batches_count') }}
            </p>
        </div>

        {{-- Inventory Stock Valuation --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <span class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">
                {{ __('backup.check_stock_valuation') }}
            </span>
            <p class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                {{ number_format($totalStockValue / 100, 2) }} {{ setting('profile.currency', 'ETB') }}
            </p>
            <p class="text-xs text-slate-500">
                {{ __('backup.valuation_recalculated_msg') }}
            </p>
        </div>

        {{-- Audit Log Rows --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <span class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">
                {{ __('backup.check_audit_integrity') }}
            </span>
            <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">
                {{ number_format($auditRowsCount) }}
            </p>
            <p class="text-xs text-slate-500">
                {{ __('backup.audit_unbroken_msg') }}
            </p>
        </div>

        {{-- Pending Prescriptions --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <span class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">
                {{ __('backup.check_prescriptions_pending') }}
            </span>
            <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">
                {{ number_format($pendingPrescriptions) }}
            </p>
            <p class="text-xs text-slate-500">
                {{ __('backup.clinical_status_msg') }}
            </p>
        </div>

        {{-- Open Shifts --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <span class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">
                {{ __('backup.check_open_shifts') }}
            </span>
            <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">
                {{ number_format($openShifts) }}
            </p>
            <p class="text-xs text-slate-500">
                {{ __('backup.pos_till_shifts_msg') }}
            </p>
        </div>
    </div>

    {{-- Verification Checklist --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">
            {{ __('backup.verification_checklist_heading') }}
        </h2>
        <div class="mt-4 space-y-4">
            <div class="flex items-start gap-3">
                <input type="checkbox" id="chk1" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                <label for="chk1" class="text-sm text-slate-700 dark:text-slate-300">
                    <strong>{{ __('backup.chk_catalog_title') }}</strong>: {{ __('backup.chk_catalog_desc') }}
                </label>
            </div>
            <div class="flex items-start gap-3">
                <input type="checkbox" id="chk2" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                <label for="chk2" class="text-sm text-slate-700 dark:text-slate-300">
                    <strong>{{ __('backup.chk_ledger_title') }}</strong>: {{ __('backup.chk_ledger_desc') }}
                </label>
            </div>
            <div class="flex items-start gap-3">
                <input type="checkbox" id="chk3" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                <label for="chk3" class="text-sm text-slate-700 dark:text-slate-300">
                    <strong>{{ __('backup.chk_audit_title') }}</strong>: {{ __('backup.chk_audit_desc') }}
                </label>
            </div>
            <div class="flex items-start gap-3">
                <input type="checkbox" id="chk4" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                <label for="chk4" class="text-sm text-slate-700 dark:text-slate-300">
                    <strong>{{ __('backup.chk_prescriptions_title') }}</strong>: {{ __('backup.chk_prescriptions_desc') }}
                </label>
            </div>
        </div>
    </div>
</div>

