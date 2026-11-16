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

        {{-- Export, Print & Schedule Actions --}}
        <div class="flex flex-wrap items-center gap-2">
            {{-- Timing indicator (NFR-1) --}}
            <span class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-mono text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                <svg class="h-3.5 w-3.5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ $renderDurationMs }} ms
            </span>

            {{-- Quick Export dropdown --}}
            <a
                href="{{ route('reports.export', array_merge(['type' => $type, 'format' => 'csv'], array_filter([
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'group_by' => $groupBy,
                    'search' => $search,
                    'category_id' => $categoryId,
                    'user_id' => $userId,
                    'till_id' => $tillId,
                    'product_id' => $productId,
                    'status' => $status,
                ]))) }}"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            >
                <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                CSV
            </a>

            <a
                href="{{ route('reports.export', array_merge(['type' => $type, 'format' => 'excel'], array_filter([
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'group_by' => $groupBy,
                    'search' => $search,
                    'category_id' => $categoryId,
                    'user_id' => $userId,
                    'till_id' => $tillId,
                    'product_id' => $productId,
                    'status' => $status,
                ]))) }}"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            >
                <svg class="h-4 w-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Excel
            </a>

            <a
                href="{{ route('reports.export', array_merge(['type' => $type, 'format' => 'pdf'], array_filter([
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'group_by' => $groupBy,
                    'search' => $search,
                    'category_id' => $categoryId,
                    'user_id' => $userId,
                    'till_id' => $tillId,
                    'product_id' => $productId,
                    'status' => $status,
                ]))) }}"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            >
                <svg class="h-4 w-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                PDF
            </a>

            <a
                href="{{ route('reports.print', array_merge(['type' => $type], array_filter([
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'group_by' => $groupBy,
                    'search' => $search,
                    'category_id' => $categoryId,
                    'user_id' => $userId,
                    'till_id' => $tillId,
                    'product_id' => $productId,
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

            {{-- Queue Export button for large runs --}}
            <button
                type="button"
                wire:click="queueExport('csv')"
                class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 dark:border-indigo-900 dark:bg-indigo-950/60 dark:text-indigo-300"
                title="Process export in the background and receive notification"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                Queue Export
            </button>

            {{-- Owner Schedule Modal trigger (STEP 5) --}}
            @if ($isOwner)
                <button
                    type="button"
                    wire:click="$toggle('showScheduleModal')"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-purple-200 bg-purple-50 px-3 py-2 text-xs font-semibold text-purple-700 hover:bg-purple-100 dark:border-purple-900 dark:bg-purple-950/60 dark:text-purple-300"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    Schedule Send
                </button>
            @endif
        </div>
    </div>

    {{-- Feedback alert message --}}
    @if ($feedbackMessage)
        <div class="rounded-xl border border-teal-200 bg-teal-50 p-3 text-xs font-medium text-teal-800 dark:border-teal-800 dark:bg-teal-950/60 dark:text-teal-300 flex items-center justify-between">
            <span>{{ $feedbackMessage }}</span>
            <button type="button" wire:click="$set('feedbackMessage', null)" class="text-teal-600 hover:text-teal-800">&times;</button>
        </div>
    @endif

    {{-- Common Filter Panel (STEP 3) --}}
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 space-y-4">
        {{-- Quick Date Range Shortcuts --}}
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 pb-3 dark:border-slate-800">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 mr-1">
                {{ __('reports.presets') }}:
            </span>
            <button
                type="button"
                wire:click="setToday"
                class="rounded-lg px-2.5 py-1 text-xs font-semibold transition {{ $mode === 'day' && $startDate === business_date() ? 'bg-teal-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300' }}"
            >
                {{ __('reports.preset_today') }}
            </button>
            <button
                type="button"
                wire:click="setYesterday"
                class="rounded-lg px-2.5 py-1 text-xs font-semibold transition {{ $mode === 'day' && $startDate !== business_date() ? 'bg-teal-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300' }}"
            >
                {{ __('reports.preset_yesterday') }}
            </button>
            <button
                type="button"
                wire:click="setThisWeek"
                class="rounded-lg px-2.5 py-1 text-xs font-semibold transition {{ $mode === 'week' ? 'bg-teal-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300' }}"
            >
                {{ __('reports.preset_this_week') }}
            </button>
            <button
                type="button"
                wire:click="setThisMonth"
                class="rounded-lg px-2.5 py-1 text-xs font-semibold transition {{ $mode === 'month' ? 'bg-teal-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300' }}"
            >
                {{ __('reports.preset_this_month') }}
            </button>
            <button
                type="button"
                wire:click="setThisYear"
                class="rounded-lg px-2.5 py-1 text-xs font-semibold transition {{ $mode === 'year' ? 'bg-teal-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300' }}"
            >
                {{ __('reports.preset_this_year') }}
            </button>

            {{-- Saved Presets Dropdown --}}
            @if ($savedFilters->isNotEmpty())
                <div class="ml-auto flex items-center gap-1.5 text-xs">
                    <span class="text-slate-400">Saved:</span>
                    <select
                        wire:change="loadSavedFilter($event.target.value)"
                        class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-800"
                    >
                        <option value="">Load Preset...</option>
                        @foreach ($savedFilters as $sf)
                            <option value="{{ $sf->name }}">{{ $sf->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        {{-- Dynamic filter inputs --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
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

            {{-- Till selector --}}
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                    Till
                </label>
                <select
                    wire:model.live="tillId"
                    class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-900 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                >
                    <option value="">All Tills</option>
                    @foreach ($tills as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Staff selector --}}
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                    {{ __('reports.staff_member') }}
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

            {{-- Category selector --}}
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                    {{ __('reports.category') }}
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

            {{-- Product selector --}}
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                    Product
                </label>
                <select
                    wire:model.live="productId"
                    class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-900 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                >
                    <option value="">All Products</option>
                    @foreach ($products as $pr)
                        <option value="{{ $pr->id }}">{{ $pr->brand }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Keyword Search --}}
            <div class="sm:col-span-2 lg:col-span-4">
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

            {{-- Save filter input --}}
            <div class="sm:col-span-2 lg:col-span-2 flex items-end gap-1.5">
                <input
                    type="text"
                    wire:model="filterPresetName"
                    placeholder="Preset name..."
                    class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-900 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                />
                <button
                    type="button"
                    wire:click="saveCurrentFilter"
                    class="rounded-lg bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-500 whitespace-nowrap"
                >
                    Save Filter
                </button>
            </div>
        </div>
    </div>

    {{-- Summary Cards if available --}}
    @if (! empty($summary))
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ($summary as $label => $val)
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

    {{-- Table controls & Column Chooser --}}
    <div class="flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex items-center gap-3">
            <span class="font-semibold text-slate-700 dark:text-slate-300">
                {{ __('reports.results') }} ({{ $allRowsCount }})
            </span>
            <div class="flex items-center gap-1.5">
                <span class="text-slate-400">Rows:</span>
                <select wire:model.live="pageSize" class="rounded border border-slate-300 bg-white px-2 py-0.5 text-xs dark:border-slate-700 dark:bg-slate-800">
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>

        {{-- Column visibility dropdown --}}
        <div x-data="{ open: false }" class="relative">
            <button
                @click="open = !open"
                type="button"
                class="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
            >
                Columns ▼
            </button>
            <div
                x-show="open"
                @click.away="open = false"
                class="absolute right-0 z-20 mt-1 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:border-slate-700 dark:bg-slate-800"
                style="display: none;"
            >
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-2 py-1">Toggle Columns</div>
                @foreach ($headers as $idx => $h)
                    <label class="flex items-center gap-2 px-2 py-1 text-xs hover:bg-slate-50 dark:hover:bg-slate-700 rounded cursor-pointer">
                        <input
                            type="checkbox"
                            wire:click="toggleColumn({{ $idx }})"
                            {{ ($visibleColumns[$idx] ?? true) ? 'checked' : '' }}
                            class="rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                        />
                        <span class="truncate">{{ $h }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Results Table --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-700 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-300">
                    <tr>
                        @foreach ($headers as $idx => $header)
                            @if ($visibleColumns[$idx] ?? true)
                                <th scope="col" class="px-4 py-2.5 whitespace-nowrap cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700 transition" wire:click="sortBy({{ $idx }})">
                                    <div class="flex items-center gap-1">
                                        <span>{{ $header }}</span>
                                        @if ($sortCol === $idx)
                                            <span class="text-teal-600">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>
                                        @endif
                                    </div>
                                </th>
                            @endif
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition">
                            @foreach ($row as $idx => $cell)
                                @if ($visibleColumns[$idx] ?? true)
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        {{ $cell }}
                                    </td>
                                @endif
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($headers) }}" class="py-12 text-center text-slate-400">
                                {{ __('reports.no_data_for_period') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination footer --}}
        @if ($totalPages > 1)
            <div class="flex items-center justify-between border-t border-slate-200 px-4 py-3 dark:border-slate-800 text-xs">
                <span class="text-slate-500">
                    Page {{ $page }} of {{ $totalPages }}
                </span>
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        wire:click="$set('page', {{ max(1, $page - 1) }})"
                        {{ $page <= 1 ? 'disabled' : '' }}
                        class="rounded px-2.5 py-1 border border-slate-300 dark:border-slate-700 disabled:opacity-50"
                    >
                        Previous
                    </button>
                    <button
                        type="button"
                        wire:click="$set('page', {{ min($totalPages, $page + 1) }})"
                        {{ $page >= $totalPages ? 'disabled' : '' }}
                        class="rounded px-2.5 py-1 border border-slate-300 dark:border-slate-700 disabled:opacity-50"
                    >
                        Next
                    </button>
                </div>
            </div>
        @endif
    </div>

    {{-- Schedule Modal (Owner only) --}}
    @if ($showScheduleModal && $isOwner)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-700">
                    <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Schedule Email Report</h3>
                    <button type="button" wire:click="$set('showScheduleModal', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Frequency</label>
                        <select wire:model="scheduleFrequency" class="w-full rounded-lg border border-slate-300 bg-white p-2 dark:border-slate-700 dark:bg-slate-900">
                            <option value="daily">Daily (Every morning at 08:00 AM)</option>
                            <option value="weekly">Weekly (Monday mornings)</option>
                            <option value="monthly">Monthly (1st day of month)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Format</label>
                        <select wire:model="scheduleFormat" class="w-full rounded-lg border border-slate-300 bg-white p-2 dark:border-slate-700 dark:bg-slate-900">
                            <option value="pdf">PDF Attachment</option>
                            <option value="csv">CSV Spreadsheet</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Recipient Emails (comma-separated)</label>
                        <input
                            type="text"
                            wire:model="scheduleRecipients"
                            placeholder="owner@example.com, manager@example.com"
                            class="w-full rounded-lg border border-slate-300 bg-white p-2 dark:border-slate-700 dark:bg-slate-900"
                        />
                        <span class="text-[10px] text-slate-400">Leave blank to default to your own account email.</span>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        wire:click="$set('showScheduleModal', false)"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        wire:click="createSchedule"
                        class="rounded-lg bg-teal-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-teal-500"
                    >
                        Save Schedule
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
