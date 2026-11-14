<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('reminders.title') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('reminders.subtitle') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <button
                type="button"
                wire:click="checkDue"
                class="inline-flex items-center px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm text-sm font-medium text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                <svg class="-ml-1 mr-2 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                {{ __('reminders.check_due_button') }}
            </button>
        </div>
    </div>

    {{-- Alert Messages --}}
    @if ($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 dark:bg-emerald-900/30 p-4 text-sm font-medium text-emerald-800 dark:text-emerald-200 flex items-center justify-between">
            <span>{{ $statusMessage }}</span>
            <button wire:click="$set('statusMessage', null)" class="text-emerald-500 hover:text-emerald-700">✕</button>
        </div>
    @endif

    @if ($errorMessage)
        <div class="rounded-xl border border-rose-200 bg-rose-50 dark:bg-rose-900/30 p-4 text-sm font-medium text-rose-800 dark:text-rose-200 flex items-center justify-between">
            <span>{{ $errorMessage }}</span>
            <button wire:click="$set('errorMessage', null)" class="text-rose-500 hover:text-rose-700">✕</button>
        </div>
    @endif

    {{-- Tabs & Batch Action Bar --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 dark:border-slate-700 pb-2">
        <div class="flex space-x-4">
            <button
                type="button"
                wire:click="setTab('due')"
                class="pb-2 text-sm font-medium border-b-2 transition flex items-center gap-2 {{ $activeTab === 'due' ? 'border-teal-500 text-teal-600 dark:text-teal-400' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                <span>{{ __('reminders.tab_due') }}</span>
                <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $activeTab === 'due' ? 'bg-teal-100 text-teal-800 dark:bg-teal-900/50 dark:text-teal-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' }}">
                    {{ $counts['due'] }}
                </span>
            </button>

            <button
                type="button"
                wire:click="setTab('failed')"
                class="pb-2 text-sm font-medium border-b-2 transition flex items-center gap-2 {{ $activeTab === 'failed' ? 'border-rose-500 text-rose-600 dark:text-rose-400' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                <span>{{ __('reminders.tab_failed') }}</span>
                <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $activeTab === 'failed' ? 'bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' }}">
                    {{ $counts['failed'] }}
                </span>
            </button>

            <button
                type="button"
                wire:click="setTab('sent')"
                class="pb-2 text-sm font-medium border-b-2 transition flex items-center gap-2 {{ $activeTab === 'sent' ? 'border-teal-500 text-teal-600 dark:text-teal-400' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                <span>{{ __('reminders.tab_sent') }}</span>
                <span class="rounded-full px-2 py-0.5 text-xs font-bold bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                    {{ $counts['sent'] }}
                </span>
            </button>
        </div>

        @if ($activeTab === 'due' && count($selectedReminders) > 0)
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    wire:click="sendSelected"
                    class="inline-flex items-center px-3.5 py-1.5 rounded-lg text-xs font-bold text-white bg-teal-600 hover:bg-teal-700 transition shadow-sm">
                    {{ __('reminders.send_selected_button', ['count' => count($selectedReminders)]) }}
                </button>
            </div>
        @endif
    </div>

    {{-- Search Bar --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="{{ __('reminders.search_placeholder') }}"
            class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-teal-500"
        />
    </div>

    {{-- Reminders Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        @if ($activeTab === 'due')
                            <th class="px-4 py-3 w-10">
                                <input type="checkbox" wire:model.live="selectAll" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                            </th>
                        @endif
                        <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">{{ __('reminders.customer') }}</th>
                        <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">{{ __('reminders.prescription_no') }}</th>
                        <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">{{ __('reminders.due_date') }}</th>
                        <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">{{ __('reminders.status') }}</th>
                        @if ($activeTab === 'failed')
                            <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300 text-rose-600">{{ __('reminders.error_reason') }}</th>
                        @endif
                        @if ($activeTab === 'sent')
                            <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">{{ __('reminders.sent_at') }}</th>
                        @endif
                        <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300 text-right">{{ __('reminders.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    @forelse ($reminders as $reminder)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            @if ($activeTab === 'due')
                                <td class="px-4 py-3">
                                    <input type="checkbox" wire:model.live="selectedReminders" value="{{ $reminder->id }}" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                                </td>
                            @endif
                            <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">
                                {{ $reminder->customer->name }}
                                <span class="block text-xs font-mono text-slate-500">{{ $reminder->customer->phone }}</span>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-300">
                                {{ $reminder->prescriptionLine?->prescription?->prescription_number ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300 font-mono text-xs">
                                {{ $reminder->due_date->format('d M Y') }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($reminder->isQueued())
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 border border-amber-200">
                                        {{ __('reminders.status_queued') }}
                                    </span>
                                @elseif ($reminder->isSent())
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300 border border-emerald-200">
                                        {{ __('reminders.status_sent') }}
                                    </span>
                                @elseif ($reminder->isFailed())
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-300 border border-rose-200">
                                        {{ __('reminders.status_failed') }} ({{ $reminder->attempts }})
                                    </span>
                                @elseif ($reminder->isCalled())
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-300 border border-sky-200">
                                        {{ __('reminders.status_called') }}
                                    </span>
                                @endif
                            </td>
                            @if ($activeTab === 'failed')
                                <td class="px-4 py-3 text-xs text-rose-600 dark:text-rose-400 font-mono">
                                    {{ $reminder->error_message ?? '—' }}
                                </td>
                            @endif
                            @if ($activeTab === 'sent')
                                <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300 font-mono">
                                    {{ $reminder->sent_at?->format('d M Y H:i') ?? '—' }}
                                </td>
                            @endif
                            <td class="px-4 py-3 text-right">
                                @if ($reminder->isQueued())
                                    <button
                                        type="button"
                                        wire:click="sendSingle({{ $reminder->id }})"
                                        class="px-2.5 py-1 text-xs font-medium text-white bg-teal-600 hover:bg-teal-700 rounded-lg transition shadow-sm">
                                        {{ __('reminders.send_button') }}
                                    </button>
                                @elseif ($reminder->isFailed())
                                    <div class="inline-flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            wire:click="sendSingle({{ $reminder->id }})"
                                            class="px-2.5 py-1 text-xs font-medium text-white bg-rose-600 hover:bg-rose-700 rounded-lg transition shadow-sm">
                                            {{ __('reminders.retry_button') }}
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="markAsCalled({{ $reminder->id }})"
                                            class="px-2.5 py-1 text-xs font-medium text-sky-700 bg-sky-100 hover:bg-sky-200 rounded-lg transition">
                                            {{ __('reminders.mark_as_called_button') }}
                                        </button>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400">✓ {{ __('reminders.completed') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $activeTab === 'failed' || $activeTab === 'sent' ? 7 : 6 }}" class="px-4 py-8 text-center text-slate-400">
                                {{ __('reminders.no_reminders_found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-700">
            {{ $reminders->links() }}
        </div>
    </div>
</div>

