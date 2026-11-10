<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center space-x-3">
                <h1 class="text-2xl font-bold text-gray-900">{{ $receipt->reference }}</h1>
                <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold
                    @if($receipt->status === 'posted') bg-green-100 text-green-800
                    @elseif($receipt->status === 'pending_confirmation') bg-amber-100 text-amber-800
                    @elseif($receipt->status === 'rejected') bg-red-100 text-red-800
                    @else bg-gray-100 text-gray-800 @endif">
                    {{ strtoupper(str_replace('_', ' ', $receipt->status)) }}
                </span>
            </div>
            <p class="text-sm text-gray-500 mt-1">{{ __('catalogue.supplier') }}: {{ $receipt->supplier_name }} &bull; {{ __('common.date') }}: {{ $receipt->receipt_date->format('Y-m-d') }}</p>
        </div>
        
        <div class="space-x-3">
            @if ($receipt->status === 'pending_confirmation' && $canConfirm)
                <button type="button" wire:click="reject" wire:confirm="{{ __('catalogue.confirm_reject_receipt') }}" class="px-4 py-2 border border-red-300 text-red-700 text-sm font-medium rounded-md hover:bg-red-50">
                    {{ __('catalogue.reject_receipt') }}
                </button>
                <button type="button" wire:click="confirm" class="px-5 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-md shadow-sm">
                    ✓ {{ __('catalogue.confirm_receipt') }}
                </button>
            @endif
            <a href="{{ route('catalogue.index') }}" class="text-sm text-gray-600 hover:text-gray-900 font-medium">
                &larr; {{ __('common.back_to_list') }}
            </a>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="p-4 bg-green-50 border-l-4 border-green-500 text-green-700 text-sm">
            {{ session('message') }}
        </div>
    @endif

    <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
        <h3 class="text-base font-semibold text-gray-900 mb-4">{{ __('catalogue.receipt_items') }}</h3>

        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="min-w-full divide-y divide-gray-200 text-xs">
                <thead class="bg-gray-50 text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-2 text-left">{{ __('catalogue.product_name') }}</th>
                        <th class="px-4 py-2 text-left">{{ __('catalogue.batch_no') }}</th>
                        <th class="px-4 py-2 text-left">{{ __('catalogue.mfg_date') }}</th>
                        <th class="px-4 py-2 text-left">{{ __('catalogue.expiry_date') }}</th>
                        <th class="px-4 py-2 text-left">{{ __('catalogue.quantity') }}</th>
                        @if ($canViewCost)
                            <th class="px-4 py-2 text-left">{{ __('catalogue.unit_cost') }}</th>
                        @endif
                        <th class="px-4 py-2 text-left">{{ __('catalogue.condition_flag') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @foreach ($receipt->lines as $line)
                        <tr class="{{ $line->flag === 'damaged' ? 'bg-red-50' : '' }}">
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ $line->product?->brand }}</td>
                            <td class="px-4 py-3 font-mono text-gray-600">{{ $line->batch_no }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $line->manufacturing_date?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $line->expiry_date->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 font-bold text-gray-900">{{ $line->quantity }}</td>
                            @if ($canViewCost)
                                <td class="px-4 py-3 text-gray-700">
                                    {{ $line->unit_cost ? number_format($line->unit_cost / 100, 2) . ' ETB' : '—' }}
                                </td>
                            @endif
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold
                                    @if($line->flag === 'damaged') bg-red-100 text-red-800
                                    @elseif($line->flag === 'short') bg-amber-100 text-amber-800
                                    @elseif($line->flag === 'over') bg-purple-100 text-purple-800
                                    @else bg-green-100 text-green-800 @endif">
                                    {{ strtoupper($line->flag) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

