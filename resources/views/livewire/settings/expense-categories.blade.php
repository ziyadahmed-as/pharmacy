<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Expense Categories</h1>
            <p class="text-sm text-slate-500 mt-0.5">Manage operational expense categories used for bookkeeping and financial reports.</p>
        </div>
        @if ($canManage)
            <div>
                <button type="button" wire:click="openCreateModal" class="inline-flex items-center gap-1.5 rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Category
                </button>
            </div>
        @endif
    </div>

    @if (session()->has('success'))
        <div class="rounded-lg bg-teal-50 border border-teal-200 p-4 text-sm text-teal-800">
            {{ session('success') }}
        </div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-5 py-3">Code</th>
                        <th class="px-5 py-3">Name</th>
                        <th class="px-5 py-3">Expenses Count</th>
                        <th class="px-5 py-3">Status</th>
                        @if ($canManage)
                            <th class="px-5 py-3 text-right">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($categories as $cat)
                        <tr wire:key="cat-{{ $cat->id }}" class="hover:bg-slate-50 transition">
                            <td class="px-5 py-3 font-mono text-xs font-semibold text-slate-700">{{ $cat->code }}</td>
                            <td class="px-5 py-3 font-medium text-slate-800">{{ $cat->name }}</td>
                            <td class="px-5 py-3 tabular-nums text-slate-600">{{ $cat->expenses_count }}</td>
                            <td class="px-5 py-3">
                                @if ($cat->is_active)
                                    <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 border border-green-200">Active</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 border border-slate-200">Inactive</span>
                                @endif
                            </td>
                            @if ($canManage)
                                <td class="px-5 py-3 text-right space-x-2">
                                    <button type="button" wire:click="edit({{ $cat->id }})" class="text-xs font-semibold text-teal-600 hover:text-teal-800">Edit</button>
                                    <button type="button" wire:click="toggleActive({{ $cat->id }})" class="text-xs font-semibold {{ $cat->is_active ? 'text-amber-600 hover:text-amber-800' : 'text-green-600 hover:text-green-800' }}">
                                        {{ $cat->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-6 text-center text-slate-400">No expense categories configured.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200 overflow-hidden">
                <div class="border-b border-slate-100 bg-slate-50 px-6 py-4 flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-800">{{ $editingId ? 'Edit Category' : 'Add Category' }}</h3>
                    <button type="button" wire:click="$set('showModal', false)" class="rounded-lg p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Code *</label>
                        <input type="text" wire:model="code" class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-teal-500 focus:ring-teal-500" placeholder="e.g. utilities, rent" />
                        @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Name *</label>
                        <input type="text" wire:model="name" class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-teal-500 focus:ring-teal-500" placeholder="e.g. Utilities" />
                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="cat_is_active" wire:model="is_active" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500" />
                        <label for="cat_is_active" class="text-sm text-slate-700">Active</label>
                    </div>
                </div>

                <div class="border-t border-slate-100 bg-slate-50 px-6 py-4 flex justify-end gap-3">
                    <button type="button" wire:click="$set('showModal', false)" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 transition">Cancel</button>
                    <button type="button" wire:click="save" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700 transition">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
