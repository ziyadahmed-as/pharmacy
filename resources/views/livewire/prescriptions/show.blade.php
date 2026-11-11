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

    @if(session('success'))
        <div class="p-4 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-sm text-emerald-800 dark:text-emerald-200">
            {{ session('success') }}
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
            @if($prescription->cancellation_reason)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-rose-500">Cancellation Reason</dt>
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
                                <span class="text-slate-400 mx-1">→</span>
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
</div>
