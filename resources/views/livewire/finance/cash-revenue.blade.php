<div class="space-y-6">
    {{-- Header & Export Actions --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                {{ __('cash_revenue.title') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('cash_revenue.subtitle') }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a
                href="{{ route('finance.cash-revenue.export', ['format' => 'csv', 'start_date' => $startDate, 'end_date' => $endDate]) }}"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            >
                <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                {{ __('cash_revenue.export_excel') }}
            </a>
            <a
                href="{{ route('finance.cash-revenue.export', ['format' => 'pdf', 'start_date' => $startDate, 'end_date' => $endDate]) }}"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            >
                <svg class="h-4 w-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                {{ __('cash_revenue.export_pdf') }}
            </a>
            <a
                href="{{ route('finance.cash-revenue.print', ['start_date' => $startDate, 'end_date' => $endDate]) }}"
                target="_blank"
                class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                {{ __('cash_revenue.print') }}
            </a>
        </div>
    </div>

    {{-- E1 Disclaimer Banner (STEP 3) --}}
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm dark:border-amber-900/50 dark:bg-amber-950/40">
        <div class="flex items-start gap-3">
            <svg class="h-5 w-5 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
                <h3 class="text-sm font-bold text-amber-900 dark:text-amber-200">
                    {{ __('cash_revenue.disclaimer_title') }}
                </h3>
                <p class="mt-0.5 text-xs text-amber-800 dark:text-amber-300">
                    {{ __('cash_revenue.disclaimer_body') }}
                </p>
            </div>
        </div>
    </div>

    {{-- Date Filters & Mode Switcher --}}
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 space-y-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-1 rounded-lg bg-slate-100 p-1 dark:bg-slate-800">
                <button
                    type="button"
                    wire:click="setMode('day')"
                    class="rounded-md px-3 py-1.5 text-xs font-semibold transition {{ $mode === 'day' ? 'bg-white shadow text-slate-900 dark:bg-slate-700 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}"
                >
                    {{ __('cash_revenue.mode_day') }}
                </button>
                <button
                    type="button"
                    wire:click="setMode('period')"
                    class="rounded-md px-3 py-1.5 text-xs font-semibold transition {{ $mode === 'period' ? 'bg-white shadow text-slate-900 dark:bg-slate-700 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}"
                >
                    {{ __('cash_revenue.mode_period') }}
                </button>
            </div>

            {{-- Quick Filter Shortcuts --}}
            <div class="flex flex-wrap items-center gap-1.5">
                <button
                    type="button"
                    wire:click="setToday"
                    class="rounded px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                >
                    {{ __('cash_revenue.quick_today') }}
                </button>
                <button
                    type="button"
                    wire:click="setYesterday"
                    class="rounded px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                >
                    {{ __('cash_revenue.quick_yesterday') }}
                </button>
                <button
                    type="button"
                    wire:click="setThisWeek"
                    class="rounded px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                >
                    {{ __('cash_revenue.quick_this_week') }}
                </button>
                <button
                    type="button"
                    wire:click="setThisMonth"
                    class="rounded px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                >
                    {{ __('cash_revenue.quick_this_month') }}
                </button>
                <button
                    type="button"
                    wire:click="setLastMonth"
                    class="rounded px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                >
                    {{ __('cash_revenue.quick_last_month') }}
                </button>
            </div>
        </div>

        {{-- Date Inputs --}}
        <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center gap-4">
            @if ($mode === 'day')
                <div class="flex items-center gap-2">
                    <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                        {{ __('cash_revenue.date') }}:
                    </label>
                    <input
                        type="date"
                        wire:model.live="selectedDate"
                        class="rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                    />
                </div>
            @else
                <div class="flex items-center gap-2">
                    <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                        {{ __('cash_revenue.start_date') }}:
                    </label>
                    <input
                        type="date"
                        wire:model.live="startDate"
                        class="rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                        {{ __('cash_revenue.end_date') }}:
                    </label>
                    <input
                        type="date"
                        wire:model.live="endDate"
                        class="rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                    />
                </div>
            @endif

            <span class="text-xs text-slate-400">
                ({{ $startDate }} @if($startDate !== $endDate) &rarr; {{ $endDate }} @endif)
            </span>
        </div>
    </div>

    {{-- 1. Revenue & Sales Summary Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Gross Sales --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                {{ __('cash_revenue.gross_sales') }}
            </div>
            <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">
                {{ number_format($revenue['gross_sales'] / 100, 2) }} <span class="text-xs font-normal text-slate-500">{{ $currency }}</span>
            </div>
            <div class="mt-1 text-xs text-slate-500">
                {{ trans_choice('cash_revenue.transaction_count', $revenue['gross_sales_count'], ['count' => $revenue['gross_sales_count']]) }}
            </div>
        </div>

        {{-- Refunds --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                {{ __('cash_revenue.refunds') }}
            </div>
            <div class="mt-2 text-2xl font-bold text-rose-600 dark:text-rose-400">
                -{{ number_format($revenue['refunds'] / 100, 2) }} <span class="text-xs font-normal text-slate-500">{{ $currency }}</span>
            </div>
            <div class="mt-1 text-xs text-slate-500">
                {{ trans_choice('cash_revenue.transaction_count', $revenue['refunds_count'], ['count' => $revenue['refunds_count']]) }}
            </div>
        </div>

        {{-- Net Sales --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                {{ __('cash_revenue.net_sales') }}
            </div>
            <div class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                {{ number_format($revenue['net_sales'] / 100, 2) }} <span class="text-xs font-normal text-slate-500">{{ $currency }}</span>
            </div>
            <div class="mt-1 text-xs text-slate-500">
                {{ __('cash_revenue.gross_sales') }} - {{ __('cash_revenue.refunds') }}
            </div>
        </div>

        {{-- Operating Surplus --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                {{ __('cash_revenue.operating_surplus') }}
            </div>
            <div class="mt-2 text-2xl font-bold {{ $revenue['operating_surplus'] >= 0 ? 'text-slate-900 dark:text-slate-100' : 'text-rose-600 dark:text-rose-400' }}">
                {{ number_format($revenue['operating_surplus'] / 100, 2) }} <span class="text-xs font-normal text-slate-500">{{ $currency }}</span>
            </div>
            <div class="mt-1 text-xs text-slate-500">
                {{ __('cash_revenue.net_sales') }} - {{ __('cash_revenue.operating_expenses') }}
            </div>
        </div>
    </div>

    {{-- 2. Detailed Revenue & Expenses Breakdown Table --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Revenue Summary Table --}}
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800">
                <h3 class="font-bold text-slate-900 dark:text-slate-100">
                    {{ __('cash_revenue.revenue_summary') }}
                </h3>
            </div>
            <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-slate-800">
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr>
                        <td class="px-5 py-3 text-slate-600 dark:text-slate-400">{{ __('cash_revenue.gross_sales') }}</td>
                        <td class="px-5 py-3 text-right font-mono font-medium text-slate-900 dark:text-slate-100">
                            {{ number_format($revenue['gross_sales'] / 100, 2) }} {{ $currency }}
                        </td>
                    </tr>
                    <tr>
                        <td class="px-5 py-3 text-slate-600 dark:text-slate-400">{{ __('cash_revenue.refunds') }}</td>
                        <td class="px-5 py-3 text-right font-mono font-medium text-rose-600 dark:text-rose-400">
                            -{{ number_format($revenue['refunds'] / 100, 2) }} {{ $currency }}
                        </td>
                    </tr>
                    <tr class="bg-slate-50/50 dark:bg-slate-800/40">
                        <td class="px-5 py-3 font-semibold text-slate-900 dark:text-slate-100">{{ __('cash_revenue.net_sales') }}</td>
                        <td class="px-5 py-3 text-right font-mono font-bold text-slate-900 dark:text-slate-100">
                            {{ number_format($revenue['net_sales'] / 100, 2) }} {{ $currency }}
                        </td>
                    </tr>
                    <tr>
                        <td class="px-5 py-3 text-slate-600 dark:text-slate-400">{{ __('cash_revenue.operating_expenses') }}</td>
                        <td class="px-5 py-3 text-right font-mono font-medium text-rose-600 dark:text-rose-400">
                            -{{ number_format($revenue['expenses'] / 100, 2) }} {{ $currency }}
                        </td>
                    </tr>
                    <tr class="bg-emerald-50/40 dark:bg-emerald-950/20">
                        <td class="px-5 py-3 font-bold text-emerald-900 dark:text-emerald-200">{{ __('cash_revenue.operating_surplus') }}</td>
                        <td class="px-5 py-3 text-right font-mono font-bold text-emerald-700 dark:text-emerald-300">
                            {{ number_format($revenue['operating_surplus'] / 100, 2) }} {{ $currency }}
                        </td>
                    </tr>
                    <tr>
                        <td class="px-5 py-3 text-xs text-slate-400">{{ __('cash_revenue.voids') }} ({{ $revenue['voids_count'] }})</td>
                        <td class="px-5 py-3 text-right font-mono text-xs text-slate-400">
                            {{ number_format($revenue['voids'] / 100, 2) }} {{ $currency }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Operating Expenses Breakdown Table --}}
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <h3 class="font-bold text-slate-900 dark:text-slate-100">
                    {{ __('cash_revenue.expense_breakdown') }}
                </h3>
                <span class="text-xs text-slate-500 font-mono">
                    {{ number_format($revenue['expenses'] / 100, 2) }} {{ $currency }}
                </span>
            </div>
            <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-slate-800">
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($revenue['expenses_by_category'] as $cat => $amount)
                        <tr>
                            <td class="px-5 py-3 capitalize text-slate-600 dark:text-slate-400">
                                {{ $cat }}
                            </td>
                            <td class="px-5 py-3 text-right font-mono text-slate-900 dark:text-slate-100">
                                {{ number_format($amount / 100, 2) }} {{ $currency }}
                            </td>
                        </tr>
                    @endforeach
                    <tr class="bg-slate-50/50 dark:bg-slate-800/40">
                        <td class="px-5 py-3 font-semibold text-slate-900 dark:text-slate-100">{{ __('cash_revenue.total') }}</td>
                        <td class="px-5 py-3 text-right font-mono font-bold text-slate-900 dark:text-slate-100">
                            {{ number_format($revenue['expenses'] / 100, 2) }} {{ $currency }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- 3. Cash & Tender Summary by Payment Method --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800">
            <h3 class="font-bold text-slate-900 dark:text-slate-100">
                {{ __('cash_revenue.cash_summary') }}
            </h3>
        </div>
        <table class="min-w-full divide-y divide-slate-200 text-left text-sm dark:divide-slate-800">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                <tr>
                    <th class="px-5 py-3">{{ __('cash_revenue.payment_method') }}</th>
                    <th class="px-5 py-3 text-right">{{ __('cash_revenue.received') }}</th>
                    <th class="px-5 py-3 text-right">{{ __('cash_revenue.refunded') }}</th>
                    <th class="px-5 py-3 text-right">{{ __('cash_revenue.net_cash') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                @foreach ($cash['methods'] as $m)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-5 py-3 font-medium text-slate-900 dark:text-slate-100">
                            {{ $m['label'] }}
                        </td>
                        <td class="px-5 py-3 text-right font-mono text-slate-700 dark:text-slate-300">
                            {{ number_format($m['received'] / 100, 2) }} {{ $currency }}
                        </td>
                        <td class="px-5 py-3 text-right font-mono {{ $m['refunded'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-400' }}">
                            {{ number_format($m['refunded'] / 100, 2) }} {{ $currency }}
                        </td>
                        <td class="px-5 py-3 text-right font-mono font-bold text-slate-900 dark:text-slate-100">
                            {{ number_format($m['net'] / 100, 2) }} {{ $currency }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t-2 border-slate-200 bg-slate-50 font-bold dark:border-slate-700 dark:bg-slate-800/70">
                <tr>
                    <td class="px-5 py-3 text-slate-900 dark:text-slate-100">{{ __('cash_revenue.total') }}</td>
                    <td class="px-5 py-3 text-right font-mono text-slate-900 dark:text-slate-100">
                        {{ number_format($cash['total_received'] / 100, 2) }} {{ $currency }}
                    </td>
                    <td class="px-5 py-3 text-right font-mono text-rose-600 dark:text-rose-400">
                        {{ number_format($cash['total_refunded'] / 100, 2) }} {{ $currency }}
                    </td>
                    <td class="px-5 py-3 text-right font-mono text-emerald-600 dark:text-emerald-400">
                        {{ number_format($cash['total_net'] / 100, 2) }} {{ $currency }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- 4. Daily Reconciliation from Locked Days (FR-6.6, AC-24) --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <h3 class="font-bold text-slate-900 dark:text-slate-100">
                {{ __('cash_revenue.reconciliation_title') }}
            </h3>
            <span class="text-xs text-slate-500 font-medium">
                {{ $cash['locked_days_summary']['days_count'] }} locked days
            </span>
        </div>
        <table class="min-w-full divide-y divide-slate-200 text-left text-sm dark:divide-slate-800">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                <tr>
                    <th class="px-5 py-3">{{ __('cash_revenue.date') }}</th>
                    <th class="px-5 py-3">{{ __('cash_revenue.closer') }}</th>
                    <th class="px-5 py-3 text-right">{{ __('cash_revenue.system_total') }}</th>
                    <th class="px-5 py-3 text-right">{{ __('cash_revenue.counted_total') }}</th>
                    <th class="px-5 py-3 text-right">{{ __('cash_revenue.variance') }}</th>
                    <th class="px-5 py-3">{{ __('cash_revenue.status') }}</th>
                    <th class="px-5 py-3">{{ __('cash_revenue.notes') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                @forelse ($cash['locked_days'] as $ld)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="whitespace-nowrap px-5 py-3 font-medium text-slate-900 dark:text-slate-100">
                            {{ $ld['date'] }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-400">
                            {{ $ld['closer'] }}
                            @if ($ld['verifier'])
                                <span class="text-xs text-slate-400">/ {{ $ld['verifier'] }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-right font-mono text-slate-700 dark:text-slate-300">
                            {{ number_format($ld['system_total'] / 100, 2) }} {{ $currency }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-right font-mono text-slate-700 dark:text-slate-300">
                            {{ number_format($ld['counted_total'] / 100, 2) }} {{ $currency }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-right font-mono font-bold {{ $ld['total_variance'] === 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            {{ $ld['total_variance'] > 0 ? '+' : '' }}{{ number_format($ld['total_variance'] / 100, 2) }} {{ $currency }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-3">
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                {{ __('cash_revenue.locked') }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-xs text-slate-500">
                            @if (! empty($ld['variance_reasons']))
                                @foreach ($ld['variance_reasons'] as $m => $r)
                                    <div><strong class="capitalize">{{ $m }}</strong>: {{ $r }}</div>
                                @endforeach
                            @elseif ($ld['notes'])
                                {{ $ld['notes'] }}
                            @else
                                <span class="text-slate-400">{{ __('cash_revenue.no_variance') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-8 text-center text-slate-500 dark:text-slate-400">
                            {{ __('cash_revenue.no_locked_days') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if (! empty($cash['locked_days']))
                <tfoot class="border-t-2 border-slate-200 bg-slate-50 font-bold dark:border-slate-700 dark:bg-slate-800/70">
                    <tr>
                        <td colspan="2" class="px-5 py-3 text-slate-900 dark:text-slate-100">{{ __('cash_revenue.total') }}</td>
                        <td class="px-5 py-3 text-right font-mono text-slate-900 dark:text-slate-100">
                            {{ number_format($cash['locked_days_summary']['system_total'] / 100, 2) }} {{ $currency }}
                        </td>
                        <td class="px-5 py-3 text-right font-mono text-slate-900 dark:text-slate-100">
                            {{ number_format($cash['locked_days_summary']['counted_total'] / 100, 2) }} {{ $currency }}
                        </td>
                        <td class="px-5 py-3 text-right font-mono {{ $cash['locked_days_summary']['variance'] === 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                            {{ number_format($cash['locked_days_summary']['variance'] / 100, 2) }} {{ $currency }}
                        </td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

