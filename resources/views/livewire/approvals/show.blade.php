<div class="mx-auto max-w-3xl space-y-6">
    <div class="flex items-center justify-between">
        <a href="{{ route('approvals.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-slate-900 transition">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            {{ __('common.back') }}
        </a>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold
            @if($r->status === 'approved') bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20
            @elseif($r->status === 'pending') bg-amber-50 text-amber-700 ring-1 ring-amber-600/20
            @elseif($r->status === 'rejected') bg-rose-50 text-rose-700 ring-1 ring-rose-600/20
            @else bg-slate-100 text-slate-700 ring-1 ring-slate-500/20 @endif">
            {{ __('approvals.status.'.$r->status) }}
        </span>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-6">
        <div>
            <h1 class="text-xl font-bold text-slate-900">{{ $r->summary }}</h1>
            <p class="text-xs font-mono text-slate-400 mt-0.5">{{ $r->type }}</p>
        </div>

        <dl class="grid gap-4 text-sm sm:grid-cols-2 border-t border-b border-slate-100 py-4">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('approvals.initiator') }}</dt>
                <dd class="mt-1 font-medium text-slate-800">{{ $r->initiator?->name }} ({{ $r->initiator_role ? __('roles.'.$r->initiator_role) : '—' }})</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('approvals.expires') }}</dt>
                <dd class="mt-1 font-medium text-slate-800">{{ local_time($r->expires_at) }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('approvals.reason') }}</dt>
                <dd class="mt-1 font-medium text-slate-800">{{ $r->initiator_comment ?: '—' }}</dd>
            </div>
            @if ($r->decided_at)
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('approvals.decided_by') }}</dt>
                    <dd class="mt-1 font-medium text-slate-800">{{ $r->approver?->name ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('approvals.decision_comment') }}</dt>
                    <dd class="mt-1 font-medium text-slate-800">{{ $r->decision_comment ?: '—' }}</dd>
                </div>
            @endif
        </dl>

        <div>
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('approvals.payload') }}</h2>
            <pre class="mt-2 overflow-x-auto rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs font-mono text-slate-700">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    </div>

    @error('decision')
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" role="alert">
            {{ $message }}
        </div>
    @enderror

    @if ($canDecide)
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <div>
                <label for="comment" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('approvals.decision_comment') }}</label>
                <textarea id="comment" wire:model="comment" rows="3" placeholder="Optional notes for this approval decision..." class="mt-1 w-full rounded-xl border border-slate-300 py-2.5 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition"></textarea>
                @error('comment') <p class="mt-1 text-xs text-rose-600 font-medium" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-center gap-3">
                <button type="button" wire:click="startDecision('approve')" wire:loading.attr="disabled" class="rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition">
                    ✓ {{ __('approvals.approve') }}
                </button>
                <button type="button" wire:click="startDecision('reject')" wire:loading.attr="disabled" class="rounded-xl border border-rose-200 bg-white px-5 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50 transition">
                    ✕ {{ __('approvals.reject') }}
                </button>
            </div>
        </div>
    @endif

    @if ($canCancel)
        <div>
            <button type="button" wire:click="cancelRequest" wire:confirm="{{ __('approvals.cancel_confirm') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                {{ __('approvals.cancel') }}
            </button>
        </div>
    @endif
</div>
