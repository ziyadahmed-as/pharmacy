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
                @if ($product->is_cold_chain)
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-cyan-50 text-cyan-700 ring-1 ring-cyan-600/20">
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        {{ __('catalogue.cold_chain') }}
                    </span>
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
                @if ($product->product_code)
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('catalogue.product_code') }}</dt>
                        <dd class="mt-0.5 font-mono text-slate-800">{{ $product->product_code }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('catalogue.category') }}</dt>
                    <dd class="mt-0.5 font-medium text-slate-800">
                        @if ($product->category?->parent)
                            <span class="text-slate-400">{{ $product->category->parent->name }} &rsaquo;</span>
                        @endif
                        {{ $product->category?->name ?? '—' }}
                    </dd>
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
        <div class="space-y-6">
            @livewire('catalogue.price-panel', ['product' => $product], key('price-panel-'.$product->id))

            <!-- Clinical Drug Information (DDD 5.3 drug_information) -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                        <h3 class="text-base font-bold text-slate-900">{{ __('catalogue.clinical_information') }}</h3>
                    </div>
                    @if ($product->drugInformation?->updater)
                        <span class="text-xs text-slate-400">Updated by {{ $product->drugInformation->updater->name }}</span>
                    @endif
                </div>

                @if ($product->drugInformation)
                    <div class="space-y-3 text-sm">
                        @if ($product->drugInformation->indications)
                            <div>
                                <h4 class="text-xs font-bold uppercase tracking-wider text-teal-700">{{ __('catalogue.indications') }}</h4>
                                <p class="mt-1 text-slate-700 whitespace-pre-line">{{ $product->drugInformation->indications }}</p>
                            </div>
                        @endif
                        @if ($product->drugInformation->dosage_guidance)
                            <div>
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-600">{{ __('catalogue.dosage_guidance') }}</h4>
                                <p class="mt-1 text-slate-700 whitespace-pre-line">{{ $product->drugInformation->dosage_guidance }}</p>
                            </div>
                        @endif
                        @if ($product->drugInformation->contraindications)
                            <div>
                                <h4 class="text-xs font-bold uppercase tracking-wider text-red-600">{{ __('catalogue.contraindications') }}</h4>
                                <p class="mt-1 text-red-700 whitespace-pre-line">{{ $product->drugInformation->contraindications }}</p>
                            </div>
                        @endif
                        @if ($product->drugInformation->side_effects)
                            <div>
                                <h4 class="text-xs font-bold uppercase tracking-wider text-amber-700">{{ __('catalogue.side_effects') }}</h4>
                                <p class="mt-1 text-slate-700 whitespace-pre-line">{{ $product->drugInformation->side_effects }}</p>
                            </div>
                        @endif
                        @if ($product->drugInformation->interactions)
                            <div>
                                <h4 class="text-xs font-bold uppercase tracking-wider text-purple-700">{{ __('catalogue.interactions') }}</h4>
                                <p class="mt-1 text-slate-700 whitespace-pre-line">{{ $product->drugInformation->interactions }}</p>
                            </div>
                        @endif
                    </div>
                @else
                    <p class="text-xs text-slate-400 italic">{{ __('catalogue.no_clinical_information') }}</p>
                @endif
            </div>

            <!-- Generic / Brand Substitutes (DDD 5.3 product_substitute) -->
            @if ($product->substitutes->isNotEmpty())
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
                    <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                        <svg class="h-5 w-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        <h3 class="text-base font-bold text-slate-900">{{ __('catalogue.suggested_substitutes') }}</h3>
                    </div>
                    <ul class="divide-y divide-slate-100 text-sm">
                        @foreach ($product->substitutes as $sub)
                            <li class="py-2.5 flex items-center justify-between">
                                <div>
                                    <a href="{{ route('catalogue.show', $sub) }}" class="font-semibold text-teal-700 hover:text-teal-900 transition">
                                        {{ $sub->brand }}
                                    </a>
                                    <p class="text-xs text-slate-500">{{ $sub->strength }} {{ $sub->dosage_form }}</p>
                                    @if ($sub->pivot->note)
                                        <p class="text-xs text-slate-400 italic mt-0.5">{{ $sub->pivot->note }}</p>
                                    @endif
                                </div>
                                <div class="text-right">
                                    @if ($sub->latestPrice)
                                        <span class="text-sm font-bold text-slate-900">ETB {{ number_format($sub->latestPrice->price / 100, 2) }}</span>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</div>
