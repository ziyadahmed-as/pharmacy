<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-900">{{ __('nav.audit') }}</h1>
        @if ($canExport)
            <div class="flex gap-2">
                <a href="{{ route('audit.export', ['format' => 'csv']) }}?{{ $exportQuery }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('common.export_csv') }}</a>
                <a href="{{ route('audit.export', ['format' => 'pdf']) }}?{{ $exportQuery }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('common.export_pdf') }}</a>
            </div>
        @endif
    </div>
    @if (! $viewer->hasPermission('audit.view'))<p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">{{ __('audit.technical_only') }}</p>@endif

    <div class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-3 lg:grid-cols-6">
        <div><label for="user" class="block text-xs font-medium text-slate-600">{{ __('audit.user') }}</label><select id="user" wire:model.live="user" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"><option value="">{{ __('common.all') }}</option>@foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->username }}</option>@endforeach</select></div>
        <div><label for="role" class="block text-xs font-medium text-slate-600">{{ __('audit.role_used') }}</label><select id="role" wire:model.live="role" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"><option value="">{{ __('common.all') }}</option>@foreach (\App\Enums\Role::cases() as $r)<option value="{{ $r->value }}">{{ $r->label() }}</option>@endforeach</select></div>
        <div><label for="action" class="block text-xs font-medium text-slate-600">{{ __('audit.action') }}</label><input id="action" wire:model.live.debounce.400ms="action" placeholder="auth.login" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"></div>
        <div><label for="from" class="block text-xs font-medium text-slate-600">{{ __('audit.from') }}</label><input id="from" type="date" wire:model.live="from" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"></div>
        <div><label for="to" class="block text-xs font-medium text-slate-600">{{ __('audit.to') }}</label><input id="to" type="date" wire:model.live="to" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"></div>
        <div><label for="subject" class="block text-xs font-medium text-slate-600">{{ __('audit.subject') }}</label><input id="subject" wire:model.live.debounce.400ms="subject" placeholder="User" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"></div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-xs">
            <thead class="bg-slate-50 text-left uppercase tracking-wide text-slate-500"><tr><th class="px-3 py-2">{{ __('common.time') }}</th><th class="px-3 py-2">{{ __('audit.user') }}</th><th class="px-3 py-2">{{ __('audit.role_used') }}</th><th class="px-3 py-2">{{ __('audit.action') }}</th><th class="px-3 py-2">{{ __('audit.terminal') }}</th><th class="px-3 py-2">{{ __('audit.old') }}</th><th class="px-3 py-2">{{ __('audit.new') }}</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($logs as $log)
                    @php
                        $old = \App\Services\AuditLogQuery::visibleValues($log->old_values, $log->action, $viewer);
                        $new = \App\Services\AuditLogQuery::visibleValues($log->new_values, $log->action, $viewer);
                    @endphp
                    <tr wire:key="a-{{ $log->id }}">
                        <td class="whitespace-nowrap px-3 py-2">{{ local_time($log->created_at, 'Y-m-d H:i:s') }}</td>
                        <td class="px-3 py-2">{{ $log->user?->username ?: '—' }}</td>
                        <td class="px-3 py-2">{{ $log->role_used ? __('roles.'.$log->role_used) : '—' }}</td>
                        <td class="px-3 py-2 font-mono">{{ $log->action }}</td>
                        <td class="max-w-[12rem] truncate px-3 py-2" title="{{ $log->terminal }}">{{ $log->terminal }}</td>
                        <td class="max-w-[14rem] truncate px-3 py-2 font-mono">{{ $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : '' }}</td>
                        <td class="max-w-[14rem] truncate px-3 py-2 font-mono">{{ $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : '' }}</td>
                        <td class="px-3 py-2"><button type="button" wire:click="showDetail({{ $log->id }})" class="text-teal-700 underline">{{ __('common.details') }}</button></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">{{ __('common.no_results') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}

    @if ($detail)
        @php
            $dOld = \App\Services\AuditLogQuery::visibleValues($detail->old_values, $detail->action, $viewer);
            $dNew = \App\Services\AuditLogQuery::visibleValues($detail->new_values, $detail->action, $viewer);
        @endphp
        <aside class="fixed inset-y-0 right-0 z-40 w-full max-w-md overflow-y-auto border-l border-slate-200 bg-white p-6 shadow-2xl" aria-label="{{ __('common.details') }}">
            <div class="flex items-start justify-between"><h2 class="font-mono text-sm font-semibold">{{ $detail->action }}</h2><button type="button" wire:click="closeDetail" class="text-slate-500">{{ __('common.close') }}</button></div>
            <dl class="mt-4 space-y-3 text-sm">
                <div><dt class="text-slate-500">{{ __('common.time') }}</dt><dd>{{ local_time($detail->created_at, 'Y-m-d H:i:s') }}</dd></div>
                <div><dt class="text-slate-500">{{ __('audit.user') }}</dt><dd>{{ $detail->user?->username ?: '—' }} ({{ $detail->role_used ? __('roles.'.$detail->role_used) : '—' }})</dd></div>
                <div><dt class="text-slate-500">{{ __('audit.terminal') }}</dt><dd class="break-all">{{ $detail->terminal }}</dd></div>
                <div><dt class="text-slate-500">{{ __('audit.subject') }}</dt><dd>{{ $detail->auditable_type ? class_basename($detail->auditable_type).' #'.$detail->auditable_id : '—' }}</dd></div>
                <div><dt class="text-slate-500">{{ __('audit.old') }}</dt><dd><pre class="overflow-x-auto rounded bg-slate-50 p-2 text-xs">{{ $dOld ? json_encode($dOld, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '—' }}</pre></dd></div>
                <div><dt class="text-slate-500">{{ __('audit.new') }}</dt><dd><pre class="overflow-x-auto rounded bg-slate-50 p-2 text-xs">{{ $dNew ? json_encode($dNew, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '—' }}</pre></dd></div>
            </dl>
        </aside>
    @endif
</div>
