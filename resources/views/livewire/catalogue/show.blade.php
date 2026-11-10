<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center space-x-3">
                <h1 class="text-2xl font-bold text-gray-900">{{ $product->brand }}</h1>
                <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold
                    @if($product->classification === 'rx') bg-blue-100 text-blue-800
                    @elseif($product->classification === 'controlled') bg-red-100 text-red-800
                    @elseif($product->classification === 'otc') bg-green-100 text-green-800
                    @else bg-gray-100 text-gray-800 @endif">
                    {{ strtoupper($product->classification) }}
                </span>
                @if ($product->is_active)
                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-800">{{ __('common.active') }}</span>
                @else
                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-800">{{ __('common.inactive') }}</span>
                @endif
            </div>
            <p class="text-sm text-gray-500 mt-1">{{ $product->generic_name }} &bull; {{ $product->strength }} {{ $product->dosage_form }} ({{ $product->pack_size }})</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('catalogue.index') }}" class="text-sm text-gray-600 hover:text-gray-900 font-medium">
                &larr; {{ __('common.back_to_list') }}
            </a>
            @if ($canManage || $canEditBasic)
                <a href="{{ route('catalogue.edit', $product) }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md shadow-sm">
                    {{ __('common.edit') }}
                </a>
            @endif
        </div>
    </div>

    <!-- Product Details Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm space-y-4">
            <h3 class="text-base font-semibold text-gray-900 border-b border-gray-200 pb-2">{{ __('catalogue.details') }}</h3>
            
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-xs text-gray-500 uppercase">{{ __('catalogue.base_unit') }}</dt>
                    <dd class="font-medium text-gray-900">{{ $product->unit }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 uppercase">{{ __('catalogue.category') }}</dt>
                    <dd class="font-medium text-gray-900">{{ $product->category?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 uppercase">{{ __('catalogue.barcode') }}</dt>
                    <dd class="font-mono text-gray-900">{{ $product->barcode ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 uppercase">{{ __('catalogue.manufacturer') }}</dt>
                    <dd class="font-medium text-gray-900">{{ $product->manufacturer ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 uppercase">{{ __('catalogue.storage_condition') }}</dt>
                    <dd class="font-medium text-gray-900">{{ $product->storage_condition ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 uppercase">{{ __('catalogue.preferred_supplier') }}</dt>
                    <dd class="font-medium text-gray-900">{{ $product->preferred_supplier_name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 uppercase">{{ __('catalogue.allow_loose_sales') }}</dt>
                    <dd class="font-medium text-gray-900">{{ $product->loose_allowed ? __('common.yes') : __('common.no') }}</dd>
                </div>
            </dl>

            <!-- Stock Levels -->
            <div class="border-t border-gray-200 pt-3 mt-4">
                <h4 class="text-xs font-semibold uppercase text-gray-500 mb-2">{{ __('catalogue.stock_levels') }}</h4>
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="bg-gray-50 p-2 rounded">
                        <span class="text-xs text-gray-500">{{ __('catalogue.min_level') }}</span>
                        <div class="font-bold text-gray-900">{{ $product->min_level }}</div>
                    </div>
                    <div class="bg-gray-50 p-2 rounded">
                        <span class="text-xs text-gray-500">{{ __('catalogue.reorder_level') }}</span>
                        <div class="font-bold text-gray-900">{{ $product->reorder_level }}</div>
                    </div>
                    <div class="bg-gray-50 p-2 rounded">
                        <span class="text-xs text-gray-500">{{ __('catalogue.max_level') }}</span>
                        <div class="font-bold text-gray-900">{{ $product->max_level }}</div>
                    </div>
                </div>
            </div>

            <!-- Alternative Units -->
            @if ($product->units->isNotEmpty())
                <div class="border-t border-gray-200 pt-3 mt-4">
                    <h4 class="text-xs font-semibold uppercase text-gray-500 mb-2">{{ __('catalogue.alternative_units') }}</h4>
                    <ul class="divide-y divide-gray-100 text-sm">
                        @foreach ($product->units as $u)
                            <li class="py-1.5 flex justify-between">
                                <span class="font-medium text-gray-800">{{ $u->name }}</span>
                                <span class="text-gray-500">{{ $u->factor }} &times; {{ $product->unit }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <!-- UC-02 Price Panel Embedded Directly (Livewire Component) -->
        <div>
            @livewire('catalogue.price-panel', ['product' => $product], key('price-panel-'.$product->id))
        </div>
    </div>
</div>
