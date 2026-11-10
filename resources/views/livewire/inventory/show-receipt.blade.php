<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $receipt->reference }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                    @if($receipt->status === 'posted') bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20
                    @elseif($receipt->status === 'pending_confirmation') bg-amber-50 text-amber-700 ring-1 ring-amber-600/20
                    @elseif($receipt->status === 'rejected') bg-rose-50 text-rose-700 ring-1 ring-rose-600/20
                    @else bg-slate-100 text-slate-700 ring-1 ring-slate-500/20 @endif">
                    {{ strtoupper(str_replace('_', ' ', $receipt->status)) }}
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1 font-medium">
                {{ __('catalogue.supplier') }}: <span class="text-slate-800 font-semibold">{{ $receipt->supplier_name }}</span> &bull; {{ __('common.date') }}: {{ $receipt->receipt_date->format('Y-m-d') }}
            </p>
        </div>
        
        <div class="flex items-center gap-3">
            @if ($receipt->status === 'pending_confirmation' && $canConfirm)
                <button type="button" wire:click="reject" wire:confirm="{{ __('catalogue.confirm_reject_receipt') }}" class="rounded-xl border border-rose-200 bg-white px-4 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50 transition">
                    {{ __('catalogue.reject_receipt') }}
                </button>
                <button type="button" wire:click="confirm" class="rounded-xl bg-teal-600 px-5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition">
                    ✓ {{ __('catalogue.confirm_receipt') }}
                </button>
            @endif
            <a href="{{ route('catalogue.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-slate-900 transition">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                {{ __('common.back_to_list') }}
            </a>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800" role="status">
            <svg class="h-5 w-5 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
            {{ session('message') }}
        </div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
        <h3 class="text-base font-bold text-slate-900">{{ __('catalogue.receipt_items') }}</h3>

        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-xs">
                <thead class="bg-slate-50/75 text-slate-500 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="px-4 py-2.5 text-left">{{ __('catalogue.product_name') }}</th>
                        <th class="px-4 py-2.5 text-left">{{ __('catalogue.batch_no') }}</th>
                        <th class="px-4 py-2.5 text-left">{{ __('catalogue.mfg_date') }}</th>
                        <th class="px-4 py-2.5 text-left">{{ __('catalogue.expiry_date') }}</th>
                        <th class="px-4 py-2.5 text-left">{{ __('catalogue.quantity') }}</th>
                        @if ($canViewCost)
                            <th class="px-4 py-2.5 text-left">{{ __('catalogue.unit_cost') }}</th>
                        @endif
                        <th class="px-4 py-2.5 text-left">{{ __('catalogue.condition_flag') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach ($receipt->lines as $line)
                        <tr class="hover:bg-slate-50/50 {{ $line->flag === 'damaged' ? 'bg-rose-50/40' : '' }}">
                            <td class="px-4 py-3 font-semibold text-slate-900">{{ $line->product?->brand }}</td>
                            <td class="px-4 py-3 font-mono text-slate-600">{{ $line->batch_no }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $line->manufacturing_date?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $line->expiry_date->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 font-bold text-slate-900">{{ number_format($line->quantity) }}</td>
                            @if ($canViewCost)
                                <td class="px-4 py-3 text-slate-700 font-mono">
                                    {{ $line->unit_cost ? number_format($line->unit_cost / 100, 2) . ' ETB' : '—' }}
                                </td>
                            @endif
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                    @if($line->flag === 'damaged') bg-rose-50 text-rose-700 ring-1 ring-rose-600/20
                                    @elseif($line->flag === 'short') bg-amber-50 text-amber-700 ring-1 ring-amber-600/20
                                    @elseif($line->flag === 'over') bg-purple-50 text-purple-700 ring-1 ring-purple-600/20
                                    @else bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20 @endif">
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
