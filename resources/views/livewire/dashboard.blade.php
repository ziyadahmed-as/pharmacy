<div class="space-y-6">
    {{-- Header --}}
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-bold text-slate-900">{{ __('dashboard.welcome', ['name' => $user->name]) }}</h1>
        <p class="mt-1 text-sm text-slate-500">
            {{ __('dashboard.signed_in_as', ['username' => $user->username]) }} ·
            {{ __('dashboard.acting_as') }} <span class="font-medium text-teal-700">{{ $actingRole?->label() }}</span> ·
            {{ __('dashboard.last_login') }} {{ local_time($user->last_login_at) }}
        </p>
    </div>

    {{-- Missing Required Settings Banner --}}
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

    {{-- Pending Approvals Banner --}}
    @if ($pendingApprovals > 0)
        <a href="{{ route('approvals.index') }}" class="flex items-center justify-between rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900 hover:bg-teal-100 transition shadow-sm">
            <div class="flex items-center space-x-3">
                <svg class="h-5 w-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-semibold">
                    {{ trans_choice('dashboard.pending_approvals', $pendingApprovals, ['count' => $pendingApprovals]) }}
                </span>
            </div>
            <span class="text-xs font-medium text-teal-700 underline">View queue &rarr;</span>
        </a>
    @endif

    {{-- Quick Stat Indicators --}}
    @if ($user->canAccessBusinessData())
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Products --}}
            @if ($user->hasPermission('catalogue.view'))
                <a href="{{ route('catalogue.index') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-teal-500 transition group">
                    <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ __('dashboard.stat_products') }}</dt>
                    <dd class="mt-2 text-3xl font-extrabold text-slate-900 group-hover:text-teal-600 transition">{{ number_format($stats['products_count'] ?? 0) }}</dd>
                    <p class="mt-1 text-xs text-slate-400">Catalogue items</p>
                </a>
            @endif

            {{-- Alerts --}}
            @if ($user->hasPermission('stock.view'))
                <a href="{{ route('inventory.alerts') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-amber-500 transition group">
                    <div class="flex items-center justify-between">
                        <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ __('dashboard.stat_alerts') }}</dt>
                        @if (($stats['critical_alerts_count'] ?? 0) > 0)
                            <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-rose-100 text-rose-800">
                                {{ $stats['critical_alerts_count'] }} critical
                            </span>
                        @endif
                    </div>
                    <dd class="mt-2 text-3xl font-extrabold text-amber-600 group-hover:text-amber-700 transition">{{ number_format($stats['active_alerts_count'] ?? 0) }}</dd>
                    <p class="mt-1 text-xs text-slate-400">Reorder & expiry alerts</p>
                </a>

                {{-- Quarantine --}}
                <a href="{{ route('inventory.quarantine') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-amber-500 transition group">
                    <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ __('dashboard.stat_quarantine') }}</dt>
                    <dd class="mt-2 text-3xl font-extrabold text-slate-700 group-hover:text-amber-600 transition">{{ number_format($stats['quarantine_quantity'] ?? 0) }}</dd>
                    <p class="mt-1 text-xs text-slate-400">Units in isolation</p>
                </a>
            @endif

            {{-- Pending Receipts (PM / Owner) or Stock on Hand --}}
            @if ($user->hasPermission('stock.receipt.confirm'))
                <a href="{{ route('inventory.receive') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-teal-500 transition group">
                    <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ __('dashboard.stat_pending_receipts') }}</dt>
                    <dd class="mt-2 text-3xl font-extrabold text-teal-600 group-hover:text-teal-700 transition">{{ number_format($stats['pending_receipts_count'] ?? 0) }}</dd>
                    <p class="mt-1 text-xs text-slate-400">Awaiting confirmation</p>
                </a>
            @elseif ($user->hasPermission('stock.view'))
                <a href="{{ route('inventory.stock') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-teal-500 transition group">
                    <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ __('dashboard.action_stock_on_hand') }}</dt>
                    <dd class="mt-2 text-sm font-medium text-slate-900 group-hover:text-teal-600 transition">View Batches & Stock</dd>
                    <p class="mt-1 text-xs text-slate-400">Sellable & quarantine overview</p>
                </a>
            @endif
        </div>
    @else
        {{-- System Admin Stats --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            @if ($user->hasPermission('users.view'))
                <a href="{{ route('users.index') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-indigo-500 transition group">
                    <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ __('dashboard.stat_users') }}</dt>
                    <dd class="mt-2 text-3xl font-extrabold text-slate-900 group-hover:text-indigo-600 transition">{{ number_format($stats['total_users'] ?? 0) }}</dd>
                    <p class="mt-1 text-xs text-slate-400">{{ $stats['active_users'] ?? 0 }} active accounts</p>
                </a>
            @endif
            @if ($user->hasPermission('audit.view.technical') || $user->hasPermission('audit.view'))
                <a href="{{ route('audit.index') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-indigo-500 transition group">
                    <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ __('dashboard.stat_audit_today') }}</dt>
                    <dd class="mt-2 text-3xl font-extrabold text-indigo-600 group-hover:text-indigo-700 transition">{{ number_format($stats['today_audits'] ?? 0) }}</dd>
                    <p class="mt-1 text-xs text-slate-400">Logged events today</p>
                </a>
            @endif
            @if ($user->hasPermission('settings.view'))
                <a href="{{ route('settings.index') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-indigo-500 transition group">
                    <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ __('dashboard.action_settings') }}</dt>
                    <dd class="mt-2 text-sm font-medium text-slate-900 group-hover:text-indigo-600 transition">Configure System Parameters</dd>
                    <p class="mt-1 text-xs text-slate-400">Security, inventory, and profile</p>
                </a>
            @endif
        </div>
    @endif

    {{-- Quick Action Hub --}}
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
        <h2 class="text-base font-bold text-slate-900">{{ __('dashboard.quick_actions') }}</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            @if ($user->hasPermission('catalogue.view'))
                <a href="{{ route('catalogue.index') }}" class="flex flex-col items-center justify-center p-3 rounded-lg border border-slate-200 hover:border-teal-500 hover:bg-teal-50/50 transition text-center group">
                    <svg class="h-6 w-6 text-slate-600 group-hover:text-teal-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <span class="mt-2 text-xs font-medium text-slate-700 group-hover:text-slate-900">{{ __('dashboard.action_catalogue') }}</span>
                </a>
            @endif
            @if ($user->hasPermission('stock.receive'))
                <a href="{{ route('inventory.receive') }}" class="flex flex-col items-center justify-center p-3 rounded-lg border border-slate-200 hover:border-teal-500 hover:bg-teal-50/50 transition text-center group">
                    <svg class="h-6 w-6 text-slate-600 group-hover:text-teal-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                    <span class="mt-2 text-xs font-medium text-slate-700 group-hover:text-slate-900">{{ __('dashboard.action_receive_stock') }}</span>
                </a>
            @endif
            @if ($user->hasPermission('stock.view'))
                <a href="{{ route('inventory.stock') }}" class="flex flex-col items-center justify-center p-3 rounded-lg border border-slate-200 hover:border-teal-500 hover:bg-teal-50/50 transition text-center group">
                    <svg class="h-6 w-6 text-slate-600 group-hover:text-teal-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                    <span class="mt-2 text-xs font-medium text-slate-700 group-hover:text-slate-900">{{ __('dashboard.action_stock_on_hand') }}</span>
                </a>
                <a href="{{ route('inventory.ledger') }}" class="flex flex-col items-center justify-center p-3 rounded-lg border border-slate-200 hover:border-teal-500 hover:bg-teal-50/50 transition text-center group">
                    <svg class="h-6 w-6 text-slate-600 group-hover:text-teal-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="mt-2 text-xs font-medium text-slate-700 group-hover:text-slate-900">{{ __('dashboard.action_stock_ledger') }}</span>
                </a>
                <a href="{{ route('inventory.alerts') }}" class="flex flex-col items-center justify-center p-3 rounded-lg border border-slate-200 hover:border-amber-500 hover:bg-amber-50/50 transition text-center group">
                    <svg class="h-6 w-6 text-amber-600 group-hover:text-amber-700 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <span class="mt-2 text-xs font-medium text-slate-700 group-hover:text-slate-900">{{ __('dashboard.action_stock_alerts') }}</span>
                </a>
                <a href="{{ route('inventory.quarantine') }}" class="flex flex-col items-center justify-center p-3 rounded-lg border border-slate-200 hover:border-amber-500 hover:bg-amber-50/50 transition text-center group">
                    <svg class="h-6 w-6 text-amber-600 group-hover:text-amber-700 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span class="mt-2 text-xs font-medium text-slate-700 group-hover:text-slate-900">{{ __('dashboard.action_quarantine') }}</span>
                </a>
            @endif
            @if ($user->hasPermission('users.view'))
                <a href="{{ route('users.index') }}" class="flex flex-col items-center justify-center p-3 rounded-lg border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50/50 transition text-center group">
                    <svg class="h-6 w-6 text-slate-600 group-hover:text-indigo-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <span class="mt-2 text-xs font-medium text-slate-700 group-hover:text-slate-900">{{ __('dashboard.action_manage_users') }}</span>
                </a>
            @endif
            @if ($user->hasPermission('audit.view.technical') || $user->hasPermission('audit.view'))
                <a href="{{ route('audit.index') }}" class="flex flex-col items-center justify-center p-3 rounded-lg border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50/50 transition text-center group">
                    <svg class="h-6 w-6 text-slate-600 group-hover:text-indigo-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span class="mt-2 text-xs font-medium text-slate-700 group-hover:text-slate-900">{{ __('dashboard.action_view_audit') }}</span>
                </a>
            @endif
            @if ($user->hasPermission('settings.view'))
                <a href="{{ route('settings.index') }}" class="flex flex-col items-center justify-center p-3 rounded-lg border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50/50 transition text-center group">
                    <svg class="h-6 w-6 text-slate-600 group-hover:text-indigo-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="mt-2 text-xs font-medium text-slate-700 group-hover:text-slate-900">{{ __('dashboard.action_settings') }}</span>
                </a>
            @endif
            @if ($user->hasPermission('staff.manage') || $user->hasPermission('staff.view.own'))
                <a href="{{ route('staff.index') }}" class="flex flex-col items-center justify-center p-3 rounded-lg border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50/50 transition text-center group">
                    <svg class="h-6 w-6 text-slate-600 group-hover:text-indigo-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span class="mt-2 text-xs font-medium text-slate-700 group-hover:text-slate-900">{{ __('nav.staff') }}</span>
                </a>
            @endif
        </div>
    </div>

    {{-- System Status & Capabilities --}}
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
