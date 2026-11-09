<div class="space-y-4">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('nav.approvals') }}</h1>
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-200">
        @if ($canProcess)
            <button type="button" wire:click="setTab('pending')" @class(['px-4 py-2 text-sm font-medium', 'border-b-2 border-teal-600 text-teal-700' => $tab === 'pending', 'text-slate-500' => $tab !== 'pending'])>{{ __('approvals.tab_pending') }}</button>
        @endif
        <button type="button" wire:click="setTab('mine')" @class(['px-4 py-2 text-sm font-medium', 'border-b-2 border-teal-600 text-teal-700' => $tab === 'mine', 'text-slate-500' => $tab !== 'mine'])>{{ __('approvals.tab_mine') }}</button>
        <div class="ml-auto pb-1">
            <label for="type" class="sr-only">{{ __('approvals.type') }}</label>
            <select id="type" wire:model.live="type" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                <option value="">{{ __('common.all') }}</option>
                @foreach ($types as $t) <option value="{{ $t }}">{{ $t }}</option> @endforeach
            </select>
        </div>
    </div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr>
                <th class="px-4 py-3">{{ __('approvals.summary') }}</th><th class="px-4 py-3">{{ __('approvals.initiator') }}</th><th class="px-4 py-3">{{ __('common.status') }}</th><th class="px-4 py-3">{{ __('approvals.expires') }}</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($requests as $r)
                    <tr wire:key="r-{{ $r->id }}">
                        <td class="px-4 py-3 text-slate-900">{{ $r->summary }}<div class="text-xs text-slate-400">{{ $r->type }}</div></td>
                        <td class="px-4 py-3">{{ $r->initiator?->name }}</td>
                        <td class="px-4 py-3">{{ __('approvals.status.'.$r->status) }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ local_time($r->expires_at) }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('approvals.show', $r) }}" class="text-teal-700 underline">{{ __('common.view') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">{{ __('approvals.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $requests->links() }}
</div>
