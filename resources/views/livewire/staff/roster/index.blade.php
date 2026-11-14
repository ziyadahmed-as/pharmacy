<div class="space-y-6">
    {{-- Header & Controls --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                {{ __('roster.title') }}
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                @if ($canManage)
                    {{ __('roster.calendar_view') }} &mdash; {{ $periodTitle }}
                @else
                    {{ __('roster.own_shifts_notice') }} &mdash; {{ $periodTitle }}
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($canManage)
                <button
                    wire:click="openCreateModal"
                    type="button"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                >
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('roster.add_shift') }}
                </button>
            @endif

            <div class="inline-flex rounded-md shadow-sm" role="group">
                <button
                    wire:click="setViewMode('week')"
                    type="button"
                    class="px-3 py-2 text-sm font-medium rounded-l-lg border {{ $viewMode === 'week' ? 'bg-primary-600 text-white border-primary-600' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700' }}"
                >
                    {{ __('roster.week_view') }}
                </button>
                <button
                    wire:click="setViewMode('month')"
                    type="button"
                    class="px-3 py-2 text-sm font-medium rounded-r-lg border {{ $viewMode === 'month' ? 'bg-primary-600 text-white border-primary-600' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700' }}"
                >
                    {{ __('roster.month_view') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if (session()->has('success'))
        <div class="rounded-md bg-green-50 dark:bg-green-900/30 p-4 border border-green-200 dark:border-green-800">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800 dark:text-green-200">
                        {{ session('success') }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- Tabs & Navigation Bar --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-gray-200 dark:border-gray-700 pb-3">
        <div class="flex space-x-4">
            <button
                wire:click="$set('activeTab', 'calendar')"
                type="button"
                class="px-3 py-2 text-sm font-medium border-b-2 {{ $activeTab === 'calendar' ? 'border-primary-600 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' }}"
            >
                {{ __('roster.calendar_view') }}
            </button>
            <button
                wire:click="$set('activeTab', 'on_duty')"
                type="button"
                class="px-3 py-2 text-sm font-medium border-b-2 {{ $activeTab === 'on_duty' ? 'border-primary-600 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' }}"
            >
                {{ __('roster.on_duty_today') }}
                @if ($onDutyToday->isNotEmpty())
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-primary-100 text-primary-800 dark:bg-primary-900/50 dark:text-primary-300">
                        {{ $onDutyToday->count() }}
                    </span>
                @endif
            </button>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @if ($canManage && $activeTab === 'calendar')
                <div class="w-48">
                    <select
                        wire:model.live="selectedStaffId"
                        class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500"
                    >
                        <option value="">{{ __('roster.all_staff') }}</option>
                        @foreach ($activeStaff as $staffMember)
                            <option value="{{ $staffMember->id }}">{{ $staffMember->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if ($activeTab === 'calendar')
                <div class="inline-flex items-center space-x-1">
                    <button
                        wire:click="previous"
                        type="button"
                        class="p-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                        title="{{ __('roster.previous') }}"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <button
                        wire:click="today"
                        type="button"
                        class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        {{ __('roster.today') }}
                    </button>
                    <button
                        wire:click="next"
                        type="button"
                        class="p-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                        title="{{ __('roster.next') }}"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- TAB 1: CALENDAR VIEW --}}
    @if ($activeTab === 'calendar')
        @if ($viewMode === 'week')
            {{-- WEEK GRID: 7 Columns --}}
            <div class="grid grid-cols-1 md:grid-cols-7 gap-3">
                @foreach ($days as $day)
                    @php
                        $dayStr = $day->toDateString();
                        $isToday = $dayStr === $todayDateStr;
                        $dayEntries = $entriesByDate->get($dayStr, collect());
                    @endphp
                    <div class="flex flex-col rounded-lg border {{ $isToday ? 'border-primary-500 ring-2 ring-primary-500/20 dark:border-primary-400' : 'border-gray-200 dark:border-gray-700' }} bg-white dark:bg-gray-800 shadow-sm min-h-[300px]">
                        {{-- Day Header --}}
                        <div class="p-3 border-b {{ $isToday ? 'bg-primary-50 dark:bg-primary-900/20 border-primary-200 dark:border-primary-800' : 'border-gray-100 dark:border-gray-700/50' }} flex items-center justify-between">
                            <div>
                                <span class="text-xs font-semibold uppercase tracking-wider {{ $isToday ? 'text-primary-700 dark:text-primary-300' : 'text-gray-500 dark:text-gray-400' }}">
                                    {{ $day->isoFormat('ddd') }}
                                </span>
                                <div class="text-base font-bold {{ $isToday ? 'text-primary-900 dark:text-primary-100' : 'text-gray-900 dark:text-white' }}">
                                    {{ $day->isoFormat('MMM D') }}
                                </div>
                            </div>
                            @if ($canManage)
                                <button
                                    wire:click="openCreateModal('{{ $dayStr }}')"
                                    type="button"
                                    class="p-1 rounded text-gray-400 hover:text-primary-600 hover:bg-gray-100 dark:hover:bg-gray-700"
                                    title="{{ __('roster.add_shift') }}"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                </button>
                            @endif
                        </div>

                        {{-- Day Shifts List --}}
                        <div class="p-2 space-y-2 flex-1 overflow-y-auto">
                            @forelse ($dayEntries as $entry)
                                <div class="p-2.5 rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 transition text-xs space-y-1">
                                    <div class="flex items-center justify-between font-semibold text-gray-900 dark:text-white">
                                        <span class="truncate">{{ $entry->staff->name }}</span>
                                        @if ($canManage)
                                            <div class="flex items-center space-x-1 ml-1 flex-shrink-0">
                                                <button
                                                    wire:click="openEditModal({{ $entry->id }})"
                                                    type="button"
                                                    class="text-gray-400 hover:text-primary-600"
                                                    title="{{ __('roster.edit_shift') }}"
                                                >
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                    </svg>
                                                </button>
                                                <button
                                                    wire:click="delete({{ $entry->id }})"
                                                    wire:confirm="{{ __('roster.delete_confirm') }}"
                                                    type="button"
                                                    class="text-gray-400 hover:text-red-600"
                                                    title="{{ __('roster.delete_shift') }}"
                                                >
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="flex items-center text-primary-700 dark:text-primary-300 font-medium">
                                        <svg class="mr-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        {{ $entry->formatted_start_time }} &ndash; {{ $entry->formatted_end_time }}
                                    </div>

                                    @if ($entry->till)
                                        <div class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200">
                                            {{ __('roster.till') }}: {{ $entry->till->name }}
                                        </div>
                                    @endif

                                    @if ($entry->note)
                                        <p class="text-gray-500 dark:text-gray-400 italic truncate" title="{{ $entry->note }}">
                                            {{ $entry->note }}
                                        </p>
                                    @endif
                                </div>
                            @empty
                                <div class="h-full flex items-center justify-center text-gray-400 dark:text-gray-500 text-xs py-8">
                                    {{ __('roster.no_entries') }}
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            {{-- MONTH GRID --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
                @foreach ($days as $day)
                    @php
                        $dayStr = $day->toDateString();
                        $isToday = $dayStr === $todayDateStr;
                        $dayEntries = $entriesByDate->get($dayStr, collect());
                    @endphp
                    <div class="rounded-lg border {{ $isToday ? 'border-primary-500 ring-2 ring-primary-500/20 dark:border-primary-400' : 'border-gray-200 dark:border-gray-700' }} bg-white dark:bg-gray-800 p-2.5 min-h-[120px] flex flex-col justify-between">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-xs font-bold {{ $isToday ? 'text-primary-600 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300' }}">
                                {{ $day->isoFormat('D MMM (ddd)') }}
                            </span>
                            @if ($canManage)
                                <button
                                    wire:click="openCreateModal('{{ $dayStr }}')"
                                    type="button"
                                    class="text-gray-400 hover:text-primary-600 text-xs"
                                    title="{{ __('roster.add_shift') }}"
                                >
                                    +
                                </button>
                            @endif
                        </div>

                        <div class="space-y-1 overflow-y-auto max-h-36">
                            @foreach ($dayEntries as $entry)
                                <div
                                    @if ($canManage) wire:click="openEditModal({{ $entry->id }})" @endif
                                    class="p-1 rounded text-[11px] bg-primary-50 dark:bg-primary-900/30 text-primary-900 dark:text-primary-200 border border-primary-200 dark:border-primary-800 {{ $canManage ? 'cursor-pointer hover:bg-primary-100' : '' }}"
                                >
                                    <div class="font-medium truncate">{{ $entry->staff->name }}</div>
                                    <div class="text-[10px] text-primary-700 dark:text-primary-300">
                                        {{ $entry->formatted_start_time }}-{{ $entry->formatted_end_time }}
                                        @if ($entry->till) &bull; {{ $entry->till->name }} @endif
                                    </div>
                                </div>
                            @endforeach
                            @if ($dayEntries->isEmpty())
                                <span class="text-[10px] text-gray-400 italic">{{ __('roster.no_entries') }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif

    {{-- TAB 2: ON DUTY TODAY --}}
    @if ($activeTab === 'on_duty')
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                <svg class="mr-2 h-5 w-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                {{ __('roster.on_duty_today') }} &mdash; {{ today()->isoFormat('dddd, MMMM D, YYYY') }}
            </h2>

            @if ($onDutyToday->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($onDutyToday as $entry)
                        <div class="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/40 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between">
                                    <h3 class="font-bold text-gray-900 dark:text-white text-base">
                                        {{ $entry->staff->name }}
                                    </h3>
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300">
                                        {{ __('roster.status.active') }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ $entry->staff->title ?: __('roster.staff_member') }}
                                </p>

                                <div class="mt-3 space-y-1.5 text-xs text-gray-700 dark:text-gray-300">
                                    <div class="flex items-center">
                                        <svg class="mr-2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span class="font-semibold">{{ $entry->formatted_start_time }} &ndash; {{ $entry->formatted_end_time }}</span>
                                    </div>
                                    <div class="flex items-center">
                                        <svg class="mr-2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                        @if ($entry->till)
                                            <span class="font-medium text-blue-600 dark:text-blue-400">{{ __('roster.till') }}: {{ $entry->till->name }}</span>
                                        @else
                                            <span class="text-gray-400">{{ __('roster.no_till') }}</span>
                                        @endif
                                    </div>
                                    @if ($entry->note)
                                        <div class="pt-1 text-gray-500 italic">
                                            "{{ $entry->note }}"
                                        </div>
                                    @endif
                                </div>
                            </div>

                            @if ($canManage)
                                <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-600 flex justify-end space-x-2">
                                    <button
                                        wire:click="openEditModal({{ $entry->id }})"
                                        type="button"
                                        class="text-xs font-medium text-primary-600 hover:text-primary-800 dark:text-primary-400"
                                    >
                                        {{ __('roster.edit_shift') }}
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-12 text-center text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-sm font-medium">{{ __('roster.no_one_on_duty') }}</p>
                </div>
            @endif
        </div>
    @endif

    {{-- MODAL: ADD / EDIT SHIFT (Drag-Free Simple Assignment Form) --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-80 transition-opacity" wire:click="closeModal"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form wire:submit="save">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4 space-y-4">
                            <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 pb-3">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white" id="modal-title">
                                    {{ $editingId ? __('roster.edit_shift') : __('roster.add_shift') }}
                                </h3>
                                <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-500">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            {{-- Form Validation Summary / Warnings --}}
                            @error('staff_id')
                                <div class="p-3 rounded bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-xs">
                                    {{ $message }}
                                </div>
                            @enderror
                            @error('till_id')
                                <div class="p-3 rounded bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-xs font-semibold">
                                    {{ $message }}
                                </div>
                            @enderror
                            @error('end_time')
                                <div class="p-3 rounded bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-xs">
                                    {{ $message }}
                                </div>
                            @enderror

                            {{-- Staff Selection --}}
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ __('roster.staff_member') }} *
                                </label>
                                <select
                                    wire:model="staff_id"
                                    required
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500"
                                >
                                    <option value="">{{ __('roster.select_staff') }}</option>
                                    @foreach ($activeStaff as $staffMember)
                                        <option value="{{ $staffMember->id }}">{{ $staffMember->name }} ({{ $staffMember->title ?: __('roster.status.active') }})</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Date --}}
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ __('roster.date') }} *
                                </label>
                                <input
                                    type="date"
                                    wire:model="date"
                                    required
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500"
                                />
                            </div>

                            {{-- Start & End Time --}}
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        {{ __('roster.start_time') }} *
                                    </label>
                                    <input
                                        type="time"
                                        wire:model="start_time"
                                        required
                                        class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        {{ __('roster.end_time') }} *
                                    </label>
                                    <input
                                        type="time"
                                        wire:model="end_time"
                                        required
                                        class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500"
                                    />
                                </div>
                            </div>

                            {{-- Till Assignment --}}
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ __('roster.till') }}
                                </label>
                                <select
                                    wire:model="till_id"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500"
                                >
                                    <option value="">{{ __('roster.select_till') }}</option>
                                    @foreach ($activeTills as $till)
                                        <option value="{{ $till->id }}">{{ $till->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Note --}}
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ __('roster.note') }}
                                </label>
                                <input
                                    type="text"
                                    wire:model="note"
                                    placeholder="{{ __('roster.note_placeholder') }}"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500"
                                />
                            </div>

                            {{-- Overlap checkbox --}}
                            <div class="flex items-center">
                                <input
                                    type="checkbox"
                                    id="allow_staff_overlap"
                                    wire:model="allow_staff_overlap"
                                    class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                                />
                                <label for="allow_staff_overlap" class="ml-2 block text-xs text-gray-700 dark:text-gray-300">
                                    {{ __('roster.allow_overlap') }}
                                </label>
                            </div>

                            {{-- Repeat (only on create) --}}
                            @if (! $editingId)
                                <div class="pt-3 border-t border-gray-200 dark:border-gray-700 space-y-3">
                                    <div class="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="repeat_assignment"
                                            wire:model.live="repeat"
                                            class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                                        />
                                        <label for="repeat_assignment" class="ml-2 block text-xs font-medium text-gray-900 dark:text-white">
                                            {{ __('roster.repeat') }} ({{ __('roster.week_view') }} / {{ __('roster.month_view') }})
                                        </label>
                                    </div>

                                    @if ($repeat)
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                {{ __('roster.repeat_until') }} *
                                            </label>
                                            <input
                                                type="date"
                                                wire:model="repeat_until"
                                                class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500"
                                            />
                                        </div>

                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                {{ __('roster.repeat_days') }}
                                            </label>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach ([1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat', 7 => 'sun'] as $dayNum => $dayKey)
                                                    <label class="inline-flex items-center text-xs">
                                                        <input
                                                            type="checkbox"
                                                            value="{{ $dayNum }}"
                                                            wire:model="repeat_days"
                                                            class="h-3.5 w-3.5 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                                                        />
                                                        <span class="ml-1 text-gray-700 dark:text-gray-300">{{ __('roster.days.'.$dayKey) }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                            <button
                                type="submit"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:w-auto sm:text-sm"
                            >
                                {{ $editingId ? __('roster.edit_shift') : __('roster.add_shift') }}
                            </button>
                            <button
                                type="button"
                                wire:click="closeModal"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm"
                            >
                                {{ __('common.cancel') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

