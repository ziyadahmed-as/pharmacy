<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('attendance.title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('attendance.my_attendance') }}</p>
        </div>
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

    {{-- Today status card (for own staff only) --}}
    @if ($ownStaff)
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 mb-4">{{ __('attendance.today_status') }}</h2>
            <div class="flex flex-wrap items-center gap-4">
                @if ($todayRecord && $todayRecord->clock_in)
                    <div class="text-sm text-slate-700">
                        <span class="font-semibold">{{ __('attendance.clock_in') }}:</span>
                        {{ $todayRecord->clock_in->format('H:i') }}
                        @if ($todayRecord->lateness_minutes > 0)
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                                {{ __('attendance.minutes_late', ['minutes' => $todayRecord->lateness_minutes]) }}
                            </span>
                        @else
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-teal-100 text-teal-800">
                                {{ __('attendance.on_time') }}
                            </span>
                        @endif
                    </div>

                    @if ($todayRecord->clock_out)
                        <div class="text-sm text-slate-700">
                            <span class="font-semibold">{{ __('attendance.clock_out') }}:</span>
                            {{ $todayRecord->clock_out->format('H:i') }}
                        </div>
                        <div class="text-sm text-slate-700">
                            <span class="font-semibold">{{ __('attendance.hours_worked') }}:</span>
                            {{ number_format($todayRecord->hours_worked, 1) }}h
                        </div>
                    @else
                        <button wire:click="clockOut" wire:confirm="Clock out now?"
                            class="inline-flex items-center gap-2 rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-700 transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1"/>
                            </svg>
                            {{ __('attendance.clock_out') }}
                        </button>
                    @endif
                @else
                    <p class="text-sm text-slate-500">{{ __('attendance.not_clocked_in_yet') }}</p>
                    <button wire:click="clockIn" wire:confirm="Clock in now?"
                        class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                        </svg>
                        {{ __('attendance.clock_in') }}
                    </button>
                @endif
            </div>
        </div>
    @endif

    {{-- Filters --}}
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">
                {{ __('attendance.filter_by_month') }}
            </label>
            <input type="month" wire:model.live="filterMonth"
                class="rounded-lg border border-slate-300 py-1.5 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
        </div>
        @if ($canViewAll && $allStaff->isNotEmpty())
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">
                    {{ __('attendance.filter_by_staff') }}
                </label>
                <select wire:model.live="filterStaffId"
                    class="rounded-lg border border-slate-300 py-1.5 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                    <option value="">{{ __('attendance.all_staff') }}</option>
                    @foreach ($allStaff as $member)
                        <option value="{{ $member->id }}">{{ $member->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    {{-- Table --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50/75 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3.5 text-left">{{ __('attendance.col_staff') }}</th>
                        <th class="px-5 py-3.5 text-left">{{ __('attendance.col_date') }}</th>
                        <th class="px-5 py-3.5 text-left">{{ __('attendance.col_clock_in') }}</th>
                        <th class="px-5 py-3.5 text-left">{{ __('attendance.col_clock_out') }}</th>
                        <th class="px-5 py-3.5 text-left">{{ __('attendance.col_hours') }}</th>
                        <th class="px-5 py-3.5 text-left">{{ __('attendance.col_lateness') }}</th>
                        <th class="px-5 py-3.5 text-left">{{ __('attendance.col_status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($records as $record)
                        <tr wire:key="att-{{ $record->id }}" class="hover:bg-slate-50/70 transition">
                            <td class="px-5 py-3.5 font-medium text-slate-900">{{ $record->staff->name }}</td>
                            <td class="px-5 py-3.5 text-slate-700">{{ $record->date->toFormattedDateString() }}</td>
                            <td class="px-5 py-3.5 text-slate-700">{{ $record->clock_in ? $record->clock_in->format('H:i') : '—' }}</td>
                            <td class="px-5 py-3.5 text-slate-700">{{ $record->clock_out ? $record->clock_out->format('H:i') : '—' }}</td>
                            <td class="px-5 py-3.5 text-slate-700">
                                {{ $record->hours_worked !== null ? number_format($record->hours_worked, 1).'h' : '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-slate-700">
                                @if ($record->lateness_minutes > 0)
                                    <span class="text-amber-700">+{{ $record->lateness_minutes }}min</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                @if ($record->is_absent)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                        {{ $record->absence_reason === 'on_leave' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $record->absence_reason === 'on_leave' ? __('attendance.status_on_leave') : __('attendance.status_absent') }}
                                    </span>
                                @elseif ($record->clock_in)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                        {{ $record->lateness_minutes > 0 ? 'bg-amber-100 text-amber-800' : 'bg-teal-100 text-teal-800' }}">
                                        {{ $record->lateness_minutes > 0 ? __('attendance.status_late') : __('attendance.status_present') }}
                                    </span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-sm text-slate-500">
                                {{ __('attendance.no_attendance') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

