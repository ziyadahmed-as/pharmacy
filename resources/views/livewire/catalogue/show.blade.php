<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $product->brand }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                    @if($product->classification === 'rx') bg-blue-50 text-blue-700 ring-1 ring-blue-600/20
                    @elseif($product->classification === 'controlled') bg-rose-50 text-rose-700 ring-1 ring-rose-600/20
                    @elseif($product->classification === 'otc') bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20
                    @else bg-slate-100 text-slate-700 ring-1 ring-slate-500/20 @endif">
                    {{ strtoupper($product->classification) }}
                </span>
                @if ($product->is_active)
                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-emerald-100 text-emerald-800">{{ __('common.active') }}</span>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-slate-100 text-slate-600">{{ __('common.inactive') }}</span>
                @endif
            </div>
            <p class="mt-1 text-sm text-slate-500 font-medium">
                {{ $product->generic_name }} &bull; {{ $product->strength }} {{ $product->dosage_form }} ({{ $product->pack_size }})
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('catalogue.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-slate-900 transition">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                {{ __('common.back_to_list') }}
            </a>
            @if ($canManage || $canEditBasic)
                <a href="{{ route('catalogue.edit', $product) }}" class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    {{ __('common.edit') }}
                </a>
            @endif
        </div>
    </div>

    <!-- Product Details Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
            <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                <svg class="h-5 w-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <h3 class="text-base font-bold text-slate-900">{{ __('catalogue.details') }}</h3>
            </div>
            
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3.5 text-sm">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('catalogue.base_unit') }}</dt>
                    <dd class="mt-0.5 font-medium text-slate-800">{{ $product->unit }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('catalogue.category') }}</dt>
                    <dd class="mt-0.5 font-medium text-slate-800">{{ $product->category?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('catalogue.barcode') }}</dt>
                    <dd class="mt-0.5 font-mono text-slate-800">{{ $product->barcode ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('catalogue.manufacturer') }}</dt>
                    <dd class="mt-0.5 font-medium text-slate-800">{{ $product->manufacturer ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('catalogue.storage_condition') }}</dt>
                    <dd class="mt-0.5 font-medium text-slate-800">{{ $product->storage_condition ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('catalogue.preferred_supplier') }}</dt>
                    <dd class="mt-0.5 font-medium text-slate-800">{{ $product->preferred_supplier_name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('catalogue.allow_loose_sales') }}</dt>
                    <dd class="mt-0.5 font-medium text-slate-800">{{ $product->loose_allowed ? __('common.yes') : __('common.no') }}</dd>
                </div>
            </dl>

            <!-- Stock Levels -->
            <div class="border-t border-slate-100 pt-4">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2.5">{{ __('catalogue.stock_levels') }}</h4>
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-xl border border-slate-100 bg-slate-50/75 p-3">
                        <span class="text-xs text-slate-500 font-medium">{{ __('catalogue.min_level') }}</span>
                        <div class="text-xl font-bold text-slate-900 mt-0.5">{{ number_format($product->min_level) }}</div>
                    </div>
                    <div class="rounded-xl border border-amber-100 bg-amber-50/60 p-3">
                        <span class="text-xs text-amber-700 font-medium">{{ __('catalogue.reorder_level') }}</span>
                        <div class="text-xl font-bold text-amber-900 mt-0.5">{{ number_format($product->reorder_level) }}</div>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50/75 p-3">
                        <span class="text-xs text-slate-500 font-medium">{{ __('catalogue.max_level') }}</span>
                        <div class="text-xl font-bold text-slate-900 mt-0.5">{{ number_format($product->max_level) }}</div>
                    </div>
                </div>
            </div>

            <!-- Alternative Units -->
            @if ($product->units->isNotEmpty())
                <div class="border-t border-slate-100 pt-4">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2.5">{{ __('catalogue.alternative_units') }}</h4>
                    <ul class="divide-y divide-slate-100 text-sm">
                        @foreach ($product->units as $u)
                            <li class="py-2 flex items-center justify-between">
                                <span class="font-medium text-slate-800">{{ $u->name }}</span>
                                <span class="text-xs font-medium text-slate-500 bg-slate-100 px-2.5 py-0.5 rounded-full">{{ $u->factor }} &times; {{ $product->unit }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <!-- Price Panel Component -->
        <div>
            @livewire('catalogue.price-panel', ['product' => $product], key('price-panel-'.$product->id))
        </div>
    </div>
</div>
