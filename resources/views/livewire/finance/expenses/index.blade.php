<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                {{ __('expense.title') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('expense.subtitle') }}
            </p>
        </div>
        <div>
            @can('finance.expense.record')
                <button
                    type="button"
                    wire:click="openCreateModal"
                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('expense.record_expense') }}
                </button>
            @endcan
        </div>
    </div>

    @if ($successMessage)
        <div class="rounded-md bg-emerald-50 p-4 dark:bg-emerald-900/30">
            <p class="text-sm font-medium text-emerald-800 dark:text-emerald-200">
                {{ $successMessage }}
            </p>
        </div>
    @endif

    @if ($errorMessage)
        <div class="rounded-md bg-rose-50 p-4 dark:bg-rose-900/30">
            <p class="text-sm font-medium text-rose-800 dark:text-rose-200">
                {{ $errorMessage }}
            </p>
        </div>
    @endif

    {{-- Filters --}}
    <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center dark:border-slate-800 dark:bg-slate-900">
        <div class="flex-1">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('expense.description') }}..."
                class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
            />
        </div>
        <div class="w-full sm:w-44">
            <select
                wire:model.live="categoryFilter"
                class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
            >
                <option value="">{{ __('expense.filter_category') }}</option>
                <option value="rent">{{ __('expense.category_rent') }}</option>
                <option value="salaries">{{ __('expense.category_salaries') }}</option>
                <option value="utilities">{{ __('expense.category_utilities') }}</option>
                <option value="transport">{{ __('expense.category_transport') }}</option>
                <option value="other">{{ __('expense.category_other') }}</option>
            </select>
        </div>
        <div class="w-full sm:w-44">
            <select
                wire:model.live="statusFilter"
                class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
            >
                <option value="">{{ __('expense.filter_status') }}</option>
                <option value="posted">{{ __('expense.status_posted') }}</option>
                <option value="pending_approval">{{ __('expense.status_pending_approval') }}</option>
                <option value="rejected">{{ __('expense.status_rejected') }}</option>
            </select>
        </div>
        <div class="w-full sm:w-36">
            <input
                type="date"
                wire:model.live="fromDate"
                title="{{ __('expense.filter_from') }}"
                class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
            />
        </div>
        <div class="w-full sm:w-36">
            <input
                type="date"
                wire:model.live="toDate"
                title="{{ __('expense.filter_to') }}"
                class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
            />
        </div>
    </div>

    {{-- Expenses Table --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <table class="min-w-full divide-y divide-slate-200 text-left text-sm dark:divide-slate-800">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-3">{{ __('expense.date') }}</th>
                    <th class="px-4 py-3">{{ __('expense.category') }}</th>
                    <th class="px-4 py-3">{{ __('expense.description') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('expense.amount') }}</th>
                    <th class="px-4 py-3">{{ __('expense.recorded_by') }}</th>
                    <th class="px-4 py-3">{{ __('expense.status') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('expense.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                @forelse ($expenses as $expense)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">
                            {{ $expense->date->format('Y-m-d') }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-900 dark:text-slate-100">
                            {{ $expense->categoryLabel() }}
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                            <div>{{ $expense->description }}</div>
                            @if ($expense->reversal_of_id)
                                <div class="text-xs text-rose-600 dark:text-rose-400">
                                    {{ __('expense.reversal') }}: {{ $expense->reversal_reason }}
                                </div>
                            @elseif ($expense->reversals->isNotEmpty())
                                <div class="text-xs text-amber-600 dark:text-amber-400">
                                    {{ __('expense.reversed') }}
                                </div>
                            @endif
                            @if ($expense->proof_path)
                                <div class="mt-1">
                                    <a
                                        href="{{ route('finance.expenses.proof', $expense) }}"
                                        target="_blank"
                                        class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:underline dark:text-indigo-400"
                                    >
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                        </svg>
                                        {{ __('expense.view_proof') }}
                                    </a>
                                </div>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-medium {{ $expense->amount < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-slate-100' }}">
                            {{ $expense->formattedAmount() }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-400">
                            {{ $expense->creator?->name }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            @if ($expense->isPosted())
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                    {{ $expense->statusLabel() }}
                                </span>
                            @elseif ($expense->isPendingApproval())
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                    {{ $expense->statusLabel() }}
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-medium text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">
                                    {{ $expense->statusLabel() }}
                                </span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            @can('finance.expense.record')
                                @if ($expense->isPosted() && ! $expense->isReversal() && $expense->reversals->isEmpty())
                                    <button
                                        type="button"
                                        wire:click="openReverseModal({{ $expense->id }})"
                                        class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-950/40"
                                    >
                                        {{ __('expense.reverse') }}
                                    </button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
                            {{ __('expense.no_expenses') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $expenses->links() }}
    </div>

    {{-- Create Modal --}}
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 backdrop-blur-sm">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100">
                            {{ __('expense.new_expense') }}
                        </h3>
                        <button
                            type="button"
                            wire:click="closeCreateModal"
                            class="text-slate-400 hover:text-slate-500"
                        >
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form wire:submit="saveExpense" class="mt-4 space-y-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                {{ __('expense.category') }} *
                            </label>
                            <select
                                wire:model="category"
                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                            >
                                <option value="rent">{{ __('expense.category_rent') }}</option>
                                <option value="salaries">{{ __('expense.category_salaries') }}</option>
                                <option value="utilities">{{ __('expense.category_utilities') }}</option>
                                <option value="transport">{{ __('expense.category_transport') }}</option>
                                <option value="other">{{ __('expense.category_other') }}</option>
                            </select>
                            @error('category')
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                    {{ __('expense.amount') }} *
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    wire:model="amount"
                                    placeholder="0.00"
                                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                />
                                @error('amount')
                                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                    {{ __('expense.date') }} *
                                </label>
                                <input
                                    type="date"
                                    wire:model="date"
                                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                />
                                @error('date')
                                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                {{ __('expense.description') }} *
                            </label>
                            <textarea
                                wire:model="description"
                                rows="3"
                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                            ></textarea>
                            @error('description')
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                {{ __('expense.proof') }}
                            </label>
                            <input
                                type="file"
                                wire:model="proofFile"
                                accept=".jpg,.jpeg,.png,.pdf"
                                class="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-slate-700 hover:file:bg-slate-200 dark:file:bg-slate-800 dark:file:text-slate-300"
                            />
                            @error('proofFile')
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                            <button
                                type="button"
                                wire:click="closeCreateModal"
                                class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                            >
                                {{ __('expense.cancel') }}
                            </button>
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 disabled:opacity-50"
                            >
                                <span wire:loading.remove>{{ __('expense.submit') }}</span>
                                <span wire:loading>{{ __('expense.saving') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Reversal Modal --}}
    @if ($showReverseModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 backdrop-blur-sm">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100">
                            {{ __('expense.reverse_modal_title') }}
                        </h3>
                        <button
                            type="button"
                            wire:click="closeReverseModal"
                            class="text-slate-400 hover:text-slate-500"
                        >
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                        {{ __('expense.reverse_modal_desc') }}
                    </p>

                    <form wire:submit="confirmReverse" class="mt-4 space-y-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                {{ __('expense.reversal_reason') }} *
                            </label>
                            <textarea
                                wire:model="reversalReason"
                                rows="3"
                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-rose-500 focus:ring-rose-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                            ></textarea>
                            @error('reversalReason')
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                            <button
                                type="button"
                                wire:click="closeReverseModal"
                                class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                            >
                                {{ __('expense.cancel') }}
                            </button>
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-500 disabled:opacity-50"
                            >
                                {{ __('expense.confirm_reversal') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
