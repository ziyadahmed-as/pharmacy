<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-1">
                <a href="{{ route('catalogue.index') }}" class="hover:text-teal-600 transition flex items-center gap-1">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    {{ __('catalogue.title') }}
                </a>
                <span>/</span>
                <span class="text-slate-700">{{ __('catalogue.manage_categories') }}</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('catalogue.categories_title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('catalogue.categories_subtitle') }}</p>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="{{ route('catalogue.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 hover:text-slate-900 transition">
                <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                {{ __('catalogue.title') }}
            </a>
            <button wire:click="openCreateModal" class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('catalogue.add_category') }}
            </button>
        </div>
    </div>

    {{-- Flash messages --}}
    @if (session()->has('message'))
        <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800" role="status">
            <svg class="h-5 w-5 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
            <span>{{ session('message') }}</span>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" role="alert">
            <svg class="h-5 w-5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- Filters Card --}}
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('common.search') }}</label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('catalogue.search_categories_placeholder') }}" class="block w-full rounded-lg border border-slate-300 py-2 pl-9 pr-3 text-sm placeholder:text-slate-400 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('common.status') }}</label>
                <select wire:model.live="statusFilter" class="block w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                    <option value="all">{{ __('common.all') }}</option>
                    <option value="active">{{ __('common.active') }}</option>
                    <option value="inactive">{{ __('common.inactive') }}</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Categories Table --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50/75 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3.5 text-left">{{ __('catalogue.category_name') }}</th>
                        <th class="px-5 py-3.5 text-left">{{ __('catalogue.parent_category') }}</th>
                        <th class="px-5 py-3.5 text-left">{{ __('catalogue.category_description') }}</th>
                        <th class="px-5 py-3.5 text-center">{{ __('catalogue.products_count') }}</th>
                        <th class="px-5 py-3.5 text-center">{{ __('common.status') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($categories as $cat)
                        <tr wire:key="cat-{{ $cat->id }}" class="hover:bg-slate-50/70 transition">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2">
                                    @if ($cat->parent_category_id)
                                        <span class="text-slate-300 pl-2">↳</span>
                                    @endif
                                    <div class="font-semibold text-slate-900">{{ $cat->name }}</div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-slate-600">
                                @if ($cat->parent)
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                        {{ $cat->parent->name }}
                                    </span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-xs text-slate-500 max-w-xs truncate">
                                {{ $cat->description ?: '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <a href="{{ route('catalogue.index', ['categoryId' => $cat->id]) }}" class="inline-flex items-center rounded-full bg-teal-50 px-2.5 py-0.5 text-xs font-semibold text-teal-700 hover:bg-teal-100 transition">
                                    {{ $cat->products_count }} {{ __('catalogue.products_count_short') }}
                                </a>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <button type="button" wire:click="toggleStatus({{ $cat->id }})" title="{{ __('catalogue.toggle_status') }}"
                                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold transition {{ $cat->is_active ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20 hover:bg-emerald-100' : 'bg-slate-100 text-slate-600 ring-1 ring-slate-500/20 hover:bg-slate-200' }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $cat->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                    {{ $cat->is_active ? __('common.active') : __('common.inactive') }}
                                </button>
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1">
                                    <button type="button" wire:click="edit({{ $cat->id }})" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-teal-700 transition" title="{{ __('common.edit') }}">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button type="button" wire:click="confirmDelete({{ $cat->id }})" class="rounded-lg p-1.5 text-slate-500 hover:bg-rose-50 hover:text-rose-600 transition" title="{{ __('common.delete') }}">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-slate-500">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="h-10 w-10 text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                    <p class="font-medium text-slate-700">{{ __('catalogue.no_categories_found') }}</p>
                                    <p class="text-xs text-slate-400 mt-1">{{ __('catalogue.no_categories_hint') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($categories->hasPages())
            <div class="border-t border-slate-200 px-5 py-3">
                {{ $categories->links() }}
            </div>
        @endif
    </div>

    {{-- Create / Edit Modal --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 sm:p-6" role="dialog" aria-modal="true">
            <div class="w-full max-w-lg rounded-2xl bg-white shadow-xl ring-1 ring-slate-900/5 overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h2 class="text-lg font-bold text-slate-900">
                        {{ $editingId ? __('catalogue.edit_category') : __('catalogue.add_category') }}
                    </h2>
                    <button type="button" wire:click="$set('showModal', false)" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form wire:submit="save" class="p-6 space-y-4">
                    {{-- Name --}}
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
                            {{ __('catalogue.category_name') }} <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" wire:model="name" required class="block w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition @error('name') border-rose-300 @enderror">
                        @error('name')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Parent Category --}}
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
                            {{ __('catalogue.parent_category') }}
                        </label>
                        <select wire:model="parent_category_id" class="block w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition @error('parent_category_id') border-rose-300 @enderror">
                            <option value="">{{ __('catalogue.no_parent') }}</option>
                            @foreach ($eligibleParents as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                        @error('parent_category_id')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Description --}}
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
                            {{ __('catalogue.category_description') }}
                        </label>
                        <textarea wire:model="description" rows="3" class="block w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition @error('description') border-rose-300 @enderror"></textarea>
                        @error('description')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Is Active --}}
                    <div class="flex items-center gap-3 pt-2">
                        <input type="checkbox" id="cat_is_active" wire:model="is_active" class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                        <label for="cat_is_active" class="text-sm font-medium text-slate-700">
                            {{ __('catalogue.category_active_label') }}
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" wire:click="$set('showModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                            {{ __('common.cancel') }}
                        </button>
                        <button type="submit" class="rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 transition">
                            {{ __('common.save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Delete Confirmation Modal --}}
    @if ($showDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 sm:p-6" role="dialog" aria-modal="true">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-xl ring-1 ring-slate-900/5 overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center gap-3 text-rose-600 mb-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-rose-100">
                            <svg class="h-6 w-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">{{ __('catalogue.delete_category_title') }}</h3>
                            <p class="text-xs text-slate-500">{{ $deletingName }}</p>
                        </div>
                    </div>

                    <p class="text-sm text-slate-600">
                        {{ __('catalogue.delete_category_confirm_message', ['name' => $deletingName]) }}
                    </p>

                    @if ($deletingProductCount > 0)
                        <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                            <strong>{{ __('common.warning') }}:</strong> {{ __('catalogue.category_has_products_warning', ['count' => $deletingProductCount]) }}
                        </div>
                    @endif

                    @if ($deletingChildCount > 0)
                        <div class="mt-2 rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                            {{ __('catalogue.category_has_children_notice', ['count' => $deletingChildCount]) }}
                        </div>
                    @endif

                    @error('delete')
                        <div class="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800">
                            {{ $message }}
                        </div>
                    @enderror

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <button type="button" wire:click="$set('showDeleteModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                            {{ __('common.cancel') }}
                        </button>
                        <button type="button" wire:click="delete" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-700 transition" @if ($deletingProductCount > 0) disabled class="opacity-50 cursor-not-allowed rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white" @endif>
                            {{ __('common.delete') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
