<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-900">{{ __('nav.staff') }}</h1>
        @if ($canManage)
            <a href="{{ route('staff.create') }}" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">{{ __('staff.add') }}</a>
        @endif
    </div>

    @if ($canManage)
        <div class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4">
            <div>
                <label for="search" class="block text-xs font-medium text-slate-600">{{ __('common.search') }}</label>
                <input id="search" type="search" wire:model.live.debounce.300ms="search" class="mt-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
            </div>
            <div>
                <label for="status" class="block text-xs font-medium text-slate-600">{{ __('common.status') }}</label>
                <select id="status" wire:model.live="status" class="mt-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                    <option value="">{{ __('common.all') }}</option>
                    @foreach (['active', 'on_leave', 'ended'] as $s) <option value="{{ $s }}">{{ __('staff.status.'.$s) }}</option> @endforeach
                </select>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.live="expiringOnly" class="rounded border-slate-300"> {{ __('staff.licence_expiring_filter') }}</label>
        </div>
    @endif

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr><th class="px-4 py-3">{{ __('common.name') }}</th><th class="px-4 py-3">{{ __('staff.title') }}</th><th class="px-4 py-3">{{ __('common.status') }}</th><th class="px-4 py-3">{{ __('staff.licence') }}</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($staff as $member)
                    @php
                        $lic = $member->licences->first();
                        $expired = $lic && $lic->expires_at->lt(now()->timezone(config('pms.timezone'))->startOfDay());
                        $soon = $lic && ! $expired && $lic->expires_at->toDateString() <= $limit;
                    @endphp
                    <tr wire:key="staff-{{ $member->id }}">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $member->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $member->title ?: '—' }}</td>
                        <td class="px-4 py-3">{{ __('staff.status.'.$member->status) }}</td>
                        <td class="px-4 py-3">
                            @if (! $lic) <span class="text-slate-400">{{ __('staff.no_licence') }}</span>
                            @elseif ($expired) <span class="rounded bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">{{ __('staff.licence_expired') }} · {{ $lic->expires_at->toDateString() }}</span>
                            @elseif ($soon) <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ __('staff.licence_expiring') }} · {{ $lic->expires_at->toDateString() }}</span>
                            @else <span class="text-slate-600">{{ $lic->expires_at->toDateString() }}</span> @endif
                        </td>
                        <td class="px-4 py-3 text-right"><a class="text-teal-700 underline" href="{{ route('staff.show', $member) }}">{{ __('common.view') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">{{ __('common.no_results') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $staff->links() }}
</div>
