<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('credits.title') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('credits.subtitle') }}</p>
        </div>
        <a href="{{ route('credits.ageing') }}" class="inline-flex items-center px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm text-sm font-medium text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
            {{ __('credits.ageing_title') }}
        </a>
    </div>

    <!-- Search -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
        <input type="text" wire:model.live.debounce.300ms="search"
               placeholder="{{ __('customers.search_placeholder') }}"
               class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500"/>
    </div>

    <!-- Accounts Table -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">{{ __('credits.customer') }}</th>
                        <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300 text-right">{{ __('credits.credit_limit') }}</th>
                        <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300 text-right">{{ __('credits.balance_outstanding') }}</th>
                        <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300 text-right">{{ __('credits.available_credit') }}</th>
                        <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">{{ __('customers.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    @forelse($accounts as $account)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">
                                {{ $account->customer->name }}
                                <span class="block text-xs text-slate-500">{{ $account->customer->phone }}</span>
                            </td>
                            <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-300">{{ $account->limitEtb() }} ETB</td>
                            <td class="px-4 py-3 text-right font-semibold {{ $account->balance > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-700 dark:text-slate-300' }}">{{ $account->balanceEtb() }} ETB</td>
                            <td class="px-4 py-3 text-right text-green-600 dark:text-green-400">{{ $account->availableCreditEtb() }} ETB</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('credits.show', $account) }}" class="text-teal-600 hover:text-teal-800 dark:text-teal-400 text-sm font-medium">{{ __('credits.view_statement') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">{{ __('credits.no_accounts_found') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-700">
            {{ $accounts->links() }}
        </div>
    </div>
</div>

