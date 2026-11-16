<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                {{ __('reports.title') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('reports.subtitle') }}
            </p>
        </div>
        @if ($isStaff)
            <div class="inline-flex items-center gap-2 rounded-lg border border-teal-200 bg-teal-50 px-3 py-1.5 text-xs font-medium text-teal-800 dark:border-teal-800 dark:bg-teal-950/40 dark:text-teal-300">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ __('reports.staff_scope_notice') }}
            </div>
        @endif
    </div>

    {{-- Filter bar --}}
    <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-900">
        {{-- Category pills --}}
        <div class="flex flex-wrap items-center gap-1.5">
            <button
                type="button"
                wire:click="$set('selectedCategory', 'all')"
                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $selectedCategory === 'all' ? 'bg-teal-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700' }}"
            >
                {{ __('reports.cat_all') }}
            </button>
            <button
                type="button"
                wire:click="$set('selectedCategory', 'sales')"
                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $selectedCategory === 'sales' ? 'bg-teal-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700' }}"
            >
                {{ __('reports.category_sales') }}
            </button>
            <button
                type="button"
                wire:click="$set('selectedCategory', 'inventory')"
                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $selectedCategory === 'inventory' ? 'bg-teal-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700' }}"
            >
                {{ __('reports.category_inventory') }}
            </button>
            <button
                type="button"
                wire:click="$set('selectedCategory', 'finance')"
                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $selectedCategory === 'finance' ? 'bg-teal-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700' }}"
            >
                {{ __('reports.category_finance', [], 'en') ?: 'Finance' }}
            </button>
            <button
                type="button"
                wire:click="$set('selectedCategory', 'compliance')"
                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $selectedCategory === 'compliance' ? 'bg-teal-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700' }}"
            >
                {{ __('reports.category_compliance') }}
            </button>
            <button
                type="button"
                wire:click="$set('selectedCategory', 'audit')"
                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $selectedCategory === 'audit' ? 'bg-teal-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700' }}"
            >
                {{ __('reports.category_audit') }}
            </button>
            <button
                type="button"
                wire:click="$set('selectedCategory', 'staff')"
                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $selectedCategory === 'staff' ? 'bg-teal-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700' }}"
            >
                {{ __('reports.category_staff') }}
            </button>
        </div>

        {{-- Search input --}}
        <div class="relative w-full sm:w-64">
            <input
                type="text"
                wire:model.live.debounce.250ms="search"
                placeholder="{{ __('reports.search_placeholder') }}"
                class="w-full rounded-lg border border-slate-300 bg-white py-1.5 pl-9 pr-3 text-xs text-slate-900 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500"
            />
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5">
                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
        </div>
    </div>

    {{-- Favourites Section if any exist --}}
    @if (! empty($favouriteReports) && $selectedCategory === 'all' && empty($search))
        <div class="space-y-3">
            <div class="flex items-center gap-2 border-b border-amber-200 pb-2 dark:border-amber-900">
                <span class="text-amber-500">★</span>
                <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">
                    {{ __('reports.starred_reports', [], 'en') ?: 'Favourites' }}
                </h2>
                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                    {{ count($favouriteReports) }}
                </span>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($favouriteReports as $favRep)
                    <div class="flex flex-col justify-between rounded-xl border border-amber-200 bg-white p-5 shadow-sm transition hover:border-amber-400 hover:shadow-md dark:border-amber-900/40 dark:bg-slate-900">
                        <div class="space-y-2">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">
                                    {{ $favRep->name() }}
                                </h3>
                                <button
                                    type="button"
                                    wire:click="toggleFavourite('{{ $favRep->key() }}')"
                                    class="text-amber-500 hover:text-amber-600 focus:outline-none"
                                    title="Remove from favourites"
                                >
                                    ★
                                </button>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $favRep->description() }}
                            </p>
                        </div>
                        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
                            <span class="text-[10px] uppercase font-semibold text-slate-400">
                                {{ $favRep->category() }}
                            </span>
                            <a
                                href="{{ route('reports.run', ['type' => $favRep->key()]) }}"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-teal-500"
                            >
                                <span>{{ __('reports.run_report') }}</span>
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Recently Run Section if any exist --}}
    @if (! empty($recentReports) && $selectedCategory === 'all' && empty($search))
        <div class="space-y-3">
            <div class="flex items-center gap-2 border-b border-slate-200 pb-2 dark:border-slate-800">
                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">
                    {{ __('reports.recently_run', [], 'en') ?: 'Recently Run' }}
                </h2>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                @foreach ($recentReports as $recRep)
                    <a
                        href="{{ route('reports.run', ['type' => $recRep->key()]) }}"
                        class="rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm transition hover:border-teal-500 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 flex flex-col justify-between"
                    >
                        <div>
                            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wider text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                {{ $recRep->category() }}
                            </span>
                            <h4 class="mt-1.5 text-xs font-bold text-slate-900 dark:text-slate-100 line-clamp-1">
                                {{ $recRep->name() }}
                            </h4>
                        </div>
                        <span class="mt-2 text-[11px] font-medium text-teal-600 dark:text-teal-400">
                            Run again &rarr;
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Report Categories & Cards --}}
    @forelse ($categories as $catKey => $category)
        <div class="space-y-3">
            <div class="flex items-center gap-2 border-b border-slate-200 pb-2 dark:border-slate-800">
                <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">
                    {{ $category['title'] }}
                </h2>
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    {{ count($category['reports']) }}
                </span>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($category['reports'] as $report)
                    @php
                        $isFav = in_array($report->key(), $favourites);
                        $audience = 'Owner, PM';
                        if ($report->canView(auth()->user()) && $isStaff) {
                            $audience = 'Owner, PM, Staff';
                        }
                    @endphp
                    <div class="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-teal-500 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-teal-600">
                        <div class="space-y-2">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">
                                    {{ $report->name() }}
                                </h3>
                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        wire:click="toggleFavourite('{{ $report->key() }}')"
                                        class="text-base {{ $isFav ? 'text-amber-500 hover:text-amber-600' : 'text-slate-300 hover:text-amber-400 dark:text-slate-600' }} focus:outline-none"
                                        title="{{ $isFav ? 'Remove from favourites' : 'Add to favourites' }}"
                                    >
                                        ★
                                    </button>
                                    <span class="rounded bg-teal-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-teal-700 dark:bg-teal-950/60 dark:text-teal-300">
                                        {{ $report->category() }}
                                    </span>
                                </div>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2">
                                {{ $report->description() }}
                            </p>
                        </div>

                        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
                            <span class="text-[10px] text-slate-400 font-medium">
                                Audience: {{ $audience }}
                            </span>
                            <a
                                href="{{ route('reports.run', ['type' => $report->key()]) }}"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-teal-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-teal-600"
                            >
                                <span>{{ __('reports.run_report') }}</span>
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-dashed border-slate-300 p-12 text-center dark:border-slate-700">
            <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('reports.no_reports_found') }}</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('reports.no_reports_desc') }}</p>
        </div>
    @endforelse
</div>
