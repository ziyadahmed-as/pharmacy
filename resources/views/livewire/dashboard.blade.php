<div class="space-y-6">
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-bold text-slate-900">{{ __('dashboard.welcome', ['name' => $user->name]) }}</h1>
        <p class="mt-1 text-sm text-slate-500">
            {{ __('dashboard.signed_in_as', ['username' => $user->username]) }} ·
            {{ __('dashboard.acting_as') }} <span class="font-medium text-teal-700">{{ $actingRole?->label() }}</span> ·
            {{ __('dashboard.last_login') }} {{ local_time($user->last_login_at) }}
        </p>
    </div>

    @if (count($missingSettings) > 0)
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4" role="alert">
            <h2 class="font-semibold text-amber-900">{{ __('dashboard.settings_missing_title') }}</h2>
            <p class="mt-1 text-sm text-amber-800">{{ __('dashboard.settings_missing_body') }}</p>
            <ul class="mt-2 list-inside list-disc text-sm text-amber-900">
                @foreach ($missingSettings as $key)
                    <li wire:key="missing-{{ $key }}"><code>{{ $key }}</code></li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($pendingApprovals > 0)
        <a href="{{ route('approvals.index') }}" class="block rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900 hover:bg-teal-100">
            {{ trans_choice('dashboard.pending_approvals', $pendingApprovals, ['count' => $pendingApprovals]) }}
        </a>
    @endif

    <div class="grid gap-4 md:grid-cols-3">
        @php
            $cards = [
                ['key' => 'operations', 'allowed' => $user->canAccessBusinessData()],
                ['key' => 'management', 'allowed' => $user->hasAnyRole(['pharmacy_manager', 'owner'])],
                ['key' => 'system', 'allowed' => $user->hasRole('system_admin')],
            ];
        @endphp
        @foreach ($cards as $card)
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" wire:key="card-{{ $card['key'] }}">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-slate-900">{{ __('dashboard.card.'.$card['key'].'.title') }}</h3>
                    <span @class(['rounded px-2 py-0.5 text-xs font-medium', 'bg-emerald-100 text-emerald-700' => $card['allowed'], 'bg-slate-100 text-slate-500' => ! $card['allowed']])>
                        {{ $card['allowed'] ? __('dashboard.available') : __('dashboard.not_available') }}
                    </span>
                </div>
                <p class="mt-2 text-sm text-slate-500">{{ __('dashboard.card.'.$card['key'].'.body') }}</p>
            </div>
        @endforeach
    </div>
</div>
