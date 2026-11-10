<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('nav.users') }}</h1>
            <p class="mt-1 text-sm text-slate-500">System user accounts and access administration</p>
        </div>
        @if ($canManage)
            <button type="button" wire:click="open('create')" class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('users.create') }}
            </button>
        @endif
    </div>

    @error('reveal')
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="alert">
            {{ $message }}
        </div>
    @enderror

    @foreach ($reveals as $rv)
        <div class="flex items-center justify-between rounded-xl border border-teal-200 bg-teal-50/70 p-4 text-sm text-teal-900 shadow-sm" wire:key="rv-{{ $rv->id }}">
            <div class="flex items-center gap-2">
                <svg class="h-5 w-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                <span class="font-medium">{{ __('users.reveal_waiting') }}</span>
            </div>
            <button type="button" wire:click="revealCredential({{ $rv->id }})" class="rounded-lg bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-teal-700 transition">
                {{ __('users.reveal_button') }}
            </button>
        </div>
    @endforeach

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm flex flex-wrap items-end gap-3">
        <div>
            <label for="search" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('common.search') }}</label>
            <input id="search" type="search" wire:model.live.debounce.300ms="search" placeholder="Username, email..." class="rounded-lg border border-slate-300 py-1.5 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
        </div>
        <div>
            <label for="statusFilter" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">{{ __('common.status') }}</label>
            <select id="statusFilter" wire:model.live="statusFilter" class="rounded-lg border border-slate-300 py-1.5 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                <option value="">{{ __('common.all') }}</option>
                @foreach (['active', 'locked', 'pending_approval', 'deactivated'] as $s)
                    <option value="{{ $s }}">{{ __('users.status.'.$s) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50/75 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3.5 text-left">{{ __('users.username') }}</th>
                        <th class="px-5 py-3.5 text-left">{{ __('common.name') }}</th>
                        <th class="px-5 py-3.5 text-left">{{ __('users.roles') }}</th>
                        <th class="px-5 py-3.5 text-left">{{ __('common.status') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($users as $u)
                        <tr wire:key="u-{{ $u->id }}" class="hover:bg-slate-50/70 transition">
                            <td class="px-5 py-3.5 font-mono font-semibold text-slate-900">{{ $u->username }}</td>
                            <td class="px-5 py-3.5 text-slate-700 font-medium">{{ $u->name }}</td>
                            <td class="px-5 py-3.5 text-slate-600 text-xs">
                                <span class="inline-flex flex-wrap gap-1">
                                    {{ collect($u->roleEnums())->map(fn ($r) => $r->label())->join(', ') ?: '—' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                    @if($u->status === 'active') bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20
                                    @elseif($u->status === 'locked') bg-rose-50 text-rose-700 ring-1 ring-rose-600/20
                                    @elseif($u->status === 'pending_approval') bg-amber-50 text-amber-700 ring-1 ring-amber-600/20
                                    @else bg-slate-100 text-slate-600 ring-1 ring-slate-500/20 @endif">
                                    {{ __('users.status.'.$u->status) }}
                                </span>
                                @if (in_array($u->id, $waiting))
                                    <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">{{ __('users.waiting') }}</span>
                                @endif
                            </td>
                            <td class="space-x-3 px-5 py-3.5 text-right text-xs whitespace-nowrap">
                                @if ($canManage && ! in_array($u->id, $waiting) && $u->status !== 'pending_approval')
                                    <button type="button" class="text-teal-700 hover:text-teal-900 font-semibold transition" wire:click="open('roles', {{ $u->id }})">{{ __('users.edit_roles') }}</button>
                                    @if ($u->is_active && $u->id !== auth()->id())
                                        <button type="button" class="text-rose-600 hover:text-rose-800 font-medium transition" wire:click="open('deactivate', {{ $u->id }})">{{ __('users.deactivate') }}</button>
                                    @endif
                                    @if (! $u->is_active)
                                        <button type="button" class="text-emerald-700 hover:text-emerald-900 font-semibold transition" wire:click="open('reactivate', {{ $u->id }})">{{ __('users.reactivate') }}</button>
                                    @endif
                                @endif
                                @if ($canRecover && $u->id !== auth()->id() && $u->is_active)
                                    @if ($u->isLocked())
                                        <button type="button" class="text-amber-700 hover:text-amber-900 font-semibold transition" wire:click="open('unlock', {{ $u->id }})">{{ __('users.unlock') }}</button>
                                    @endif
                                    <button type="button" class="text-slate-600 hover:text-slate-900 font-medium transition" wire:click="open('reset', {{ $u->id }})">{{ __('users.reset_password') }}</button>
                                @endif
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
        @if ($users->hasPages())
            <div class="px-5 py-3 border-t border-slate-200 bg-slate-50/50">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    @if ($modal !== '')
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-slate-200">
                @error('approval')
                    <p class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700 font-medium" role="alert">{{ $message }}</p>
                @enderror

                @if ($modal === 'create')
                    <h2 class="text-lg font-bold text-slate-900 pb-3 border-b border-slate-100">{{ __('users.create') }}</h2>
                    <div class="mt-4 space-y-4">
                        <div>
                            <label for="staffId" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('users.pick_staff') }}</label>
                            <select id="staffId" wire:model="staffId" class="mt-1 w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                                <option value="">—</option>
                                @foreach ($availableStaff as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                            @error('staff') <p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="username" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('users.username') }}</label>
                            <input id="username" wire:model="username" class="mt-1 w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                            @error('username') <p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                        </div>
                        @include('livewire.users.partials.roles')
                    </div>
                    <div class="mt-6 flex justify-end gap-2.5 pt-4 border-t border-slate-100">
                        <button type="button" wire:click="closeModal" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">{{ __('common.cancel') }}</button>
                        <button type="button" wire:click="createAccount" class="rounded-xl bg-teal-600 px-5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition">{{ __('users.send_request') }}</button>
                    </div>
                @elseif ($modal === 'roles')
                    <h2 class="text-lg font-bold text-slate-900 pb-3 border-b border-slate-100">{{ __('users.edit_roles') }} — {{ $target?->username }}</h2>
                    <div class="mt-4 space-y-4">
                        @include('livewire.users.partials.roles')
                        <div>
                            <label for="reason" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('common.reason') }}</label>
                            <input id="reason" wire:model="reason" class="mt-1 w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-2.5 pt-4 border-t border-slate-100">
                        <button type="button" wire:click="closeModal" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">{{ __('common.cancel') }}</button>
                        <button type="button" wire:click="saveRoles" class="rounded-xl bg-teal-600 px-5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition">{{ __('users.send_request') }}</button>
                    </div>
                @elseif (in_array($modal, ['deactivate', 'reactivate']))
                    <h2 class="text-lg font-bold text-slate-900 pb-3 border-b border-slate-100">{{ __('users.'.$modal) }} — {{ $target?->username }}</h2>
                    <div class="mt-4">
                        <label for="reason" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('common.reason') }}</label>
                        <input id="reason" wire:model="reason" class="mt-1 w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                        @error('reason') <p class="mt-1 text-xs text-rose-600 font-medium" role="alert">{{ $message }}</p> @enderror
                        @error('user') <p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                    </div>
                    <div class="mt-6 flex justify-end gap-2.5 pt-4 border-t border-slate-100">
                        <button type="button" wire:click="closeModal" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">{{ __('common.cancel') }}</button>
                        <button type="button" wire:click="{{ $modal }}" class="rounded-xl bg-teal-600 px-5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition">{{ __('users.send_request') }}</button>
                    </div>
                @elseif (in_array($modal, ['unlock', 'reset']))
                    <h2 class="text-lg font-bold text-slate-900 pb-3 border-b border-slate-100">{{ __('users.'.($modal === 'unlock' ? 'unlock' : 'reset_password')) }} — {{ $target?->username }}</h2>
                    <div class="mt-4 space-y-4">
                        <label class="flex items-start gap-2.5 text-xs text-slate-700 cursor-pointer">
                            <input type="checkbox" wire:model="identityVerified" class="mt-0.5 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                            <span>{{ __('users.identity_verified') }}</span>
                        </label>
                        @error('identityVerified') <p class="text-xs text-rose-600 font-medium" role="alert">{{ $message }}</p> @enderror
                        <div>
                            <label for="note" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('users.verification_note') }}</label>
                            <textarea id="note" wire:model="note" rows="2" class="mt-1 w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition"></textarea>
                            @error('note') <p class="mt-1 text-xs text-rose-600 font-medium" role="alert">{{ $message }}</p> @enderror
                        </div>
                        @error('target') <p class="text-xs text-rose-600 font-medium" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="mt-6 flex justify-end gap-2.5 pt-4 border-t border-slate-100">
                        <button type="button" wire:click="closeModal" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">{{ __('common.cancel') }}</button>
                        <button type="button" wire:click="{{ $modal === 'unlock' ? 'unlock' : 'resetPassword' }}" class="rounded-xl bg-teal-600 px-5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition">{{ __('common.confirm') }}</button>
                    </div>
                @elseif ($modal === 'reveal')
                    <h2 class="text-lg font-bold text-slate-900 pb-3 border-b border-slate-100">{{ __('users.temp_password_title') }}</h2>
                    <p class="mt-3 text-xs text-slate-600">{{ __('users.temp_password_help', ['user' => $revealedFor]) }}</p>
                    <div class="mt-4 flex items-center gap-2" x-data="{ copied: false }">
                        <code class="flex-1 break-all rounded-xl bg-slate-100 px-4 py-2.5 font-mono text-base font-bold text-slate-900 border border-slate-200" id="tmp-pw">{{ $revealedPassword }}</code>
                        <button type="button" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition" @click="navigator.clipboard.writeText(document.getElementById('tmp-pw').innerText); copied = true" x-text="copied ? '{{ __('users.copied') }}' : '{{ __('users.copy') }}'"></button>
                    </div>
                    <div class="mt-6 flex justify-end pt-4 border-t border-slate-100">
                        <button type="button" wire:click="closeModal" class="rounded-xl bg-teal-600 px-5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-teal-700 transition">{{ __('common.close') }}</button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
