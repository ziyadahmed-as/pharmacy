<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('catalogue.import_title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('catalogue.import_subtitle') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ asset('docs/sample_products_template.csv') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
                <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                {{ __('catalogue.download_template') }}
            </a>
            <a href="{{ route('catalogue.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-slate-900 transition">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                {{ __('common.back_to_list') }}
            </a>
        </div>
    </div>

    @if (! $import)
        <!-- Upload Step -->
        <div class="rounded-2xl border border-slate-200 bg-white p-8 sm:p-12 shadow-sm text-center">
            <form wire:submit.prevent="uploadAndPreview" class="max-w-md mx-auto space-y-5">
                <div class="rounded-2xl border-2 border-dashed border-slate-300 p-8 hover:border-teal-500 bg-slate-50/50 transition flex flex-col items-center justify-center">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-teal-50 text-teal-600 mb-3">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    </div>
                    <input type="file" wire:model="file" accept=".csv,.xlsx,.txt" class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100 transition cursor-pointer">
                    <p class="text-xs text-slate-400 mt-2">{{ __('catalogue.supported_formats') }}: CSV, XLSX (Max 20MB)</p>
                </div>
                @error('file') <span class="text-xs text-rose-600 block font-medium">{{ $message }}</span> @enderror

                <button type="submit" wire:loading.attr="disabled" class="w-full rounded-xl bg-teal-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition disabled:opacity-50">
                    <span wire:loading.remove>{{ __('catalogue.upload_and_validate') }}</span>
                    <span wire:loading class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        {{ __('common.please_wait') }}
                    </span>
                </button>
            </form>
        </div>
    @else
        <!-- Validation Summary Report -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-100 pb-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">{{ __('catalogue.validation_report') }}: {{ $import->file_name }}</h2>
                    <p class="text-xs text-slate-500 mt-0.5">{{ __('catalogue.preview_notice') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    @if ($import->totals['error_rows'] > 0)
                        <button type="button" wire:click="downloadErrorsCsv" class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            {{ __('catalogue.download_error_csv') }}
                        </button>
                    @endif
                    <button type="button" wire:click="cancel" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                        {{ __('common.cancel') }} ({{ __('catalogue.save_nothing') }})
                    </button>
                    <button type="button" wire:click="confirm" class="rounded-xl bg-teal-600 px-5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition">
                        {{ __('catalogue.confirm_and_import') }} ({{ $import->totals['valid_rows'] }} {{ __('catalogue.valid_items') }})
                    </button>
                </div>
            </div>

            <!-- Totals Metrics -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-center">
                <div class="rounded-xl border border-slate-200 bg-slate-50/75 p-4">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('catalogue.total_rows') }}</span>
                    <div class="text-2xl font-extrabold text-slate-900 mt-1">{{ number_format($import->totals['total_rows'] ?? 0) }}</div>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-4">
                    <span class="text-xs font-semibold uppercase tracking-wider text-emerald-800">{{ __('catalogue.valid_rows') }}</span>
                    <div class="text-2xl font-extrabold text-emerald-700 mt-1">{{ number_format($import->totals['valid_rows'] ?? 0) }}</div>
                </div>
                <div class="rounded-xl border border-rose-200 bg-rose-50/70 p-4">
                    <span class="text-xs font-semibold uppercase tracking-wider text-rose-800">{{ __('catalogue.error_rows') }}</span>
                    <div class="text-2xl font-extrabold text-rose-700 mt-1">{{ number_format($import->totals['error_rows'] ?? 0) }}</div>
                </div>
            </div>

            <!-- Filter Pills -->
            <div class="flex items-center gap-2 pt-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 mr-1">{{ __('catalogue.filter_rows') }}:</span>
                <button type="button" wire:click="$set('filter', 'all')" class="text-xs px-3 py-1 rounded-lg font-medium transition {{ $filter === 'all' ? 'bg-teal-600 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    {{ __('common.all') }}
                </button>
                <button type="button" wire:click="$set('filter', 'errors_only')" class="text-xs px-3 py-1 rounded-lg font-medium transition {{ $filter === 'errors_only' ? 'bg-rose-600 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    {{ __('catalogue.errors_only') }}
                </button>
                <button type="button" wire:click="$set('filter', 'valid_only')" class="text-xs px-3 py-1 rounded-lg font-medium transition {{ $filter === 'valid_only' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    {{ __('catalogue.valid_only') }}
                </button>
            </div>

            <!-- Rows Table -->
            <div class="rounded-xl border border-slate-200 overflow-hidden">
                <table class="min-w-full divide-y divide-slate-200 text-xs">
                    <thead class="bg-slate-50/75 text-slate-500 uppercase tracking-wider font-semibold">
                        <tr>
                            <th class="px-4 py-2.5 text-left">#</th>
                            <th class="px-4 py-2.5 text-left">{{ __('catalogue.product_name') }}</th>
                            <th class="px-4 py-2.5 text-left">{{ __('catalogue.generic_name') }}</th>
                            <th class="px-4 py-2.5 text-left">{{ __('catalogue.barcode') }}</th>
                            <th class="px-4 py-2.5 text-left">{{ __('catalogue.classification') }}</th>
                            <th class="px-4 py-2.5 text-left">{{ __('catalogue.price') }}</th>
                            <th class="px-4 py-2.5 text-left">{{ __('common.status') }}</th>
                            <th class="px-4 py-2.5 text-left">{{ __('catalogue.errors') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($rows as $r)
                            @php $raw = $r->raw_data; @endphp
                            <tr class="hover:bg-slate-50/60 transition {{ $r->status === 'error' ? 'bg-rose-50/50' : '' }}">
                                <td class="px-4 py-2.5 font-mono text-slate-400">{{ $r->row_number }}</td>
                                <td class="px-4 py-2.5 font-semibold text-slate-900">{{ $raw['brand'] ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-slate-600">{{ $raw['generic_name'] ?? '—' }}</td>
                                <td class="px-4 py-2.5 font-mono text-slate-500">{{ $raw['barcode'] ?? '—' }}</td>
                                <td class="px-4 py-2.5 uppercase text-slate-700 font-medium">{{ $raw['classification'] ?? '—' }}</td>
                                <td class="px-4 py-2.5 font-semibold text-slate-900">{{ ! empty($raw['price']) ? number_format($raw['price'], 2) . ' ETB' : '—' }}</td>
                                <td class="px-4 py-2.5">
                                    @if ($r->status === 'valid')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">{{ __('catalogue.valid') }}</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800">{{ __('catalogue.invalid') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-rose-700">
                                    @if (! empty($r->errors))
                                        <ul class="list-disc list-inside space-y-0.5 font-medium">
                                            @foreach ($r->errors as $err)
                                                <li>{{ $err }}</li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-slate-400 italic">{{ __('catalogue.no_rows_match_filter') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                @if ($rows->hasPages())
                    <div class="px-4 py-3 border-t border-slate-200 bg-slate-50/50">
                        {{ $rows->links() }}
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
