<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('catalogue.import_title') }}</h1>
            <p class="text-sm text-gray-500">{{ __('catalogue.import_subtitle') }}</p>
        </div>
        <div class="space-x-3">
            <a href="{{ asset('docs/sample_products_template.csv') }}" class="px-3 py-1.5 border border-gray-300 text-gray-700 text-xs font-medium rounded hover:bg-gray-50">
                📥 {{ __('catalogue.download_template') }}
            </a>
            <a href="{{ route('catalogue.index') }}" class="text-sm text-gray-600 hover:text-gray-900 font-medium">
                &larr; {{ __('common.back_to_list') }}
            </a>
        </div>
    </div>

    @if (! $import)
        <!-- Upload Step -->
        <div class="bg-white rounded-lg border border-gray-200 p-8 shadow-sm text-center">
            <form wire:submit.prevent="uploadAndPreview" class="max-w-md mx-auto space-y-4">
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 hover:border-indigo-500 transition">
                    <input type="file" wire:model="file" accept=".csv,.xlsx,.txt" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    <p class="text-xs text-gray-400 mt-2">{{ __('catalogue.supported_formats') }}: CSV, XLSX (Max 20MB)</p>
                </div>
                @error('file') <span class="text-xs text-red-600 block">{{ $message }}</span> @enderror

                <button type="submit" wire:loading.attr="disabled" class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-md shadow-sm transition disabled:opacity-50">
                    <span wire:loading.remove>{{ __('catalogue.upload_and_validate') }}</span>
                    <span wire:loading>{{ __('common.please_wait') }}</span>
                </button>
            </form>
        </div>
    @else
        <!-- Validation Summary Report (STEP 4) -->
        <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm space-y-6">
            <div class="flex items-center justify-between border-b border-gray-200 pb-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('catalogue.validation_report') }}: {{ $import->file_name }}</h2>
                    <p class="text-xs text-gray-500 mt-1">{{ __('catalogue.preview_notice') }}</p>
                </div>
                <div class="flex items-center space-x-3">
                    @if ($import->totals['error_rows'] > 0)
                        <button type="button" wire:click="downloadErrorsCsv" class="px-3 py-1.5 bg-red-50 text-red-700 border border-red-200 text-xs font-medium rounded hover:bg-red-100">
                            {{ __('catalogue.download_error_csv') }}
                        </button>
                    @endif
                    <button type="button" wire:click="cancel" class="px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50">
                        {{ __('common.cancel') }} ({{ __('catalogue.save_nothing') }})
                    </button>
                    <button type="button" wire:click="confirm" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md shadow-sm">
                        {{ __('catalogue.confirm_and_import') }} ({{ $import->totals['valid_rows'] }} {{ __('catalogue.valid_items') }})
                    </button>
                </div>
            </div>

            <!-- Totals Metrics -->
            <div class="grid grid-cols-3 gap-4 text-center">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <span class="text-xs uppercase text-gray-500 font-medium">{{ __('catalogue.total_rows') }}</span>
                    <div class="text-2xl font-bold text-gray-900 mt-1">{{ $import->totals['total_rows'] ?? 0 }}</div>
                </div>
                <div class="bg-green-50 p-4 rounded-lg">
                    <span class="text-xs uppercase text-green-700 font-medium">{{ __('catalogue.valid_rows') }}</span>
                    <div class="text-2xl font-bold text-green-700 mt-1">{{ $import->totals['valid_rows'] ?? 0 }}</div>
                </div>
                <div class="bg-red-50 p-4 rounded-lg">
                    <span class="text-xs uppercase text-red-700 font-medium">{{ __('catalogue.error_rows') }}</span>
                    <div class="text-2xl font-bold text-red-700 mt-1">{{ $import->totals['error_rows'] ?? 0 }}</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex items-center space-x-4 pt-2">
                <span class="text-xs font-medium text-gray-700">{{ __('catalogue.filter_rows') }}:</span>
                <button type="button" wire:click="$set('filter', 'all')" class="text-xs px-2.5 py-1 rounded {{ $filter === 'all' ? 'bg-indigo-100 text-indigo-800 font-bold' : 'text-gray-600 hover:bg-gray-100' }}">
                    {{ __('common.all') }}
                </button>
                <button type="button" wire:click="$set('filter', 'errors_only')" class="text-xs px-2.5 py-1 rounded {{ $filter === 'errors_only' ? 'bg-red-100 text-red-800 font-bold' : 'text-gray-600 hover:bg-gray-100' }}">
                    {{ __('catalogue.errors_only') }}
                </button>
                <button type="button" wire:click="$set('filter', 'valid_only')" class="text-xs px-2.5 py-1 rounded {{ $filter === 'valid_only' ? 'bg-green-100 text-green-800 font-bold' : 'text-gray-600 hover:bg-gray-100' }}">
                    {{ __('catalogue.valid_only') }}
                </button>
            </div>

            <!-- Rows Table -->
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="bg-gray-50 text-gray-500 uppercase">
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
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($rows as $r)
                            @php $raw = $r->raw_data; @endphp
                            <tr class="{{ $r->status === 'error' ? 'bg-red-50' : '' }}">
                                <td class="px-4 py-2 font-mono text-gray-500">{{ $r->row_number }}</td>
                                <td class="px-4 py-2 font-semibold text-gray-900">{{ $raw['brand'] ?? '—' }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $raw['generic_name'] ?? '—' }}</td>
                                <td class="px-4 py-2 font-mono text-gray-500">{{ $raw['barcode'] ?? '—' }}</td>
                                <td class="px-4 py-2 uppercase">{{ $raw['classification'] ?? '—' }}</td>
                                <td class="px-4 py-2 font-medium">{{ ! empty($raw['price']) ? number_format($raw['price'], 2) . ' ETB' : '—' }}</td>
                                <td class="px-4 py-2">
                                    @if ($r->status === 'valid')
                                        <span class="inline-flex px-1.5 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800">{{ __('catalogue.valid') }}</span>
                                    @else
                                        <span class="inline-flex px-1.5 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800">{{ __('catalogue.invalid') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-red-600">
                                    @if (! empty($r->errors))
                                        <ul class="list-disc list-inside space-y-0.5">
                                            @foreach ($r->errors as $err)
                                                <li>{{ $err }}</li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-gray-500">{{ __('catalogue.no_rows_match_filter') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                @if ($rows->hasPages())
                    <div class="px-4 py-3 border-t border-gray-200">
                        {{ $rows->links() }}
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
