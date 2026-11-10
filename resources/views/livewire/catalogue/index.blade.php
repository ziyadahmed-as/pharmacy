<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('catalogue.title') }}</h1>
            <p class="text-sm text-gray-500">{{ __('catalogue.subtitle') }}</p>
        </div>
        @if ($canManage || $canEditBasic)
            <a href="{{ route('catalogue.create') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md shadow-sm transition">
                + {{ __('catalogue.add_product') }}
            </a>
        @endif
    </div>

    @if (session()->has('message'))
        <div class="p-4 bg-green-50 border-l-4 border-green-500 text-green-700 text-sm">
            {{ session('message') }}
        </div>
    @endif

    <!-- Filters & Search -->
    <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-700">{{ __('common.search') }}</label>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('catalogue.search_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700">{{ __('catalogue.classification') }}</label>
            <select wire:model.live="classification" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">{{ __('common.all') }}</option>
                <option value="rx">Rx (Prescription)</option>
                <option value="otc">OTC</option>
                <option value="controlled">Controlled</option>
                <option value="non_drug">Non-Drug</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700">{{ __('catalogue.category') }}</label>
            <select wire:model.live="categoryId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">{{ __('common.all') }}</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700">{{ __('common.status') }}</label>
            <select wire:model.live="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="all">{{ __('common.all') }}</option>
                <option value="active">{{ __('common.active') }}</option>
                <option value="inactive">{{ __('common.inactive') }}</option>
            </select>
        </div>
    </div>

    <!-- Products Table -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase">
                <tr>
                    <th class="px-6 py-3 text-left">{{ __('catalogue.product_name') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('catalogue.generic_name') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('catalogue.classification') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('catalogue.barcode') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('catalogue.selling_price') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('common.status') }}</th>
                    <th class="px-6 py-3 text-right">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse ($products as $p)
                    <tr class="{{ ! $p->is_active ? 'bg-gray-50 opacity-75' : '' }}">
                        <td class="px-6 py-4 font-semibold text-gray-900">
                            <a href="{{ route('catalogue.show', $p) }}" class="text-indigo-600 hover:text-indigo-900">
                                {{ $p->brand }}
                            </a>
                            <div class="text-xs text-gray-500">{{ $p->strength }} {{ $p->dosage_form }} ({{ $p->pack_size }})</div>
                        </td>
                        <td class="px-6 py-4 text-gray-600">{{ $p->generic_name }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium 
                                @if($p->classification === 'rx') bg-blue-100 text-blue-800
                                @elseif($p->classification === 'controlled') bg-red-100 text-red-800
                                @elseif($p->classification === 'otc') bg-green-100 text-green-800
                                @else bg-gray-100 text-gray-800 @endif">
                                {{ strtoupper($p->classification) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-500 font-mono text-xs">{{ $p->barcode ?? '—' }}</td>
                        <td class="px-6 py-4 text-gray-900 font-medium">
                            @if ($p->latestPrice)
                                {{ number_format($p->latestPrice->price / 100, 2) }} ETB
                            @else
                                <span class="text-gray-400 text-xs italic">{{ __('catalogue.no_price_set') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if ($p->is_active)
                                <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800">{{ __('common.active') }}</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">{{ __('common.inactive') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right space-x-2 text-xs font-medium">
                            <a href="{{ route('catalogue.show', $p) }}" class="text-gray-600 hover:text-gray-900">{{ __('common.view') }}</a>
                            @if ($canManage || $canEditBasic)
                                <a href="{{ route('catalogue.edit', $p) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('common.edit') }}</a>
                            @endif
                            @if ($canManage)
                                @if ($p->is_active)
                                    <button type="button" wire:click="deactivate({{ $p->id }})" wire:confirm="{{ __('catalogue.confirm_deactivate') }}" class="text-red-600 hover:text-red-900">
                                        {{ __('catalogue.deactivate') }}
                                    </button>
                                @else
                                    <button type="button" wire:click="reactivate({{ $p->id }})" class="text-green-600 hover:text-green-900">
                                        {{ __('catalogue.reactivate') }}
                                    </button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-500">{{ __('catalogue.no_products_found') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="px-6 py-4 border-t border-gray-200">
            {{ $products->links() }}
        </div>
    </div>
</div>
