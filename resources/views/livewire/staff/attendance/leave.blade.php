<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('attendance.leave_title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('attendance.my_leave_requests') }}</p>
        </div>
        @can('leave.request')
            <button wire:click="openForm"
                class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 transition">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ __('attendance.submit_leave') }}
            </button>
        @endcan
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

    {{-- Leave request form modal --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:key="leave-modal">
            <div class="w-full max-w-lg rounded-2xl bg-white shadow-xl p-6 space-y-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-slate-900">{{ __('attendance.submit_leave') }}</h2>
                    <button wire:click="closeForm" class="text-slate-400 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="space-y-4">
                    {{-- Leave type --}}
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">{{ __('attendance.leave_type') }}</label>
                        <select wire:model.live="leaveTypeId"
                            class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                            <option value="">— {{ __('attendance.leave_type') }} —</option>
                            @foreach ($leaveTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }} ({{ $type->days_per_year }}d/yr)</option>
                            @endforeach
                        </select>
                        @error('leaveTypeId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Dates --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">{{ __('attendance.start_date') }}</label>
                            <input type="date" wire:model.live="startDate"
                                class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                            @error('startDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">{{ __('attendance.end_date') }}</label>
                            <input type="date" wire:model.live="endDate"
                                class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                            @error('endDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Days counter --}}
                    @if ($daysRequested > 0)
                        <p class="text-sm text-slate-600">
                            {{ __('attendance.days_requested') }}: <strong>{{ $daysRequested }}</strong>
                        </p>
                    @endif

                    {{-- Reason --}}
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">{{ __('attendance.reason') }}</label>
                        <textarea wire:model="reason" rows="2"
                            class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition"></textarea>
                        @error('reason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <button wire:click="closeForm"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                        {{ __('common.cancel') }}
                    </button>
                    <button wire:click="submit" wire:loading.attr="disabled"
                        class="rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700 transition disabled:opacity-60">
                        {{ __('attendance.submit_leave') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Filters (PM / Owner only) --}}
    @if ($canAll)
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">
                    {{ __('attendance.filter_by_staff') }}
                </label>
                <select wire:model.live="filterStaffId"
                    class="rounded-lg border border-slate-300 py-1.5 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                    <option value="">{{ __('attendance.all_staff') }}</option>
                    @if ($ownStaff)
                        <option value="{{ $ownStaff->id }}">{{ $ownStaff->name }}</option>
                    @endif
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">
                    {{ __('attendance.col_status') }}
                </label>
                <select wire:model.live="filterStatus"
                    class="rounded-lg border border-slate-300 py-1.5 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                    <option value="">{{ __('common.all') }}</option>
                    @foreach ([
                        'pending'   => __('attendance.status_pending'),
                        'approved'  => __('attendance.status_approved'),
                        'rejected'  => __('attendance.status_rejected'),
                        'cancelled' => __('attendance.status_cancelled'),
                    ] as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    @endif

    {{-- Table --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50/75 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        @if ($canAll)
                            <th class="px-5 py-3.5 text-left">{{ __('attendance.col_staff') }}</th>
                        @endif
                        <th class="px-5 py-3.5 text-left">{{ __('attendance.col_leave_type') }}</th>
                        <th class="px-5 py-3.5 text-left">{{ __('attendance.col_period') }}</th>
                        <th class="px-5 py-3.5 text-left">{{ __('attendance.col_days') }}</th>
                        <th class="px-5 py-3.5 text-left">{{ __('attendance.col_status') }}</th>
                        <th class="px-5 py-3.5 text-left">{{ __('attendance.col_submitted') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('attendance.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($requests as $req)
                        <tr wire:key="lr-{{ $req->id }}" class="hover:bg-slate-50/70 transition">
                            @if ($canAll)
                                <td class="px-5 py-3.5 font-medium text-slate-900">
                                    {{ $req->staff->name }}
                                    @if ($req->is_over_balance)
                                        <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700" title="{{ __('attendance.over_balance_warning') }}">!</span>
                                    @endif
                                </td>
                            @endif
                            <td class="px-5 py-3.5 text-slate-700">{{ $req->leaveType->name }}</td>
                            <td class="px-5 py-3.5 text-slate-700">
                                {{ $req->start_date->format('d M Y') }} – {{ $req->end_date->format('d M Y') }}
                            </td>
                            <td class="px-5 py-3.5 text-slate-700">{{ $req->days }}</td>
                            <td class="px-5 py-3.5">
                                @php
                                    $colors = [
                                        'pending'   => 'bg-amber-100 text-amber-800',
                                        'approved'  => 'bg-teal-100 text-teal-800',
                                        'rejected'  => 'bg-red-100 text-red-800',
                                        'cancelled' => 'bg-slate-100 text-slate-600',
                                    ];
                                    $labels = [
                                        'pending'   => __('attendance.status_pending'),
                                        'approved'  => __('attendance.status_approved'),
                                        'rejected'  => __('attendance.status_rejected'),
                                        'cancelled' => __('attendance.status_cancelled'),
                                    ];
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $colors[$req->status] ?? '' }}">
                                    {{ $labels[$req->status] ?? $req->status }}
                                </span>
                                @if ($req->is_over_balance && ! $canAll)
                                    <span class="ml-1 text-xs text-amber-600 font-medium">{{ __('attendance.over_balance_warning') }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-slate-500">{{ $req->created_at->diffForHumans() }}</td>
                            <td class="px-5 py-3.5 text-right">
                                @if ($req->status === 'pending')
                                    <button wire:click="cancel({{ $req->id }})" wire:confirm="Cancel this leave request?"
                                        class="text-xs text-red-600 hover:text-red-800 font-medium transition">
                                        {{ __('common.cancel') }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canAll ? 7 : 6 }}" class="px-5 py-10 text-center text-sm text-slate-500">
                                {{ __('attendance.no_leave_requests') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

