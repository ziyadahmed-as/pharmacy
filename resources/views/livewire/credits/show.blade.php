<div class="space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('credits.index') }}" class="text-teal-600 hover:text-teal-800 dark:text-teal-400 text-sm">{{ __('credits.back_to_accounts') }}</a>
    </div>

    <!-- Account Summary -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white mb-4">{{ $account->customer->name }} — {{ __('credits.statement_title') }}</h1>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-4">
                <span class="text-xs text-slate-500 uppercase">{{ __('credits.credit_limit') }}</span>
                <p class="text-lg font-bold text-slate-900 dark:text-white">{{ $account->limitEtb() }} ETB</p>
            </div>
            <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-4">
                <span class="text-xs text-slate-500 uppercase">{{ __('credits.balance_outstanding') }}</span>
                <p class="text-lg font-bold {{ $account->balance > 0 ? 'text-red-600' : 'text-green-600' }}">{{ $account->balanceEtb() }} ETB</p>
            </div>
            <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-4">
                <span class="text-xs text-slate-500 uppercase">{{ __('credits.available_credit') }}</span>
                <p class="text-lg font-bold text-green-600">{{ $account->availableCreditEtb() }} ETB</p>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if($successMessage)
        <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded-lg p-4 text-green-800 dark:text-green-300 text-sm">{{ $successMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded-lg p-4 text-red-800 dark:text-red-300 text-sm">{{ $errorMessage }}</div>
    @endif

    <!-- Collect Payment Form -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">{{ __('credits.collect_payment_button') }}</h2>
        <form wire:submit="recordPayment" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('credits.payment_amount') }}</label>
                <input type="number" step="0.01" wire:model="paymentAmount" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-teal-500"/>
                @error('paymentAmount') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('credits.payment_method') }}</label>
                <select wire:model="paymentMethod" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-teal-500">
                    <option value="cash">{{ __('sales.payment_method_cash') }}</option>
                    <option value="mobile_money">{{ __('sales.payment_method_mobile_money') }}</option>
                    <option value="telebirr">{{ __('sales.payment_method_telebirr') }}</option>
                    <option value="cbe_birr">{{ __('sales.payment_method_cbe_birr') }}</option>
                    <option value="card">{{ __('sales.payment_method_card') }}</option>
                    <option value="bank_transfer">{{ __('sales.payment_method_bank_transfer') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('credits.payment_reference') }}</label>
                <input type="text" wire:model="paymentReference" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-teal-500"/>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-sm font-medium transition">{{ __('credits.record_payment') }}</button>
            </div>
        </form>
    </div>

    <!-- Transactions -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">{{ __('credits.transaction_date') }}</th>
                        <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">{{ __('credits.transaction_type') }}</th>
                        <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">{{ __('credits.receipt_no') }}</th>
                        <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">{{ __('credits.transaction_method') }}</th>
                        <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300 text-right">{{ __('credits.transaction_amount') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    @forelse($transactions as $txn)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $txn->created_at->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3">
                                @if($txn->isCharge())
                                    <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 rounded-full">{{ __('credits.charge') }}</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 rounded-full">{{ __('credits.payment') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $txn->receipt_no ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $txn->method ? ucfirst(str_replace('_', ' ', $txn->method)) : '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold {{ $txn->isCharge() ? 'text-red-600' : 'text-green-600' }}">
                                {{ $txn->isCharge() ? '+' : '-' }}{{ $txn->amountEtb() }} ETB
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">{{ __('credits.no_transactions') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-700">
            {{ $transactions->links() }}
        </div>
    </div>
</div>

