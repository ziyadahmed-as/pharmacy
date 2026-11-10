<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('catalogue.receive_stock') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('catalogue.receive_stock_subtitle') }}</p>
        </div>
        <a href="{{ route('catalogue.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-slate-900 transition">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            {{ __('common.back_to_list') }}
        </a>
    </div>

    @if ($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" role="alert">
            <div class="flex items-center gap-2 font-semibold mb-1">
                <svg class="h-5 w-5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>Please correct the errors below:</span>
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-xs text-rose-700">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form wire:submit.prevent="submit" class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-8">
        <!-- Header details -->
        <div>
            <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                <svg class="h-4 w-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800">Delivery Information</h3>
            </div>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.supplier') }} <span class="text-rose-500">*</span></label>
                    <input type="text" wire:model="supplier_name" placeholder="e.g. EPSS, Medtech" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('catalogue.delivery_note_no') }}</label>
                    <input type="text" wire:model="delivery_note_no" placeholder="e.g. DN-2026-0042" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('common.date') }} <span class="text-rose-500">*</span></label>
                    <input type="date" wire:model="receipt_date" class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition" required>
                </div>
            </div>
        </div>

        <!-- Receipt Lines -->
        <div class="space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800">{{ __('catalogue.receipt_items') }}</h3>
                </div>
                <button type="button" wire:click="addLine" class="inline-flex items-center gap-1 rounded-lg bg-teal-50 px-3 py-1.5 text-xs font-semibold text-teal-700 hover:bg-teal-100 transition">
                    + {{ __('catalogue.add_line') }}
                </button>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-xs">
                    <thead class="bg-slate-50/75 text-slate-500 uppercase tracking-wider font-semibold">
                        <tr>
                            <th class="px-3 py-2.5 text-left">{{ __('catalogue.product_name') }} <span class="text-rose-500">*</span></th>
                            <th class="px-3 py-2.5 text-left">{{ __('catalogue.batch_no') }} <span class="text-rose-500">*</span></th>
                            <th class="px-3 py-2.5 text-left">{{ __('catalogue.mfg_date') }}</th>
                            <th class="px-3 py-2.5 text-left">{{ __('catalogue.expiry_date') }} <span class="text-rose-500">*</span></th>
                            <th class="px-3 py-2.5 text-left">{{ __('catalogue.quantity') }} <span class="text-rose-500">*</span></th>
                            @if ($canViewCost)
                                <th class="px-3 py-2.5 text-left">{{ __('catalogue.unit_cost') }} (ETB)</th>
                            @endif
                            <th class="px-3 py-2.5 text-left">{{ __('catalogue.condition_flag') }}</th>
                            <th class="px-3 py-2.5 text-center">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($lines as $index => $line)
                            <tr class="hover:bg-slate-50/50">
                                <td class="p-2 w-1/4">
                                    <select wire:model="lines.{{ $index }}.product_id" class="w-full text-xs rounded-lg border border-slate-300 py-1.5 px-2 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition" required>
                                        <option value="">-- {{ __('catalogue.select_product') }} --</option>
                                        @foreach ($products as $p)
                                            <option value="{{ $p->id }}">{{ $p->brand }} ({{ $p->generic_name }})</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="p-2">
                                    <input type="text" wire:model="lines.{{ $index }}.batch_no" placeholder="Batch #" class="w-full text-xs rounded-lg border border-slate-300 py-1.5 px-2 font-mono focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition" required>
                                </td>
                                <td class="p-2">
                                    <input type="date" wire:model="lines.{{ $index }}.manufacturing_date" class="w-full text-xs rounded-lg border border-slate-300 py-1.5 px-2 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                                </td>
                                <td class="p-2">
                                    <input type="date" wire:model="lines.{{ $index }}.expiry_date" class="w-full text-xs rounded-lg border border-slate-300 py-1.5 px-2 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition" required>
                                </td>
                                <td class="p-2 w-20">
                                    <input type="number" min="1" wire:model="lines.{{ $index }}.quantity" class="w-full text-xs rounded-lg border border-slate-300 py-1.5 px-2 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition" required>
                                </td>
                                @if ($canViewCost)
                                    <td class="p-2 w-24">
                                        <input type="number" step="0.01" wire:model="lines.{{ $index }}.unit_cost" placeholder="0.00" class="w-full text-xs rounded-lg border border-slate-300 py-1.5 px-2 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                                    </td>
                                @endif
                                <td class="p-2 w-28">
                                    <select wire:model="lines.{{ $index }}.flag" class="w-full text-xs rounded-lg border border-slate-300 py-1.5 px-2 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                                        <option value="ok">{{ __('catalogue.flag_ok') }}</option>
                                        <option value="short">{{ __('catalogue.flag_short') }}</option>
                                        <option value="over">{{ __('catalogue.flag_over') }}</option>
                                        <option value="damaged">{{ __('catalogue.flag_damaged') }}</option>
                                    </select>
                                </td>
                                <td class="p-2 text-center">
                                    @if (count($lines) > 1)
                                        <button type="button" wire:click="removeLine({{ $index }})" class="p-1 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 transition" title="{{ __('common.remove') }}">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Shelf life override section (PM / Owner) -->
        <div class="border-t border-slate-100 pt-5">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" wire:model.live="overrideShelfLife" class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                <span class="text-xs font-semibold text-slate-700">{{ __('catalogue.override_shelf_life_checkbox') }}</span>
            </label>
            @if ($overrideShelfLife)
                <div class="mt-3 max-w-md">
                    <input type="text" wire:model="overrideReason" placeholder="{{ __('catalogue.override_shelf_life_reason_placeholder') }}" class="w-full text-xs rounded-xl border border-slate-300 py-2 px-3 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                </div>
            @endif
        </div>

        <div class="flex justify-end gap-3 border-t border-slate-100 pt-6">
            <button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-teal-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition disabled:opacity-50">
                <span wire:loading.remove>{{ __('catalogue.submit_receipt') }}</span>
                <span wire:loading class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    {{ __('common.please_wait') }}
                </span>
            </button>
        </div>
    </form>
</div>
