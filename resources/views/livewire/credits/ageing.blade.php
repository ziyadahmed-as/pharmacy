<div class="space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('credits.index') }}" class="text-teal-600 hover:text-teal-800 dark:text-teal-400 text-sm">{{ __('credits.back_to_accounts') }}</a>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white mb-4">{{ __('credits.ageing_title') }}</h1>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">{{ __('credits.customer') }}</th>
                        <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300 text-right">{{ __('credits.current_0_30') }}</th>
                        <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300 text-right">{{ __('credits.days_31_60') }}</th>
                        <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300 text-right">{{ __('credits.days_61_90') }}</th>
                        <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300 text-right">{{ __('credits.days_90_plus') }}</th>
                        <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300 text-right">{{ __('credits.total_outstanding') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    @forelse($accounts as $row)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                            <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">
                                {{ $row['customer_name'] }}
                                <span class="block text-xs text-slate-500">{{ $row['customer_phone'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-right {{ $row['bucket'] === 'current' ? 'font-semibold text-amber-600' : 'text-slate-400' }}">
                                {{ $row['bucket'] === 'current' ? number_format($row['balance'] / 100, 2) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right {{ $row['bucket'] === '31_60' ? 'font-semibold text-orange-600' : 'text-slate-400' }}">
                                {{ $row['bucket'] === '31_60' ? number_format($row['balance'] / 100, 2) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right {{ $row['bucket'] === '61_90' ? 'font-semibold text-red-600' : 'text-slate-400' }}">
                                {{ $row['bucket'] === '61_90' ? number_format($row['balance'] / 100, 2) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right {{ $row['bucket'] === '90_plus' ? 'font-semibold text-red-800 dark:text-red-400' : 'text-slate-400' }}">
                                {{ $row['bucket'] === '90_plus' ? number_format($row['balance'] / 100, 2) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-slate-900 dark:text-white">{{ number_format($row['balance'] / 100, 2) }} ETB</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">{{ __('credits.no_accounts_found') }}</td></tr>
                    @endforelse
                </tbody>
                @if($accounts->count())
                    <tfoot class="bg-slate-50 dark:bg-slate-900/50 font-bold">
                        <tr>
                            <td class="px-4 py-3 text-slate-900 dark:text-white">{{ __('credits.total_outstanding') }}</td>
                            <td class="px-4 py-3 text-right text-amber-600">{{ number_format($totals['current'] / 100, 2) }}</td>
                            <td class="px-4 py-3 text-right text-orange-600">{{ number_format($totals['31_60'] / 100, 2) }}</td>
                            <td class="px-4 py-3 text-right text-red-600">{{ number_format($totals['61_90'] / 100, 2) }}</td>
                            <td class="px-4 py-3 text-right text-red-800 dark:text-red-400">{{ number_format($totals['90_plus'] / 100, 2) }}</td>
                            <td class="px-4 py-3 text-right text-slate-900 dark:text-white">{{ number_format($totals['total'] / 100, 2) }} ETB</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

