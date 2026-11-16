<div x-data="{ open: @entangle('isOpen') }"
     @keydown.window.prevent.cmd.k="open = true; $wire.open(); $nextTick(() => $refs.searchInput.focus())"
     @keydown.window.prevent.ctrl.k="open = true; $wire.open(); $nextTick(() => $refs.searchInput.focus())"
     @keydown.window.slash="if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return; $event.preventDefault(); open = true; $wire.open(); $nextTick(() => $refs.searchInput.focus())"
     @keydown.escape.window="open = false; $wire.close()">

    {{-- Trigger button in top header --}}
    <button type="button"
            @click="open = true; $wire.open(); $nextTick(() => $refs.searchInput.focus())"
            class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/80 hover:bg-slate-100 px-3 py-1.5 text-xs text-slate-500 hover:text-slate-700 transition shadow-inner">
        <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <span class="hidden sm:inline">{{ __('search.placeholder') }}</span>
        <span class="sm:hidden">{{ __('common.search') }}</span>
        <kbd class="hidden md:inline-flex items-center gap-0.5 rounded border border-slate-300 bg-white px-1.5 py-0.5 font-mono text-[10px] text-slate-400 shadow-sm">
            <span>Ctrl</span><span>K</span>
        </kbd>
    </button>

    {{-- Search Modal Dialog --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto p-4 sm:p-6 md:p-20" role="dialog" aria-modal="true">
        {{-- Backdrop --}}
        <div x-show="open" x-transition.opacity class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="open = false; $wire.close()"></div>

        {{-- Dialog Panel --}}
        <div x-show="open" x-transition
             class="relative mx-auto max-w-2xl transform rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/5 transition-all overflow-hidden">

            {{-- Input Box --}}
            <div class="relative border-b border-slate-100 flex items-center px-4">
                <svg class="h-5 w-5 text-teal-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input x-ref="searchInput"
                       type="text"
                       wire:model.live.debounce.250ms="query"
                       placeholder="{{ __('search.modal_placeholder') }}"
                       class="h-14 w-full border-0 bg-transparent pl-3 pr-10 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0">
                <button type="button" @click="open = false; $wire.close()" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                    <kbd class="rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[10px] text-slate-500 font-mono">ESC</kbd>
                </button>
            </div>

            {{-- Results Container --}}
            <div class="max-h-96 overflow-y-auto divide-y divide-slate-100 p-2 text-sm">
                @if (strlen(trim($query)) >= 2)
                    @php $hasResults = !empty($results); @endphp

                    @if ($hasResults)
                        @foreach ($results as $category => $items)
                            <div class="py-2">
                                <div class="px-3 pb-1 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                    {{ match($category) {
                                        'pages' => __('search.category_pages'),
                                        'products' => __('search.category_products'),
                                        'customers' => __('search.category_customers'),
                                        'prescriptions' => __('search.category_prescriptions'),
                                        'receipts' => __('search.category_receipts'),
                                        'staff' => __('search.category_staff'),
                                        'reports' => __('search.category_reports'),
                                        default => $category,
                                    } }}
                                </div>
                                <div class="space-y-0.5">
                                    @foreach ($items as $item)
                                        <a href="{{ $item['url'] }}"
                                           @click="open = false; $wire.close()"
                                           class="flex items-center justify-between rounded-xl px-3 py-2 text-slate-700 hover:bg-teal-50 hover:text-teal-900 transition group">
                                            <div class="min-w-0 flex-1">
                                                <div class="font-semibold text-slate-900 group-hover:text-teal-900 truncate">
                                                    {{ $item['title'] }}
                                                </div>
                                                @if (!empty($item['subtitle']))
                                                    <div class="text-xs text-slate-400 group-hover:text-teal-700 truncate">
                                                        {{ $item['subtitle'] }}
                                                    </div>
                                                @endif
                                            </div>
                                            <span class="ml-3 shrink-0 rounded-md bg-slate-100 group-hover:bg-teal-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 group-hover:text-teal-800 transition">
                                                {{ $item['badge'] }}
                                            </span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="p-8 text-center text-slate-500">
                            <p class="font-medium text-slate-700">{{ __('search.no_results_title') }}</p>
                            <p class="text-xs text-slate-400 mt-1">{{ __('search.no_results_subtitle') }}</p>
                        </div>
                    @endif
                @else
                    <div class="p-6 text-center text-xs text-slate-400 space-y-2">
                        <p>{{ __('search.type_to_search_prompt') }}</p>
                        <div class="flex flex-wrap justify-center gap-2 pt-2">
                            <span class="rounded bg-slate-100 px-2 py-1 text-[11px] text-slate-600">{{ __('search.hint_products') }}</span>
                            <span class="rounded bg-slate-100 px-2 py-1 text-[11px] text-slate-600">{{ __('search.hint_customers') }}</span>
                            <span class="rounded bg-slate-100 px-2 py-1 text-[11px] text-slate-600">{{ __('search.hint_prescriptions') }}</span>
                            <span class="rounded bg-slate-100 px-2 py-1 text-[11px] text-slate-600">{{ __('search.hint_receipts') }}</span>
                            <span class="rounded bg-slate-100 px-2 py-1 text-[11px] text-slate-600">{{ __('search.hint_reports') }}</span>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Footer help --}}
            <div class="border-t border-slate-100 bg-slate-50/50 px-4 py-2.5 flex items-center justify-between text-[11px] text-slate-400">
                <div class="flex items-center gap-3">
                    <span><kbd class="font-mono bg-white border border-slate-200 rounded px-1 text-[10px]">↑</kbd> <kbd class="font-mono bg-white border border-slate-200 rounded px-1 text-[10px]">↓</kbd> {{ __('search.to_navigate') }}</span>
                    <span><kbd class="font-mono bg-white border border-slate-200 rounded px-1 text-[10px]">↵</kbd> {{ __('search.to_select') }}</span>
                </div>
                <span>{{ __('search.permission_filtered_notice') }}</span>
            </div>
        </div>
    </div>
</div>
