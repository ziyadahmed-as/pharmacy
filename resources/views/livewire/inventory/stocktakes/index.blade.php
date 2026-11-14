<div class="max-w-6xl mx-auto space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('stocktake.title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('stocktake.subtitle') }}</p>
        </div>
        @if ($canManage)
            <button
                wire:click="openCreateModal"
                class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 transition"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('stocktake.new_stocktake') }}
            </button>
        @endif
    </div>

    {{-- Flash message --}}
    @if (session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 flex flex-wrap gap-4 items-center">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('common.status') }}</label>
            <select wire:model.live="statusFilter" class="rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-500/20">
                <option value="">{{ __('common.all') }}</option>
                <option value="counting">{{ __('stocktake.status_counting') }}</option>
                <option value="review">{{ __('stocktake.status_review') }}</option>
                <option value="approved">{{ __('stocktake.status_approved') }}</option>
                <option value="rejected">{{ __('stocktake.status_rejected') }}</option>
            </select>
        </div>
    </div>

    {{-- Stocktake list --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-100">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-600">#</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-600">{{ __('stocktake.type') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-600">{{ __('stocktake.scope') }}</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase text-slate-600">{{ __('stocktake.lines') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-600">{{ __('common.status') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-600">{{ __('stocktake.created_by') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-600">{{ __('common.date') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($stocktakes as $st)
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-4 font-mono text-xs text-slate-500">#{{ $st->id }}</td>
                        <td class="px-6 py-4 text-sm font-medium text-slate-900">
                            {{ ucfirst($st->type) }}
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-600">
                            @if ($st->scope_type)
                                <span class="capitalize">{{ $st->scope_type }}:</span> {{ $st->scope_value }}
                            @else
                                <span class="text-slate-400">{{ __('stocktake.all_stock') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right text-sm font-semibold text-slate-900">
                            {{ $st->lines_count }}
                        </td>
                        <td class="px-6 py-4">
                            @include('livewire.inventory.stocktakes._status_badge', ['status' => $st->status])
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-700">{{ $st->createdBy?->name }}</td>
                        <td class="px-6 py-4 text-xs text-slate-500">{{ $st->created_at->format('M d, Y H:i') }}</td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('inventory.stocktakes.show', $st) }}" wire:navigate class="text-teal-600 hover:text-teal-800 text-sm font-semibold">
                                {{ __('common.view') }} &rarr;
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-sm text-slate-500">
                            {{ __('stocktake.no_stocktakes_found') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($stocktakes->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $stocktakes->links() }}
            </div>
        @endif
    </div>

    {{-- Create Stocktake Modal --}}
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-slate-900">{{ __('stocktake.new_stocktake') }}</h2>
                    <button wire:click="closeCreateModal" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                @if ($errorMessage)
                    <div class="rounded-lg bg-rose-50 border border-rose-200 p-3 text-sm text-rose-800">{{ $errorMessage }}</div>
                @endif
                @if ($errors->any())
                    <div class="rounded-lg bg-rose-50 border border-rose-200 p-3 text-sm text-rose-800">
                        @foreach ($errors->all() as $err)
                            <p>{{ $err }}</p>
                        @endforeach
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">{{ __('stocktake.type') }} <span class="text-rose-500">*</span></label>
                    <select wire:model.live="type" class="block w-full rounded-xl border border-slate-300 py-2.5 px-3 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-500/20">
                        <option value="full">{{ __('stocktake.type_full') }}</option>
                        <option value="partial">{{ __('stocktake.type_partial') }}</option>
                    </select>
                </div>

                @if ($type === 'partial')
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">{{ __('stocktake.scope_type') }}</label>
                        <select wire:model="scope_type" class="block w-full rounded-xl border border-slate-300 py-2.5 px-3 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-500/20">
                            <option value="category">{{ __('stocktake.scope_category') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">{{ __('stocktake.scope_value') }} <span class="text-rose-500">*</span></label>
                        <input
                            type="text"
                            wire:model="scope_value"
                            placeholder="{{ __('stocktake.scope_value_placeholder') }}"
                            class="block w-full rounded-xl border border-slate-300 py-2.5 px-3 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-500/20"
                        />
                        @error('scope_value') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button wire:click="closeCreateModal" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                        {{ __('common.cancel') }}
                    </button>
                    <button
                        wire:click="createStocktake"
                        wire:loading.attr="disabled"
                        class="rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 transition disabled:opacity-50"
                    >
                        <span wire:loading.remove>{{ __('stocktake.start_stocktake') }}</span>
                        <span wire:loading>{{ __('common.processing') }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

