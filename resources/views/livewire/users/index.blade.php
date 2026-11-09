<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-900">{{ __('nav.users') }}</h1>
        @if ($canManage)<button type="button" wire:click="open('create')" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">{{ __('users.create') }}</button>@endif
    </div>

    @error('reveal') <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="alert">{{ $message }}</div> @enderror

    @foreach ($reveals as $rv)
        <div class="flex items-center justify-between rounded-lg border border-teal-200 bg-teal-50 p-3 text-sm text-teal-900" wire:key="rv-{{ $rv->id }}">
            <span>{{ __('users.reveal_waiting') }}</span>
            <button type="button" wire:click="revealCredential({{ $rv->id }})" class="rounded bg-teal-600 px-3 py-1 text-xs font-medium text-white">{{ __('users.reveal_button') }}</button>
        </div>
    @endforeach

    <div class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4">
        <div><label for="search" class="block text-xs font-medium text-slate-600">{{ __('common.search') }}</label><input id="search" type="search" wire:model.live.debounce.300ms="search" class="mt-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm"></div>
        <div><label for="statusFilter" class="block text-xs font-medium text-slate-600">{{ __('common.status') }}</label>
            <select id="statusFilter" wire:model.live="statusFilter" class="mt-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                <option value="">{{ __('common.all') }}</option>
                @foreach (['active', 'locked', 'pending_approval', 'deactivated'] as $s)<option value="{{ $s }}">{{ __('users.status.'.$s) }}</option>@endforeach
            </select></div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr>
                <th class="px-4 py-3">{{ __('users.username') }}</th><th class="px-4 py-3">{{ __('common.name') }}</th><th class="px-4 py-3">{{ __('users.roles') }}</th><th class="px-4 py-3">{{ __('common.status') }}</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($users as $u)
                    <tr wire:key="u-{{ $u->id }}">
                        <td class="px-4 py-3 font-mono text-slate-900">{{ $u->username }}</td>
                        <td class="px-4 py-3">{{ $u->name }}</td>
                        <td class="px-4 py-3">{{ collect($u->roleEnums())->map(fn ($r) => $r->label())->join(', ') ?: '—' }}</td>
                        <td class="px-4 py-3">{{ __('users.status.'.$u->status) }} @if (in_array($u->id, $waiting)) <span class="ml-1 rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-800">{{ __('users.waiting') }}</span> @endif</td>
                        <td class="space-x-3 px-4 py-3 text-right text-xs">
                            @if ($canManage && ! in_array($u->id, $waiting) && $u->status !== 'pending_approval')
                                <button type="button" class="text-teal-700 underline" wire:click="open('roles', {{ $u->id }})">{{ __('users.edit_roles') }}</button>
                                @if ($u->is_active && $u->id !== auth()->id())<button type="button" class="text-red-700 underline" wire:click="open('deactivate', {{ $u->id }})">{{ __('users.deactivate') }}</button>@endif
                                @if (! $u->is_active)<button type="button" class="text-teal-700 underline" wire:click="open('reactivate', {{ $u->id }})">{{ __('users.reactivate') }}</button>@endif
                            @endif
                            @if ($canRecover && $u->id !== auth()->id() && $u->is_active)
                                @if ($u->isLocked())<button type="button" class="text-indigo-700 underline" wire:click="open('unlock', {{ $u->id }})">{{ __('users.unlock') }}</button>@endif
                                <button type="button" class="text-indigo-700 underline" wire:click="open('reset', {{ $u->id }})">{{ __('users.reset_password') }}</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">{{ __('common.no_results') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $users->links() }}

    @if ($modal !== '')
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true">
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
                @error('approval') <p class="mb-3 rounded bg-red-50 p-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror

                @if ($modal === 'create')
                    <h2 class="text-lg font-semibold">{{ __('users.create') }}</h2>
                    <div class="mt-4 space-y-3">
                        <div><label for="staffId" class="block text-sm font-medium">{{ __('users.pick_staff') }}</label>
                            <select id="staffId" wire:model="staffId" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">—</option>@foreach ($availableStaff as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select>
                            @error('staff') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror</div>
                        <div><label for="username" class="block text-sm font-medium">{{ __('users.username') }}</label><input id="username" wire:model="username" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@error('username') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror</div>
                        @include('livewire.users.partials.roles')
                    </div>
                    <div class="mt-5 flex justify-end gap-2"><button type="button" wire:click="closeModal" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">{{ __('common.cancel') }}</button><button type="button" wire:click="createAccount" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white">{{ __('users.send_request') }}</button></div>
                @elseif ($modal === 'roles')
                    <h2 class="text-lg font-semibold">{{ __('users.edit_roles') }} — {{ $target?->username }}</h2>
                    <div class="mt-4 space-y-3">@include('livewire.users.partials.roles')
                        <div><label for="reason" class="block text-sm font-medium">{{ __('common.reason') }}</label><input id="reason" wire:model="reason" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div></div>
                    <div class="mt-5 flex justify-end gap-2"><button type="button" wire:click="closeModal" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">{{ __('common.cancel') }}</button><button type="button" wire:click="saveRoles" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white">{{ __('users.send_request') }}</button></div>
                @elseif (in_array($modal, ['deactivate', 'reactivate']))
                    <h2 class="text-lg font-semibold">{{ __('users.'.$modal) }} — {{ $target?->username }}</h2>
                    <div class="mt-4"><label for="reason" class="block text-sm font-medium">{{ __('common.reason') }}</label><input id="reason" wire:model="reason" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@error('reason') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror @error('user') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror</div>
                    <div class="mt-5 flex justify-end gap-2"><button type="button" wire:click="closeModal" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">{{ __('common.cancel') }}</button><button type="button" wire:click="{{ $modal }}" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white">{{ __('users.send_request') }}</button></div>
                @elseif (in_array($modal, ['unlock', 'reset']))
                    <h2 class="text-lg font-semibold">{{ __('users.'.($modal === 'unlock' ? 'unlock' : 'reset_password')) }} — {{ $target?->username }}</h2>
                    <div class="mt-4 space-y-3">
                        <label class="flex items-start gap-2 text-sm"><input type="checkbox" wire:model="identityVerified" class="mt-1 rounded border-slate-300"> <span>{{ __('users.identity_verified') }}</span></label>
                        @error('identityVerified') <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                        <div><label for="note" class="block text-sm font-medium">{{ __('users.verification_note') }}</label><textarea id="note" wire:model="note" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>@error('note') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror</div>
                        @error('target') <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="mt-5 flex justify-end gap-2"><button type="button" wire:click="closeModal" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">{{ __('common.cancel') }}</button><button type="button" wire:click="{{ $modal === 'unlock' ? 'unlock' : 'resetPassword' }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">{{ __('common.confirm') }}</button></div>
                @elseif ($modal === 'reveal')
                    <h2 class="text-lg font-semibold">{{ __('users.temp_password_title') }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ __('users.temp_password_help', ['user' => $revealedFor]) }}</p>
                    <div class="mt-4 flex items-center gap-2" x-data="{ copied: false }">
                        <code class="flex-1 break-all rounded bg-slate-100 px-3 py-2 font-mono text-lg" id="tmp-pw">{{ $revealedPassword }}</code>
                        <button type="button" class="rounded border border-slate-300 px-3 py-2 text-sm" @click="navigator.clipboard.writeText(document.getElementById('tmp-pw').innerText); copied = true" x-text="copied ? '{{ __('users.copied') }}' : '{{ __('users.copy') }}'"></button>
                    </div>
                    <div class="mt-5 flex justify-end"><button type="button" wire:click="closeModal" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white">{{ __('common.close') }}</button></div>
                @endif
            </div>
        </div>
    @endif
</div>
