<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                {{ __('backup.dashboard_title') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('backup.dashboard_subtitle') }}
            </p>
        </div>
        <div>
            <button
                type="button"
                wire:click="runBackupNow"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 disabled:opacity-50"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
                <span wire:loading.remove>{{ __('backup.run_now') }}</span>
                <span wire:loading>{{ __('backup.running') }}...</span>
            </button>
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

    @if ($isStale)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-200">
            <strong>{{ __('backup.alert_warning') }}</strong>: {{ __('backup.stale_warning_msg') }}
        </div>
    @endif

    {{-- Metrics Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Last Success --}}
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <span class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">
                {{ __('backup.metric_last_success') }}
            </span>
            <p class="mt-2 text-lg font-bold text-slate-900 dark:text-slate-100">
                {{ $lastSuccess ? $lastSuccess->completed_at?->diffForHumans() : __('backup.never') }}
            </p>
            <p class="text-xs text-slate-400">
                {{ $lastSuccess ? $lastSuccess->completed_at?->format('Y-m-d H:i') : '-' }}
            </p>
        </div>

        {{-- Last Failure --}}
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <span class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">
                {{ __('backup.metric_last_failure') }}
            </span>
            <p class="mt-2 text-lg font-bold {{ $lastFailure ? 'text-rose-600' : 'text-slate-900 dark:text-slate-100' }}">
                {{ $lastFailure ? $lastFailure->started_at->diffForHumans() : __('backup.none') }}
            </p>
            <p class="text-xs text-slate-400">
                {{ $lastFailure ? $lastFailure->started_at->format('Y-m-d H:i') : '-' }}
            </p>
        </div>

        {{-- Archive Size --}}
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <span class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">
                {{ __('backup.metric_latest_size') }}
            </span>
            <p class="mt-2 text-lg font-bold text-slate-900 dark:text-slate-100">
                {{ $lastSuccess ? $lastSuccess->formattedSize() : '-' }}
            </p>
            <p class="text-xs text-slate-400">
                {{ __('backup.disk') }}: {{ config('backup.disk', 'backup') }}
            </p>
        </div>

        {{-- Encryption Status --}}
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <span class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">
                {{ __('backup.metric_encryption') }}
            </span>
            <p class="mt-2 text-lg font-bold text-emerald-600 dark:text-emerald-400">
                {{ __('backup.encrypted_aes') }}
            </p>
            <p class="text-xs text-slate-400">
                {{ __('backup.key_protected_server') }}
            </p>
        </div>
    </div>

    {{-- Backups Archive Table --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800">
            <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">
                {{ __('backup.archive_list_title') }}
            </h2>
        </div>
        <table class="min-w-full divide-y divide-slate-200 text-left text-sm dark:divide-slate-800">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-3">{{ __('backup.table_date') }}</th>
                    <th class="px-4 py-3">{{ __('backup.table_filename') }}</th>
                    <th class="px-4 py-3">{{ __('backup.table_type') }}</th>
                    <th class="px-4 py-3">{{ __('backup.table_size') }}</th>
                    <th class="px-4 py-3">{{ __('backup.table_checksum') }}</th>
                    <th class="px-4 py-3">{{ __('backup.table_status') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('backup.table_actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                @forelse ($backups as $b)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">
                            {{ $b->started_at->format('Y-m-d H:i') }}
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-900 dark:text-slate-100">
                            {{ $b->filename }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 uppercase text-xs font-bold text-slate-600 dark:text-slate-300">
                            {{ $b->type }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">
                            {{ $b->formattedSize() }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-[10px] text-slate-500">
                            {{ $b->checksum ? substr($b->checksum, 0, 12).'...' : '-' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            @if ($b->isSuccess())
                                <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                    {{ __('backup.status_success') }}
                                </span>
                            @elseif ($b->isFailed())
                                <span class="inline-flex rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">
                                    {{ __('backup.status_failed') }}
                                </span>
                            @else
                                <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                                    {{ __('backup.status_in_progress') }}
                                </span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            @if ($b->isSuccess())
                                <button
                                    type="button"
                                    wire:click="openRestoreModal('{{ $b->filename }}')"
                                    class="text-xs font-semibold text-amber-600 hover:text-amber-500 dark:text-amber-400"
                                >
                                    {{ __('backup.btn_request_restore') }}
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">
                            {{ __('backup.no_archives') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($backups->hasPages())
            <div class="border-t border-slate-200 p-4 dark:border-slate-800">
                {{ $backups->links() }}
            </div>
        @endif
    </div>

    {{-- Recent Restore Requests --}}
    @if ($recentRestores->isNotEmpty())
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">
                    {{ __('backup.recent_restores_title') }}
                </h2>
            </div>
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3">{{ __('backup.table_date') }}</th>
                        <th class="px-4 py-3">{{ __('backup.table_filename') }}</th>
                        <th class="px-4 py-3">{{ __('backup.table_reason') }}</th>
                        <th class="px-4 py-3">{{ __('backup.table_requested_by') }}</th>
                        <th class="px-4 py-3">{{ __('backup.table_status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @foreach ($recentRestores as $r)
                        <tr>
                            <td class="px-4 py-3 text-xs">{{ $r->created_at->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $r->backup_filename }}</td>
                            <td class="px-4 py-3 text-xs">{{ $r->reason }}</td>
                            <td class="px-4 py-3 text-xs">{{ $r->requestedBy->name }}</td>
                            <td class="px-4 py-3 text-xs font-bold uppercase">
                                {{ $r->status }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Restore Request Modal --}}
    @if ($showRestoreModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-200 pb-4 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100">
                        {{ __('backup.modal_restore_title') }}
                    </h3>
                    <button type="button" wire:click="closeRestoreModal" class="text-slate-400 hover:text-slate-500">
                        <span class="sr-only">Close</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="mt-4 rounded-lg bg-amber-50 p-3 text-xs text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    <strong>{{ __('backup.modal_restore_warning') }}</strong>: {{ __('backup.modal_restore_notice') }}
                </div>

                <form wire:submit="submitRestoreRequest" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-700 dark:text-slate-300">
                            {{ __('backup.archive_selected') }}
                        </label>
                        <input
                            type="text"
                            value="{{ $selectedFilename }}"
                            disabled
                            class="mt-1 block w-full rounded-lg border-slate-200 bg-slate-100 font-mono text-xs dark:border-slate-800 dark:bg-slate-800 text-slate-600 dark:text-slate-400"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-700 dark:text-slate-300">
                            {{ __('backup.restore_reason') }} *
                        </label>
                        <textarea
                            wire:model="restoreReason"
                            rows="3"
                            placeholder="{{ __('backup.restore_reason_placeholder') }}"
                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-500 focus:ring-amber-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                        ></textarea>
                        @error('restoreReason')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                        <button
                            type="button"
                            wire:click="closeRestoreModal"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300"
                        >
                            {{ __('backup.cancel') }}
                        </button>
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-500 disabled:opacity-50"
                        >
                            <span wire:loading.remove>{{ __('backup.submit_restore_approval') }}</span>
                            <span wire:loading>{{ __('backup.submitting') }}...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

