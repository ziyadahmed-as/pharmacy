<div class="mx-auto max-w-3xl space-y-5">
    <a href="{{ route('approvals.index') }}" class="text-sm text-teal-700 underline">← {{ __('common.back') }}</a>
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-start justify-between gap-3">
            <div><h1 class="text-xl font-bold text-slate-900">{{ $r->summary }}</h1><p class="text-xs text-slate-400">{{ $r->type }}</p></div>
            <span class="rounded bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">{{ __('approvals.status.'.$r->status) }}</span>
        </div>
        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
            <div><dt class="text-slate-500">{{ __('approvals.initiator') }}</dt><dd>{{ $r->initiator?->name }} ({{ $r->initiator_role ? __('roles.'.$r->initiator_role) : '—' }})</dd></div>
            <div><dt class="text-slate-500">{{ __('approvals.expires') }}</dt><dd>{{ local_time($r->expires_at) }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-slate-500">{{ __('approvals.reason') }}</dt><dd>{{ $r->initiator_comment ?: '—' }}</dd></div>
            @if ($r->decided_at)
                <div><dt class="text-slate-500">{{ __('approvals.decided_by') }}</dt><dd>{{ $r->approver?->name ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">{{ __('approvals.decision_comment') }}</dt><dd>{{ $r->decision_comment ?: '—' }}</dd></div>
            @endif
        </dl>
        <div class="mt-4">
            <h2 class="text-sm font-semibold text-slate-800">{{ __('approvals.payload') }}</h2>
            <pre class="mt-1 overflow-x-auto rounded bg-slate-50 p-3 text-xs text-slate-700">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    </div>

    @error('decision') <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $message }}</div> @enderror

    @if ($canDecide)
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <label for="comment" class="block text-sm font-medium text-slate-700">{{ __('approvals.decision_comment') }}</label>
            <textarea id="comment" wire:model="comment" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
            @error('comment') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            <div class="mt-4 flex gap-2">
                <button type="button" wire:click="startDecision('approve')" wire:loading.attr="disabled" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">{{ __('approvals.approve') }}</button>
                <button type="button" wire:click="startDecision('reject')" wire:loading.attr="disabled" class="rounded-lg border border-red-300 px-4 py-2 text-sm text-red-700 hover:bg-red-50">{{ __('approvals.reject') }}</button>
            </div>
        </div>
    @endif
    @if ($canCancel)
        <button type="button" wire:click="cancelRequest" wire:confirm="{{ __('approvals.cancel_confirm') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">{{ __('approvals.cancel') }}</button>
    @endif
</div>
