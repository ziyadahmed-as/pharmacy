<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('nav.approvals') }}</h1>
            <p class="mt-1 text-sm text-slate-500">Manage and process dual-authorization workflow requests</p>
        </div>
        <div>
            <label for="type" class="sr-only">{{ __('approvals.type') }}</label>
            <select id="type" wire:model.live="type" class="rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                <option value="">{{ __('common.all') }}</option>
                @foreach ($types as $t) <option value="{{ $t }}">{{ $t }}</option> @endforeach
            </select>
        </div>
    </div>

    <div class="border-b border-slate-200">
        <nav class="-mb-px flex space-x-6">
            @if ($canProcess)
                <button type="button" wire:click="setTab('pending')"
                        class="py-3 px-1 border-b-2 font-semibold text-sm transition {{ $tab === 'pending' ? 'border-teal-600 text-teal-700' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
                    {{ __('approvals.tab_pending') }}
                </button>
            @endif
            <button type="button" wire:click="setTab('mine')"
                    class="py-3 px-1 border-b-2 font-semibold text-sm transition {{ $tab === 'mine' ? 'border-teal-600 text-teal-700' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
                {{ __('approvals.tab_mine') }}
            </button>
        </nav>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50/75 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3.5 text-left">{{ __('approvals.summary') }}</th>
                        <th class="px-5 py-3.5 text-left">{{ __('approvals.initiator') }}</th>
                        <th class="px-5 py-3.5 text-left">{{ __('common.status') }}</th>
                        <th class="px-5 py-3.5 text-left">{{ __('approvals.expires') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($requests as $r)
                        <tr wire:key="r-{{ $r->id }}" class="hover:bg-slate-50/70 transition">
                            <td class="px-5 py-3.5">
                                <div class="font-semibold text-slate-900">{{ $r->summary }}</div>
                                <div class="text-xs font-mono text-slate-400 mt-0.5">{{ $r->type }}</div>
                            </td>
                            <td class="px-5 py-3.5 text-slate-700 font-medium">{{ $r->initiator?->name }}</td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                                    @if($r->status === 'approved') bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20
                                    @elseif($r->status === 'pending') bg-amber-50 text-amber-700 ring-1 ring-amber-600/20
                                    @elseif($r->status === 'rejected') bg-rose-50 text-rose-700 ring-1 ring-rose-600/20
                                    @else bg-slate-100 text-slate-700 ring-1 ring-slate-500/20 @endif">
                                    {{ __('approvals.status.'.$r->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-slate-500">{{ local_time($r->expires_at) }}</td>
                            <td class="px-5 py-3.5 text-right">
                                <a href="{{ route('approvals.show', $r) }}" class="inline-flex items-center gap-1 text-teal-700 hover:text-teal-900 font-semibold text-xs transition">
                                    {{ __('common.view') }} &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-400 italic">
                                {{ __('approvals.empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($requests->hasPages())
            <div class="px-5 py-3 border-t border-slate-200 bg-slate-50/50">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
</div>
