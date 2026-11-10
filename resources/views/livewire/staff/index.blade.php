<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('nav.staff') }}</h1>
            <p class="mt-1 text-sm text-slate-500">Employee registry, credentials, and professional licences</p>
        </div>
        @if ($canManage)
            <a href="{{ route('staff.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('staff.add') }}
            </a>
        @endif
    </div>

    @if ($canManage)
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm flex flex-wrap items-end gap-3">
            <div>
                <label for="search" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('common.search') }}</label>
                <input id="search" type="search" wire:model.live.debounce.300ms="search" placeholder="Name, title..." class="rounded-lg border border-slate-300 py-1.5 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
            </div>
            <div>
                <label for="status" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('common.status') }}</label>
                <select id="status" wire:model.live="status" class="rounded-lg border border-slate-300 py-1.5 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                    <option value="">{{ __('common.all') }}</option>
                    @foreach (['active', 'on_leave', 'ended'] as $s)
                        <option value="{{ $s }}">{{ __('staff.status.'.$s) }}</option>
                    @endforeach
                </select>
            </div>
            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 pb-2 cursor-pointer">
                <input type="checkbox" wire:model.live="expiringOnly" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                <span>{{ __('staff.licence_expiring_filter') }}</span>
            </label>
        </div>
    @endif

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50/75 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3.5 text-left">{{ __('common.name') }}</th>
                        <th class="px-5 py-3.5 text-left">{{ __('staff.title') }}</th>
                        <th class="px-5 py-3.5 text-left">{{ __('common.status') }}</th>
                        <th class="px-5 py-3.5 text-left">{{ __('staff.licence') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($staff as $member)
                        @php
                            $lic = $member->licences->first();
                            $expired = $lic && $lic->expires_at->lt(now()->timezone(config('pms.timezone'))->startOfDay());
                            $soon = $lic && ! $expired && $lic->expires_at->toDateString() <= $limit;
                        @endphp
                        <tr wire:key="staff-{{ $member->id }}" class="hover:bg-slate-50/70 transition">
                            <td class="px-5 py-3.5 font-semibold text-slate-900">{{ $member->name }}</td>
                            <td class="px-5 py-3.5 text-slate-600 text-xs">{{ $member->title ?: '—' }}</td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                    @if($member->status === 'active') bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20
                                    @elseif($member->status === 'on_leave') bg-amber-50 text-amber-700 ring-1 ring-amber-600/20
                                    @else bg-slate-100 text-slate-600 ring-1 ring-slate-500/20 @endif">
                                    {{ __('staff.status.'.$member->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-xs">
                                @if (! $lic)
                                    <span class="text-slate-400 italic">{{ __('staff.no_licence') }}</span>
                                @elseif ($expired)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-rose-50 text-rose-700 ring-1 ring-rose-600/20">
                                        {{ __('staff.licence_expired') }} · {{ $lic->expires_at->toDateString() }}
                                    </span>
                                @elseif ($soon)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 ring-1 ring-amber-600/20">
                                        {{ __('staff.licence_expiring') }} · {{ $lic->expires_at->toDateString() }}
                                    </span>
                                @else
                                    <span class="text-slate-700 font-mono">{{ $lic->expires_at->toDateString() }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right text-xs">
                                <a class="text-teal-700 hover:text-teal-900 font-semibold transition" href="{{ route('staff.show', $member) }}">
                                    {{ __('common.view') }} &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-400 italic">{{ __('common.no_results') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($staff->hasPages())
            <div class="px-5 py-3 border-t border-slate-200 bg-slate-50/50">
                {{ $staff->links() }}
            </div>
        @endif
    </div>
</div>
