<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('nav.audit') }}</h1>
            <p class="mt-1 text-sm text-slate-500">Immutable forensic audit trail and security event logs</p>
        </div>
        @if ($canExport)
            <div class="flex items-center gap-2.5">
                <a href="{{ route('audit.export', ['format' => 'csv']) }}?{{ $exportQuery }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
                    <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    {{ __('common.export_csv') }}
                </a>
                <a href="{{ route('audit.export', ['format' => 'pdf']) }}?{{ $exportQuery }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
                    <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    {{ __('common.export_pdf') }}
                </a>
            </div>
        @endif
    </div>

    @if (! $viewer->hasPermission('audit.view'))
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-900 font-medium">
            {{ __('audit.technical_only') }}
        </div>
    @endif

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <div>
            <label for="user" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('audit.user') }}</label>
            <select id="user" wire:model.live="user" class="w-full rounded-lg border border-slate-300 py-1.5 px-2.5 text-xs focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                <option value="">{{ __('common.all') }}</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}">{{ $u->username }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="role" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('audit.role_used') }}</label>
            <select id="role" wire:model.live="role" class="w-full rounded-lg border border-slate-300 py-1.5 px-2.5 text-xs focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                <option value="">{{ __('common.all') }}</option>
                @foreach (\App\Enums\Role::cases() as $r)
                    <option value="{{ $r->value }}">{{ $r->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="action" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('audit.action') }}</label>
            <input id="action" wire:model.live.debounce.400ms="action" placeholder="auth.login" class="w-full rounded-lg border border-slate-300 py-1.5 px-2.5 text-xs font-mono focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
        </div>
        <div>
            <label for="from" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('audit.from') }}</label>
            <input id="from" type="date" wire:model.live="from" class="w-full rounded-lg border border-slate-300 py-1.5 px-2.5 text-xs focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
        </div>
        <div>
            <label for="to" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('audit.to') }}</label>
            <input id="to" type="date" wire:model.live="to" class="w-full rounded-lg border border-slate-300 py-1.5 px-2.5 text-xs focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
        </div>
        <div>
            <label for="subject" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('audit.subject') }}</label>
            <input id="subject" wire:model.live.debounce.400ms="subject" placeholder="User" class="w-full rounded-lg border border-slate-300 py-1.5 px-2.5 text-xs focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-xs">
                <thead class="bg-slate-50/75 text-slate-500 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="px-4 py-3 text-left">{{ __('common.time') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('audit.user') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('audit.role_used') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('audit.action') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('audit.terminal') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('audit.old') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('audit.new') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($logs as $log)
                        @php
                            $old = \App\Services\AuditLogQuery::visibleValues($log->old_values, $log->action, $viewer);
                            $new = \App\Services\AuditLogQuery::visibleValues($log->new_values, $log->action, $viewer);
                        @endphp
                        <tr wire:key="a-{{ $log->id }}" class="hover:bg-slate-50/70 transition">
                            <td class="whitespace-nowrap px-4 py-2.5 text-slate-500">{{ local_time($log->created_at, 'Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-2.5 font-medium text-slate-900">{{ $log->user?->username ?: '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $log->role_used ? __('roles.'.$log->role_used) : '—' }}</td>
                            <td class="px-4 py-2.5 font-mono text-teal-800 font-medium">{{ $log->action }}</td>
                            <td class="max-w-[12rem] truncate px-4 py-2.5 text-slate-400 font-mono text-[11px]" title="{{ $log->terminal }}">{{ $log->terminal }}</td>
                            <td class="max-w-[14rem] truncate px-4 py-2.5 font-mono text-slate-500" title="{{ $old ? json_encode($old) : '' }}">{{ $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : '' }}</td>
                            <td class="max-w-[14rem] truncate px-4 py-2.5 font-mono text-slate-700" title="{{ $new ? json_encode($new) : '' }}">{{ $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : '' }}</td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                <button type="button" wire:click="showDetail({{ $log->id }})" class="text-teal-700 hover:text-teal-900 font-semibold transition">{{ __('common.details') }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-slate-400 italic">{{ __('common.no_results') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            <div class="px-5 py-3 border-t border-slate-200 bg-slate-50/50">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

    @if ($detail)
        @php
            $dOld = \App\Services\AuditLogQuery::visibleValues($detail->old_values, $detail->action, $viewer);
            $dNew = \App\Services\AuditLogQuery::visibleValues($detail->new_values, $detail->action, $viewer);
        @endphp
        <aside class="fixed inset-y-0 right-0 z-50 w-full max-w-md overflow-y-auto border-l border-slate-200 bg-white p-6 shadow-2xl space-y-5" aria-label="{{ __('common.details') }}">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h2 class="font-mono text-sm font-bold text-teal-700">{{ $detail->action }}</h2>
                <button type="button" wire:click="closeDetail" class="text-xs text-slate-500 hover:text-slate-700 font-medium">{{ __('common.close') }} ✕</button>
            </div>
            <dl class="space-y-4 text-xs">
                <div><dt class="font-semibold uppercase tracking-wider text-slate-400">{{ __('common.time') }}</dt><dd class="mt-0.5 text-slate-800 font-medium">{{ local_time($detail->created_at, 'Y-m-d H:i:s') }}</dd></div>
                <div><dt class="font-semibold uppercase tracking-wider text-slate-400">{{ __('audit.user') }}</dt><dd class="mt-0.5 text-slate-800 font-medium">{{ $detail->user?->username ?: '—' }} ({{ $detail->role_used ? __('roles.'.$detail->role_used) : '—' }})</dd></div>
                <div><dt class="font-semibold uppercase tracking-wider text-slate-400">{{ __('audit.terminal') }}</dt><dd class="mt-0.5 break-all font-mono text-slate-600">{{ $detail->terminal }}</dd></div>
                <div><dt class="font-semibold uppercase tracking-wider text-slate-400">{{ __('audit.subject') }}</dt><dd class="mt-0.5 font-medium text-slate-800">{{ $detail->auditable_type ? class_basename($detail->auditable_type).' #'.$detail->auditable_id : '—' }}</dd></div>
                <div><dt class="font-semibold uppercase tracking-wider text-slate-400">{{ __('audit.old') }}</dt><dd class="mt-1"><pre class="overflow-x-auto rounded-xl border border-slate-200 bg-slate-50 p-3 font-mono text-slate-700">{{ $dOld ? json_encode($dOld, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '—' }}</pre></dd></div>
                <div><dt class="font-semibold uppercase tracking-wider text-slate-400">{{ __('audit.new') }}</dt><dd class="mt-1"><pre class="overflow-x-auto rounded-xl border border-slate-200 bg-slate-50 p-3 font-mono text-slate-700">{{ $dNew ? json_encode($dNew, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '—' }}</pre></dd></div>
            </dl>
        </aside>
    @endif
</div>
