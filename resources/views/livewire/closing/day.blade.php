<div class="space-y-6">
    {{-- Header with Date Picker and Lock Status --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('closing.title') }}</h1>
            <p class="text-sm text-slate-500">{{ __('closing.subtitle') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <input type="date"
                   wire:model.live="selectedDate"
                   max="{{ now()->toDateString() }}"
                   class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500">

            @if ($isLocked)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 border border-red-300 px-3 py-1 text-xs font-bold text-red-800 uppercase tracking-wider">
                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                    {{ __('closing.status_locked') }}
                </span>
                <a href="{{ route('closing.report', $existingClosing) }}"
                   target="_blank"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-teal-300 bg-teal-50 px-3.5 py-1.5 text-xs font-bold text-teal-800 hover:bg-teal-100 transition shadow-sm">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    {{ __('closing.view_report') }}
                </a>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-800">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    {{ __('closing.status_open') }}
                </span>
            @endif
        </div>
    </div>

    {{-- Error / Success Alerts --}}
    @if ($errorMessage)
        <div class="rounded-xl bg-red-50 border border-red-200 p-4 text-sm font-medium text-red-800" role="alert">
            {{ $errorMessage }}
        </div>
    @endif
    @if ($successMessage)
        <div class="rounded-xl bg-teal-50 border border-teal-200 p-4 text-sm font-medium text-teal-800" role="alert">
            {{ $successMessage }}
        </div>
    @endif

    {{-- Open Shifts Blocking Warning --}}
    @if ($openShiftsCount > 0)
        <div class="rounded-xl bg-amber-50 border border-amber-300 p-4 flex items-start gap-3 text-sm text-amber-900" role="alert">
            <svg class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            <div>
                <h4 class="font-bold">{{ __('closing.open_shifts_blocking_title') }}</h4>
                <p class="mt-0.5 text-xs text-amber-800">{{ __('closing.open_shifts_exist_error', ['count' => $openShiftsCount]) }}</p>
            </div>
        </div>
    @endif

    {{-- Metric Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ __('closing.completed_sales_metric') }}</span>
            <div class="mt-2 text-2xl font-bold font-mono text-teal-700 tabular-nums">
                {{ number_format($metrics['sales_total'] / 100, 2) }} ETB
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ __('closing.completed_refunds_metric') }}</span>
            <div class="mt-2 text-2xl font-bold font-mono text-amber-700 tabular-nums">
                {{ number_format($metrics['refunds_total'] / 100, 2) }} ETB
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ __('closing.voided_sales_metric') }}</span>
            <div class="mt-2 text-2xl font-bold font-mono text-red-700 tabular-nums">
                {{ number_format($metrics['voids_total'] / 100, 2) }} ETB
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Form: Tender Reconciliation --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-slate-100 bg-slate-50/50 px-6 py-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide">{{ __('closing.tender_reconciliation_title') }}</h2>
                        <p class="text-xs text-slate-500 mt-0.5">{{ __('closing.compare_instructions') }}</p>
                    </div>
                </div>

                <div class="p-6">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                                <th class="text-left pb-3">{{ __('sales.method') }}</th>
                                <th class="text-right pb-3">{{ __('closing.system_total') }} (ETB)</th>
                                <th class="text-right pb-3 w-40">{{ __('closing.counted_actual') }} (ETB)</th>
                                <th class="text-right pb-3 w-32">{{ __('closing.variance') }} (ETB)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($systemTotals as $method => $sysAmount)
                                @php
                                    $diffSantim = $liveVariances[$method] ?? 0;
                                    $diffEtb = $liveVariancesEtb[$method] ?? '0.00';
                                @endphp
                                <tr wire:key="recon-{{ $method }}">
                                    <td class="py-3.5 font-medium text-slate-800 capitalize">
                                        {{ $method === 'cash' ? __('sales.payment_method_cash') : ucfirst(str_replace('_', ' ', $method)) }}
                                    </td>
                                    <td class="py-3.5 text-right font-mono text-slate-600 tabular-nums">
                                        {{ number_format($sysAmount / 100, 2) }}
                                    </td>
                                    <td class="py-3.5 text-right">
                                        @if ($isLocked)
                                            <span class="font-mono font-bold text-slate-800 tabular-nums">
                                                {{ number_format(($existingClosing->counted_totals_by_method[$method] ?? 0) / 100, 2) }}
                                            </span>
                                        @else
                                            <input type="number"
                                                   step="0.01"
                                                   min="0"
                                                   wire:model.live.debounce.300ms="counted.{{ $method }}"
                                                   placeholder="0.00"
                                                   class="w-full text-right rounded-lg border border-slate-300 bg-slate-50 px-3 py-1.5 text-sm font-mono text-slate-900 focus:border-teal-500 focus:bg-white focus:ring-1 focus:ring-teal-500 transition">
                                        @endif
                                    </td>
                                    <td class="py-3.5 text-right font-mono font-bold tabular-nums {{ $diffSantim === 0 ? 'text-teal-700' : ($diffSantim > 0 ? 'text-amber-700' : 'text-red-700') }}">
                                        {{ $diffSantim > 0 ? '+' : '' }}{{ $diffEtb }}
                                    </td>
                                </tr>

                                {{-- Variance Reason Input Row (Mandatory when variance != 0) --}}
                                @if (! $isLocked && $diffSantim !== 0)
                                    <tr wire:key="reason-{{ $method }}" class="bg-red-50/20">
                                        <td colspan="4" class="px-3 py-2">
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-bold text-red-700 shrink-0 uppercase tracking-wide">
                                                    {{ __('closing.reason_required_label') }} ({{ ucfirst(str_replace('_', ' ', $method)) }}):
                                                </span>
                                                <input type="text"
                                                       wire:model="varianceReasons.{{ $method }}"
                                                       placeholder="{{ __('closing.variance_reason_placeholder') }}"
                                                       class="w-full rounded-lg border border-red-300 bg-white px-3 py-1 text-xs text-slate-900 focus:border-red-500 focus:ring-1 focus:ring-red-500 transition">
                                            </div>
                                            @error("varianceReasons.{$method}")
                                                <p class="text-xs text-red-600 mt-1 pl-1 font-semibold">{{ $message }}</p>
                                            @enderror
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-slate-200 font-bold text-slate-900">
                                <td class="pt-4 uppercase text-xs tracking-wider">{{ __('sales.total') }}</td>
                                <td class="pt-4 text-right font-mono tabular-nums text-slate-700">{{ $totalSystemEtb }}</td>
                                <td class="pt-4 text-right font-mono tabular-nums text-slate-800">{{ $totalCountedEtb }}</td>
                                <td class="pt-4 text-right font-mono tabular-nums text-base {{ $totalVarianceSantim === 0 ? 'text-teal-700' : ($totalVarianceSantim > 0 ? 'text-amber-700' : 'text-red-700') }}">
                                    {{ $totalVarianceSantim > 0 ? '+' : '' }}{{ $totalVarianceEtb }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>

                    @if (! $isLocked)
                        <div class="mt-6 border-t border-slate-100 pt-4">
                            <label for="closing-notes" class="block text-xs font-semibold text-slate-700 mb-1">
                                {{ __('closing.manager_notes_label') }}
                            </label>
                            <textarea id="closing-notes"
                                      wire:model="notes"
                                      rows="2"
                                      placeholder="{{ __('closing.manager_notes_placeholder') }}"
                                      class="w-full rounded-xl border border-slate-300 bg-slate-50 p-3 text-sm text-slate-900 focus:border-teal-500 focus:bg-white focus:ring-1 focus:ring-teal-500 transition"></textarea>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button type="button"
                                    wire:click="requestLockDay"
                                    {{ $openShiftsCount > 0 ? 'disabled' : '' }}
                                    class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-6 py-3 text-sm font-bold text-white hover:bg-teal-700 disabled:opacity-40 transition shadow-sm">
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                                <span>{{ __('closing.confirm_and_lock_day_button') }}</span>
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Right Column: Shifts & Held Sales for Review --}}
        <div class="space-y-6">
            {{-- Shifts List --}}
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-slate-100 bg-slate-50/50 px-5 py-3.5 flex items-center justify-between">
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide">{{ __('closing.daily_shifts_heading') }}</h3>
                    <span class="font-mono text-xs text-slate-500">({{ $shifts->count() }})</span>
                </div>
                <div class="divide-y divide-slate-100 text-xs">
                    @forelse ($shifts as $s)
                        <div class="p-3.5 flex items-center justify-between hover:bg-slate-50 transition">
                            <div>
                                <div class="font-bold text-slate-800">{{ $s->till->name }}</div>
                                <div class="text-slate-500 text-[11px]">{{ $s->user->name }} · {{ $s->opened_at->format('H:i') }} - {{ $s->closed_at ? $s->closed_at->format('H:i') : 'Open' }}</div>
                            </div>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 font-bold uppercase tracking-wider text-[10px] {{ $s->isClosed() ? 'bg-teal-50 text-teal-700' : 'bg-amber-100 text-amber-800 animate-pulse' }}">
                                {{ $s->status }}
                            </span>
                        </div>
                    @empty
                        <div class="p-6 text-center text-slate-400">
                            {{ __('closing.no_shifts_found') }}
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Unresolved Held Sales Review --}}
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-slate-100 bg-slate-50/50 px-5 py-3.5 flex items-center justify-between">
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide">{{ __('closing.held_sales_review_heading') }}</h3>
                    <span class="font-mono text-xs {{ $heldSales->count() > 0 ? 'text-amber-700 font-bold' : 'text-slate-500' }}">({{ $heldSales->count() }})</span>
                </div>
                <div class="divide-y divide-slate-100 text-xs">
                    @forelse ($heldSales as $held)
                        <div class="p-3.5 hover:bg-slate-50 transition">
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-slate-800">{{ $held->user->name }}</span>
                                <span class="font-mono font-bold text-teal-700">{{ $held->totalEtb() }} ETB</span>
                            </div>
                            <p class="text-slate-500 text-[11px] mt-0.5">{{ $held->reference ?: 'No note' }} · {{ $held->held_at->format('H:i') }}</p>
                        </div>
                    @empty
                        <div class="p-6 text-center text-slate-400">
                            {{ __('closing.no_unresolved_held_sales') }}
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

