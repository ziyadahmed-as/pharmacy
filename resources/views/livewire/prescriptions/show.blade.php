<div class="max-w-4xl mx-auto space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white font-mono">
                    {{ $prescription->prescription_number }}
                </h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $statusBadgeClass }}">
                    {{ $statusLabel }}
                </span>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('prescriptions.fields.patient') }}: {{ $prescription->customer->name }}
            </p>
        </div>
        <a href="{{ route('prescriptions.index') }}" class="px-3 py-1.5 border border-slate-300 dark:border-slate-600 rounded-lg text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
            &larr; {{ __('prescriptions.title') }}
        </a>
    </div>

    @if($successMessage || session('success'))
        <div class="p-4 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-sm text-emerald-800 dark:text-emerald-200">
            {{ $successMessage ?? session('success') }}
        </div>
    @endif

    @if($errorMessage)
        <div class="p-4 rounded-lg bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800 text-sm text-rose-800 dark:text-rose-200">
            {{ $errorMessage }}
        </div>
    @endif

    {{-- Verification Panel (when pending verification) --}}
    @if($canVerify)
        <div class="bg-amber-50/50 dark:bg-amber-950/30 rounded-xl border border-amber-200 dark:border-amber-800 p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-amber-200 dark:border-amber-800 pb-3">
                <h2 class="text-base font-bold text-amber-900 dark:text-amber-200 flex items-center gap-2">
                    <svg class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ __('prescriptions.verification_checks') }}
                </h2>
                <span class="text-xs text-amber-700 dark:text-amber-300 font-medium">
                    {{ __('prescriptions.all_checks_required') }}
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg hover:bg-amber-100/50 dark:hover:bg-amber-900/40">
                    <input type="checkbox" wire:model.live="checkAuthenticity" class="rounded border-amber-300 text-teal-600 focus:ring-teal-500">
                    <span class="text-slate-800 dark:text-slate-200">{{ __('prescriptions.check_authenticity') }}</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg hover:bg-amber-100/50 dark:hover:bg-amber-900/40">
                    <input type="checkbox" wire:model.live="checkCompleteness" class="rounded border-amber-300 text-teal-600 focus:ring-teal-500">
                    <span class="text-slate-800 dark:text-slate-200">{{ __('prescriptions.check_completeness') }}</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg hover:bg-amber-100/50 dark:hover:bg-amber-900/40">
                    <input type="checkbox" wire:model.live="checkDrug" class="rounded border-amber-300 text-teal-600 focus:ring-teal-500">
                    <span class="text-slate-800 dark:text-slate-200">{{ __('prescriptions.check_drug') }}</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg hover:bg-amber-100/50 dark:hover:bg-amber-900/40">
                    <input type="checkbox" wire:model.live="checkDose" class="rounded border-amber-300 text-teal-600 focus:ring-teal-500">
                    <span class="text-slate-800 dark:text-slate-200">{{ __('prescriptions.check_dose') }}</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg hover:bg-amber-100/50 dark:hover:bg-amber-900/40 md:col-span-2">
                    <input type="checkbox" wire:model.live="checkQuantity" class="rounded border-amber-300 text-teal-600 focus:ring-teal-500">
                    <span class="text-slate-800 dark:text-slate-200">{{ __('prescriptions.check_quantity') }}</span>
                </label>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-amber-200 dark:border-amber-800">
                <button
                    type="button"
                    wire:click="openRejectModal"
                    class="px-4 py-2 border border-rose-300 dark:border-rose-700 text-rose-700 dark:text-rose-300 rounded-lg text-sm font-medium hover:bg-rose-50 dark:hover:bg-rose-950/50 transition"
                >
                    {{ __('prescriptions.reject') }}
                </button>
                <button
                    type="button"
                    wire:click="verify"
                    @disabled(! $this->areAllChecksCompleted())
                    class="px-4 py-2 bg-teal-600 hover:bg-teal-700 disabled:bg-slate-300 dark:disabled:bg-slate-700 text-white rounded-lg text-sm font-medium transition shadow-sm"
                >
                    {{ __('prescriptions.verify') }}
                </button>
            </div>
        </div>
    @endif

    {{-- Prescription Details --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h2 class="text-base font-semibold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-700 pb-2 mb-4">
            Prescription Details
        </h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('prescriptions.fields.prescription_number') }}</dt>
                <dd class="mt-1 text-sm font-mono text-slate-900 dark:text-white">{{ $prescription->prescription_number }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('prescriptions.fields.prescription_date') }}</dt>
                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $prescription->prescription_date->format('Y-m-d') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('prescriptions.fields.prescriber_name') }}</dt>
                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $prescription->prescriber_name }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('prescriptions.fields.prescriber_registration_no') }}</dt>
                <dd class="mt-1 text-sm font-mono text-slate-900 dark:text-white">{{ $prescription->prescriber_registration_no }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('prescriptions.fields.patient') }}</dt>
                <dd class="mt-1 text-sm text-slate-900 dark:text-white">
                    <a href="{{ route('customers.show', $prescription->customer) }}" class="text-teal-600 hover:text-teal-800 dark:text-teal-400">
                        {{ $prescription->customer->name }}
                    </a>
                    <span class="text-slate-500 dark:text-slate-400 font-mono text-xs ml-2">{{ $prescription->customer->phone }}</span>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('prescriptions.fields.scan') }}</dt>
                <dd class="mt-1 text-sm">
                    @if($prescription->scan_path)
                        <a href="{{ route('prescriptions.scan', $prescription) }}" target="_blank" class="inline-flex items-center gap-1 text-teal-600 hover:text-teal-800 dark:text-teal-400 font-medium text-xs">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            View Scan
                        </a>
                    @else
                        <span class="text-slate-400 text-xs">No scan uploaded</span>
                    @endif
                </dd>
            </div>

            @if($prescription->verified_by)
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">{{ __('prescriptions.fields.verified_by') }}</dt>
                    <dd class="mt-1 text-sm text-slate-900 dark:text-white">
                        {{ $prescription->verifier->name ?? 'Staff' }} &bull;
                        <span class="text-xs text-slate-500">{{ $prescription->verified_at?->format('Y-m-d H:i') }}</span>
                    </dd>
                </div>
            @endif

            @if($prescription->rejected_by)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-rose-600 dark:text-rose-400">{{ __('prescriptions.fields.rejected_by') }}</dt>
                    <dd class="mt-1 text-sm text-slate-900 dark:text-white">
                        {{ $prescription->rejecter->name ?? 'Staff' }} &bull;
                        <span class="text-xs text-slate-500">{{ $prescription->rejected_at?->format('Y-m-d H:i') }}</span>
                    </dd>
                    <dd class="mt-1 text-xs text-rose-600 dark:text-rose-400 font-mono">
                        {{ __('prescriptions.fields.rejection_reason') }}: {{ $prescription->rejection_reason }}
                    </dd>
                </div>
            @endif

            @if($prescription->cancellation_reason)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Cancellation Reason</dt>
                    <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $prescription->cancellation_reason }}</dd>
                </div>
            @endif
            @if($prescription->replacedBy)
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Replaced By</dt>
                    <dd class="mt-1 text-sm">
                        <a href="{{ route('prescriptions.show', $prescription->replacedBy) }}" class="text-teal-600 hover:text-teal-800 dark:text-teal-400 font-mono">
                            {{ $prescription->replacedBy->prescription_number }}
                        </a>
                    </dd>
                </div>
            @endif
        </dl>
    </div>

    {{-- Product Lines --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Prescription Lines</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-300 font-medium">
                    <tr>
                        <th class="px-6 py-3.5">{{ __('prescriptions.fields.product') }}</th>
                        <th class="px-6 py-3.5">{{ __('prescriptions.fields.dose') }}</th>
                        <th class="px-6 py-3.5 text-right">{{ __('prescriptions.fields.quantity_prescribed') }}</th>
                        <th class="px-6 py-3.5 text-right">{{ __('prescriptions.fields.quantity_dispensed') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach($prescription->lines as $line)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                            <td class="px-6 py-4">
                                <span class="font-medium text-slate-900 dark:text-white">{{ $line->product->brand }}</span>
                                <span class="text-slate-500 dark:text-slate-400 text-xs block">{{ $line->product->generic_name }} {{ $line->product->strength }}</span>
                            </td>
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-300">{{ $line->dose }}</td>
                            <td class="px-6 py-4 text-right font-mono text-slate-900 dark:text-white">{{ $line->quantity_prescribed }}</td>
                            <td class="px-6 py-4 text-right font-mono {{ $line->quantity_dispensed > 0 ? 'text-teal-600 dark:text-teal-400' : 'text-slate-400' }}">
                                {{ $line->quantity_dispensed }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Status History --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h2 class="text-base font-semibold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-700 pb-2 mb-4">
            Status History
        </h2>
        <ol class="relative border-l border-slate-200 dark:border-slate-700 ml-3 space-y-4">
            @foreach($prescription->statusHistory as $entry)
                <li class="ml-6">
                    <span class="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full bg-teal-100 dark:bg-teal-950 ring-8 ring-white dark:ring-slate-800">
                        <svg class="h-3 w-3 text-teal-600 dark:text-teal-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                    </span>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-1">
                        <p class="text-sm font-medium text-slate-900 dark:text-white">
                            @if($entry->from_status)
                                <span class="text-slate-500">{{ $entry->from_status }}</span>
                                <span class="text-slate-400 mx-1">&rarr;</span>
                            @endif
                            <span class="font-semibold">{{ $entry->to_status }}</span>
                        </p>
                        <span class="text-xs text-slate-500 dark:text-slate-400 sm:ml-2">
                            by {{ $entry->user->name ?? 'System' }}
                            @if($entry->role_used) ({{ $entry->role_used }}) @endif
                            &bull; {{ $entry->created_at->format('Y-m-d H:i') }}
                        </span>
                    </div>
                    @if($entry->notes)
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $entry->notes }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    </div>

    {{-- Rejection Modal --}}
    @if($showRejectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-md w-full p-6 space-y-4 border border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-bold text-rose-600 dark:text-rose-400">
                    {{ __('prescriptions.reject_modal_title') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    This will mark the prescription as rejected and record a suspect prescription log entry.
                </p>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('prescriptions.reject_reason_label') }} *
                    </label>
                    <textarea
                        wire:model="rejectionReason"
                        rows="3"
                        placeholder="State reason for suspecting or rejecting prescription..."
                        class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-rose-500"
                    ></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        wire:click="closeRejectModal"
                        class="px-4 py-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 rounded-lg text-sm hover:bg-slate-50 transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        wire:click="reject"
                        class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-sm font-medium transition"
                    >
                        {{ __('prescriptions.reject') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
