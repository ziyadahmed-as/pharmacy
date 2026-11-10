<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('catalogue.title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('catalogue.subtitle') }}</p>
        </div>
        @if ($canManage || $canEditBasic)
            <div class="flex items-center gap-2.5">
                @if ($canManage)
                    <a href="{{ route('catalogue.import') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 hover:text-slate-900 transition">
                        <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        {{ __('catalogue.import_title') }}
                    </a>
                @endif
                <a href="{{ route('catalogue.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    {{ __('catalogue.add_product') }}
                </a>
            </div>
        @endif
    </div>

    @if (session()->has('message'))
        <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800" role="status">
            <svg class="h-5 w-5 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
            {{ session('message') }}
        </div>
    @endif

    {{-- Filters & Search Card --}}
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('common.search') }}</label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('catalogue.search_placeholder') }}" class="block w-full rounded-lg border border-slate-300 py-2 pl-9 pr-3 text-sm placeholder:text-slate-400 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('catalogue.classification') }}</label>
                <select wire:model.live="classification" class="block w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                    <option value="">{{ __('common.all') }}</option>
                    <option value="rx">Rx (Prescription)</option>
                    <option value="otc">OTC</option>
                    <option value="controlled">Controlled</option>
                    <option value="non_drug">Non-Drug</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('catalogue.category') }}</label>
                <select wire:model.live="categoryId" class="block w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                    <option value="">{{ __('common.all') }}</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('common.status') }}</label>
                <select wire:model.live="status" class="block w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                    <option value="all">{{ __('common.all') }}</option>
                    <option value="active">{{ __('common.active') }}</option>
                    <option value="inactive">{{ __('common.inactive') }}</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Products Table --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50/75 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3.5 text-left">{{ __('catalogue.product_name') }}</th>
                        <th scope="col" class="px-5 py-3.5 text-left">{{ __('catalogue.generic_name') }}</th>
                        <th scope="col" class="px-5 py-3.5 text-left">{{ __('catalogue.classification') }}</th>
                        <th scope="col" class="px-5 py-3.5 text-left">{{ __('catalogue.barcode') }}</th>
                        <th scope="col" class="px-5 py-3.5 text-left">{{ __('catalogue.selling_price') }}</th>
                        <th scope="col" class="px-5 py-3.5 text-left">{{ __('common.status') }}</th>
                        <th scope="col" class="px-5 py-3.5 text-right">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($products as $p)
                        <tr class="hover:bg-slate-50/70 transition {{ ! $p->is_active ? 'bg-slate-50/40 opacity-75' : '' }}">
                            <td class="px-5 py-3.5 font-semibold text-slate-900">
                                <a href="{{ route('catalogue.show', $p) }}" class="text-teal-700 hover:text-teal-900 transition">
                                    {{ $p->brand }}
                                </a>
                                <div class="text-xs text-slate-400 font-normal mt-0.5">{{ $p->strength }} {{ $p->dosage_form }} ({{ $p->pack_size }})</div>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600 font-medium">{{ $p->generic_name }}</td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                                    @if($p->classification === 'rx') bg-blue-50 text-blue-700 ring-1 ring-blue-600/20
                                    @elseif($p->classification === 'controlled') bg-rose-50 text-rose-700 ring-1 ring-rose-600/20
                                    @elseif($p->classification === 'otc') bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20
                                    @else bg-slate-100 text-slate-700 ring-1 ring-slate-500/20 @endif">
                                    {{ strtoupper($p->classification) }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-slate-500 font-mono text-xs">{{ $p->barcode ?? '—' }}</td>
                            <td class="px-5 py-3.5 font-semibold text-slate-900">
                                @if ($p->latestPrice)
                                    {{ number_format($p->latestPrice->price / 100, 2) }} <span class="text-xs font-normal text-slate-500">ETB</span>
                                @else
                                    <span class="text-slate-400 text-xs italic">{{ __('catalogue.no_price_set') }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                @if ($p->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-emerald-100 text-emerald-800">{{ __('common.active') }}</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-slate-100 text-slate-600">{{ __('common.inactive') }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right space-x-2 text-xs font-medium whitespace-nowrap">
                                <a href="{{ route('catalogue.show', $p) }}" class="text-slate-600 hover:text-slate-900 transition">{{ __('common.view') }}</a>
                                @if ($canManage || $canEditBasic)
                                    <a href="{{ route('catalogue.edit', $p) }}" class="text-teal-700 hover:text-teal-900 font-semibold transition">{{ __('common.edit') }}</a>
                                @endif
                                @if ($canManage)
                                    @if ($p->is_active)
                                        <button type="button" wire:click="deactivate({{ $p->id }})" wire:confirm="{{ __('catalogue.confirm_deactivate') }}" class="text-rose-600 hover:text-rose-800 transition">
                                            {{ __('catalogue.deactivate') }}
                                        </button>
                                    @else
                                        <button type="button" wire:click="reactivate({{ $p->id }})" class="text-emerald-600 hover:text-emerald-800 transition">
                                            {{ __('catalogue.reactivate') }}
                                        </button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="h-10 w-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                                    <p class="mt-2 text-sm font-medium text-slate-500">{{ __('catalogue.no_products_found') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($products->hasPages())
            <div class="px-5 py-3 border-t border-slate-200 bg-slate-50/50">
                {{ $products->links() }}
            </div>
        @endif
    </div>
</div>
