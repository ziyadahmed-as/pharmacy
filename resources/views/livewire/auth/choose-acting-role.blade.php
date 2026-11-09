<div class="mx-auto max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
    <h1 class="text-xl font-bold text-slate-900">{{ __('auth.choose_role_title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('auth.choose_role_help') }}</p>
    <div class="mt-5 space-y-2">
        @foreach ($roles as $role)
            <button type="button" wire:click="choose('{{ $role->value }}')" wire:key="role-{{ $role->value }}"
                    class="flex w-full items-center justify-between rounded-lg border border-slate-300 px-4 py-3 text-left text-sm font-medium text-slate-800 hover:border-teal-500 hover:bg-teal-50">
                <span>{{ $role->label() }}</span><span class="text-xs text-slate-400">{{ $role->code() }}</span>
            </button>
        @endforeach
    </div>
</div>
