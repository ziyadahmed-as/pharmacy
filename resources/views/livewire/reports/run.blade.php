<div class="space-y-6">
    {{-- Top navigation & action header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('reports.index') }}" class="inline-flex items-center text-xs text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                    <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ __('reports.back_to_reports') }}
                </a>
                <span class="text-slate-300 dark:text-slate-700">•</span>
                <span class="rounded bg-teal-50 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wider text-teal-700 dark:bg-teal-950/60 dark:text-teal-300">
                    {{ $definition->category() }}
                </span>
            </div>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                {{ $definition->name() }}
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ $definition->description() }}
            </p>
        </div>

        {{-- Export & Print Actions --}}
        <div class="flex flex-wrap items-center gap-2">
            {{-- Timing indicator (NFR-1) --}}
            <span class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-mono text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                <svg class="h-3.5 w-3.5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ $renderDurationMs }} ms
            </span>

            <a
                href="{{ route('reports.export', array_merge(['type' => $type, 'format' => 'csv'], array_filter([
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'group_by' => $groupBy,
                    'search' => $search,
                    'category_id' => $categoryId,
                    'user_id' => $userId,
                    'status' => $status,
                ]))) }}"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            >
                <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                {{ __('reports.export_excel') }}
            </a>

            <a
                href="{{ route('reports.export', array_merge(['type' => $type, 'format' => 'pdf'], array_filter([
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'group_by' => $groupBy,
                    'search' => $search,
                    'category_id' => $categoryId,
                    'user_id' => $userId,
                    'status' => $status,
                ]))) }}"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            >
                <svg class="h-4 w-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                {{ __('reports.export_pdf') }}
            </a>

            <a
                href="{{ route('reports.print', array_merge(['type' => $type], array_filter([
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'group_by' => $groupBy,
                    'search' => $search,
                    'category_id' => $categoryId,
                    'user_id' => $userId,
                    'status' => $status,
                ]))) }}"
                target="_blank"
                class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                {{ __('reports.print') }}
            </a>
        </div>
    </div>

    {{-- Filter Panel --}}
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 space-y-4">
        {{-- Quick Date Range Shortcuts --}}
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 pb-3 dark:border-slate-800">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 mr-1">
                {{ __('reports.presets') }}:
            </span>
            <button
                type="button"
                wire:click="setToday"
                class="rounded-lg px-2.5 py-1 text-xs font-medium transition {{ $mode === 'day' && $startDate === now()->toDateString() ? 'bg-teal-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300' }}"
            >
                {{ __('reports.today') }}
            </button>
            <button
                type="button"
                wire:click="setYesterday"
                class="rounded-lg px-2.5 py-1 text-xs font-medium transition {{ $mode === 'day' && $startDate === now()->subDay()->toDateString() ? 'bg-teal-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300' }}"
            >
                {{ __('reports.yesterday') }}
            </button>
            <button
                type="button"
                wire:click="setThisWeek"
                class="rounded-lg px-2.5 py-1 text-xs font-medium transition {{ $mode === 'week' ? 'bg-teal-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300' }}"
            >
                {{ __('reports.this_week') }}
            </button>
            <button
                type="button"
                wire:click="setThisMonth"
                class="rounded-lg px-2.5 py-1 text-xs font-medium transition {{ $mode === 'month' ? 'bg-teal-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300' }}"
            >
                {{ __('reports.this_month') }}
            </button>
            <button
                type="button"
                wire:click="setThisYear"
                class="rounded-lg px-2.5 py-1 text-xs font-medium transition {{ $mode === 'year' ? 'bg-teal-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300' }}"
            >
                {{ __('reports.this_year') }}
            </button>
        </div>

        {{-- Filter Inputs Form --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6 items-end">
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                    {{ __('reports.from_date') }}
                </label>
                <input
                    type="date"
                    wire:model.live="startDate"
                    class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-900 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                />
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                    {{ __('reports.to_date') }}
                </label>
                <input
                    type="date"
                    wire:model.live="endDate"
                    class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-900 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                />
            </div>

            @if (isset($definition->availableFilters()['group_by']))
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('reports.group_by') }}
                    </label>
                    <select
                        wire:model.live="groupBy"
                        class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-900 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                    >
                        <option value="day">{{ __('reports.group_day') }}</option>
                        <option value="week">{{ __('reports.group_week') }}</option>
                        <option value="month">{{ __('reports.group_month') }}</option>
                        <option value="year">{{ __('reports.group_year') }}</option>
                    </select>
                </div>
            @endif

            @if (isset($definition->availableFilters()['category_id']))
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('reports.col_category') }}
                    </label>
                    <select
                        wire:model.live="categoryId"
                        class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-900 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                    >
                        <option value="">{{ __('reports.all_categories') }}</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if (isset($definition->availableFilters()['user_id']) && $canViewCost)
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('reports.col_staff_member') }}
                    </label>
                    <select
                        wire:model.live="userId"
                        class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-900 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                    >
                        <option value="">{{ __('reports.all_staff') }}</option>
                        @foreach ($staffUsers as $sUser)
                            <option value="{{ $sUser->id }}">{{ $sUser->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if (isset($definition->availableFilters()['search']))
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('reports.search') }}
                    </label>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ __('reports.search_keyword') }}"
                        class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-900 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                    />
                </div>
            @endif
        </div>
    </div>

    {{-- Summary Cards if available --}}
    @if (! empty($result['summary']))
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ($result['summary'] as $label => $val)
                <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400 capitalize">
                        {{ str_replace('_', ' ', $label) }}
                    </p>
                    <p class="mt-1 text-base font-bold text-slate-900 dark:text-slate-100">
                        {{ $val }}
                    </p>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Results Table --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800">
            <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                {{ __('reports.results') }} ({{ $result['row_count'] }})
            </span>
            <div wire:loading class="text-xs font-medium text-teal-600 dark:text-teal-400">
                {{ __('reports.loading') }}...
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-700 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-300">
                    <tr>
                        @foreach ($result['headers'] as $header)
                            <th scope="col" class="px-4 py-2.5 whitespace-nowrap">
                                {{ $header }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    @forelse ($result['rows'] as $row)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition">
                            @foreach ($row as $cell)
                                <td class="px-4 py-2 whitespace-nowrap">
                                    {{ $cell }}
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($result['headers']) }}" class="py-12 text-center text-slate-400">
                                {{ __('reports.no_data_for_period') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
