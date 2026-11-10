<div class="mx-auto max-w-md rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-xl">
    <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-50 text-teal-600">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
        </div>
        <div>
            <h1 class="text-lg font-bold text-slate-900">{{ __('auth.choose_role_title') }}</h1>
            <p class="text-xs text-slate-400">Context Switching</p>
        </div>
    </div>

    <p class="mt-4 text-xs text-slate-600 leading-relaxed">{{ __('auth.choose_role_help') }}</p>

    <div class="mt-5 space-y-2.5">
        @foreach ($roles as $role)
            <button type="button" wire:click="choose('{{ $role->value }}')" wire:key="role-{{ $role->value }}"
                    class="group flex w-full items-center justify-between rounded-xl border border-slate-200 p-4 text-left transition hover:border-teal-500 hover:bg-teal-50/50 shadow-xs">
                <div>
                    <span class="block text-sm font-bold text-slate-900 group-hover:text-teal-700 transition">{{ $role->label() }}</span>
                    <span class="block text-xs font-mono text-slate-400 mt-0.5">{{ $role->code() }}</span>
                </div>
                <svg class="h-5 w-5 text-slate-300 group-hover:text-teal-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        @endforeach
    </div>
</div>
