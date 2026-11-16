@props([
    'items' => [],
    'back' => null,
    'backLabel' => null,
])

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
    {{-- Breadcrumbs list --}}
    @if (!empty($items))
        <nav class="flex items-center space-x-1.5 text-xs text-slate-500 overflow-x-auto" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}" class="hover:text-teal-700 transition flex items-center gap-1 font-medium">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span>{{ __('nav.dashboard') }}</span>
            </a>

            @foreach ($items as $index => $item)
                <span class="text-slate-300">/</span>
                @if (!empty($item['url']) && !empty($item['label']) && $index < count($items) - 1)
                    <a href="{{ $item['url'] }}" class="hover:text-teal-700 transition font-medium whitespace-nowrap">
                        {{ $item['label'] }}
                    </a>
                @else
                    <span class="text-slate-800 font-semibold truncate max-w-xs whitespace-nowrap">
                        {{ is_array($item) ? ($item['label'] ?? '') : $item }}
                    </span>
                @endif
            @endforeach
        </nav>
    @else
        <div></div>
    @endif

    {{-- Back to list link --}}
    @if ($back)
        <a href="{{ $back }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-teal-700 hover:text-teal-800 transition group self-start sm:self-auto">
            <svg class="h-3.5 w-3.5 text-teal-600 group-hover:-translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <span>{{ $backLabel ?: __('common.back_to_list') }}</span>
        </a>
    @endif
</div>
