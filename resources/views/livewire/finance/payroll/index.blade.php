<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('payroll.title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('payroll.subtitle') }}</p>
        </div>
        @if ($canManage)
            <div class="flex items-center gap-3">
                @if ($currentRun)
                    <button wire:click="backToList"
                        class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
                        &larr; {{ __('payroll.all_runs') }}
                    </button>
                @endif
                <button wire:click="openPrepareModal"
                    class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    {{ __('payroll.prepare_payroll') }}
                </button>
            </div>
        @endif
    </div>

    {{-- Flash messages --}}
    @if (session('success'))
        <div class="rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-700">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    {{-- Prepare modal --}}
    @if ($showPrepareModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:key="prepare-modal">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-xl p-6 space-y-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-slate-900">{{ __('payroll.prepare_payroll') }}</h2>
                    <button wire:click="closePrepareModal" class="text-slate-400 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">{{ __('payroll.select_month') }}</label>
                        <input type="month" wire:model="prepareMonth"
                            class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                        @error('prepareMonth') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">{{ __('payroll.notes') }}</label>
                        <textarea wire:model="prepareNotes" rows="2"
                            class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <button wire:click="closePrepareModal"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                        {{ __('common.cancel') }}
                    </button>
                    <button wire:click="prepare" wire:loading.attr="disabled"
                        class="rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700 transition disabled:opacity-60">
                        {{ __('payroll.prepare_payroll') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Detail View for a Run --}}
    @if ($currentRun && $canManage)
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-100 pb-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">{{ __('payroll.run_month') }}: {{ $currentRun->month }}</h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Prepared by {{ $currentRun->preparer->name }} on {{ $currentRun->created_at->format('d M Y') }}
                        @if ($currentRun->approver)
                            &bull; Approved by {{ $currentRun->approver->name }}
                        @endif
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    @php
                        $statusLabels = [
                            'draft' => __('payroll.status_draft'),
                            'pending_approval' => __('payroll.status_pending_approval'),
                            'approved' => __('payroll.status_approved'),
                            'paid' => __('payroll.status_paid'),
                            'rejected' => __('payroll.status_rejected'),
                        ];
                        $statusColors = [
                            'draft' => 'bg-slate-100 text-slate-700',
                            'pending_approval' => 'bg-amber-100 text-amber-800',
                            'approved' => 'bg-teal-100 text-teal-800',
                            'paid' => 'bg-emerald-100 text-emerald-800',
                            'rejected' => 'bg-red-100 text-red-800',
                        ];
                    @endphp
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $statusColors[$currentRun->status] ?? '' }}">
                        {{ $statusLabels[$currentRun->status] ?? $currentRun->status }}
                    </span>

                    @if ($currentRun->status === 'draft')
                        <button wire:click="submitApproval({{ $currentRun->id }})" wire:confirm="Submit payroll for Owner approval?"
                            class="rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 transition">
                            {{ __('payroll.submit_approval') }}
                        </button>
                    @elseif ($currentRun->status === 'approved')
                        <button wire:click="recordPayment({{ $currentRun->id }})" wire:confirm="{{ __('payroll.confirm_payment') }}"
                            class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 transition">
                            {{ __('payroll.record_payment') }}
                        </button>
                    @endif
                </div>
            </div>

            {{-- Summary cards --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="rounded-xl bg-slate-50 p-4 border border-slate-100">
                    <p class="text-xs font-semibold uppercase text-slate-500">{{ __('payroll.total_gross') }}</p>
                    <p class="text-lg font-bold text-slate-900 mt-1">ETB {{ number_format($currentRun->total_gross, 2) }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-4 border border-slate-100">
                    <p class="text-xs font-semibold uppercase text-slate-500">{{ __('payroll.total_advances') }}</p>
                    <p class="text-lg font-bold text-amber-700 mt-1">ETB {{ number_format($currentRun->total_advances, 2) }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-4 border border-slate-100">
                    <p class="text-xs font-semibold uppercase text-slate-500">{{ __('payroll.total_deductions') }}</p>
                    <p class="text-lg font-bold text-red-700 mt-1">ETB {{ number_format($currentRun->total_deductions, 2) }}</p>
                </div>
                <div class="rounded-xl bg-teal-50 p-4 border border-teal-100">
                    <p class="text-xs font-semibold uppercase text-teal-700">{{ __('payroll.total_net') }}</p>
                    <p class="text-lg font-bold text-teal-900 mt-1">ETB {{ number_format($currentRun->total_net, 2) }}</p>
                </div>
            </div>

            {{-- Staff Lines Table --}}
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50/75 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">{{ __('payroll.staff_member') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('payroll.base_salary') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('payroll.allowances') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('payroll.overtime') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('payroll.total_gross') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('payroll.advances') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('payroll.deductions') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('payroll.net_pay') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('payroll.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($currentRun->lines as $line)
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ $line->staff->name }}</td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ number_format($line->base_salary, 2) }}</td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ number_format($line->allowances, 2) }}</td>
                                <td class="px-4 py-3 text-right text-slate-700">
                                    {{ number_format($line->overtime_amount, 2) }}
                                    <span class="text-xs text-slate-400">({{ $line->overtime_hours }}h)</span>
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-slate-900">{{ number_format($line->gross, 2) }}</td>
                                <td class="px-4 py-3 text-right text-amber-700">-{{ number_format($line->advances, 2) }}</td>
                                <td class="px-4 py-3 text-right text-red-700">-{{ number_format($line->deductions, 2) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-teal-800">{{ number_format($line->net, 2) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ route('payroll.payslip', $line->id) }}"
                                        class="inline-flex items-center gap-1 text-xs text-teal-600 hover:text-teal-800 font-semibold transition">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        PDF
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($canManage)
        {{-- List of all runs --}}
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50/75 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3.5 text-left">{{ __('payroll.run_month') }}</th>
                            <th class="px-5 py-3.5 text-right">{{ __('payroll.total_gross') }}</th>
                            <th class="px-5 py-3.5 text-right">{{ __('payroll.total_advances') }}</th>
                            <th class="px-5 py-3.5 text-right">{{ __('payroll.total_deductions') }}</th>
                            <th class="px-5 py-3.5 text-right">{{ __('payroll.total_net') }}</th>
                            <th class="px-5 py-3.5 text-left">{{ __('payroll.status') }}</th>
                            <th class="px-5 py-3.5 text-right">{{ __('payroll.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @php
                            $statusLabels = [
                                'draft' => __('payroll.status_draft'),
                                'pending_approval' => __('payroll.status_pending_approval'),
                                'approved' => __('payroll.status_approved'),
                                'paid' => __('payroll.status_paid'),
                                'rejected' => __('payroll.status_rejected'),
                            ];
                            $statusColors = [
                                'draft' => 'bg-slate-100 text-slate-700',
                                'pending_approval' => 'bg-amber-100 text-amber-800',
                                'approved' => 'bg-teal-100 text-teal-800',
                                'paid' => 'bg-emerald-100 text-emerald-800',
                                'rejected' => 'bg-red-100 text-red-800',
                            ];
                        @endphp
                        @forelse ($runs as $run)
                            <tr wire:key="run-{{ $run->id }}" class="hover:bg-slate-50/70 transition">
                                <td class="px-5 py-3.5 font-bold text-slate-900">{{ $run->month }}</td>
                                <td class="px-5 py-3.5 text-right text-slate-700">ETB {{ number_format($run->total_gross, 2) }}</td>
                                <td class="px-5 py-3.5 text-right text-amber-700">ETB {{ number_format($run->total_advances, 2) }}</td>
                                <td class="px-5 py-3.5 text-right text-red-700">ETB {{ number_format($run->total_deductions, 2) }}</td>
                                <td class="px-5 py-3.5 text-right font-bold text-teal-800">ETB {{ number_format($run->total_net, 2) }}</td>
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $statusColors[$run->status] ?? '' }}">
                                        {{ $statusLabels[$run->status] ?? $run->status }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <button wire:click="viewRun({{ $run->id }})"
                                        class="text-xs font-semibold text-teal-600 hover:text-teal-800 transition">
                                        {{ __('payroll.view_run') }} &rarr;
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-sm text-slate-500">
                                    {{ __('payroll.no_runs_found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Staff View: My Payslips --}}
    @if (! $canManage && $staff)
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <h2 class="text-lg font-bold text-slate-900">{{ __('payroll.my_payslips') }}</h2>
            <div class="overflow-x-auto rounded-xl border border-slate-100">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50/75 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">{{ __('payroll.run_month') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('payroll.base_salary') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('payroll.allowances') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('payroll.overtime') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('payroll.advances') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('payroll.net_pay') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('payroll.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($ownLines as $line)
                            <tr wire:key="own-line-{{ $line->id }}" class="hover:bg-slate-50/70 transition">
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ $line->payrollRun->month }}</td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ number_format($line->base_salary, 2) }}</td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ number_format($line->allowances, 2) }}</td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ number_format($line->overtime_amount, 2) }}</td>
                                <td class="px-4 py-3 text-right text-amber-700">-{{ number_format($line->advances, 2) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-teal-800">ETB {{ number_format($line->net, 2) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ route('payroll.payslip', $line->id) }}"
                                        class="inline-flex items-center gap-1 text-xs text-teal-600 hover:text-teal-800 font-semibold transition">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        {{ __('payroll.download_payslip') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">
                                    {{ __('payroll.no_payslips_found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
