<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('common.app_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'Noto Sans Ethiopic', 'ui-sans-serif', 'system-ui', 'sans-serif'] } } } }
    </script>
    @livewireStyles
</head>
@php
    $authUser = auth()->user();
    $nav = $authUser ? \App\Support\Navigation::forUser($authUser) : [];
    $actingRole = $authUser ? \App\Services\ActingRole::for($authUser) : null;
    $idleMinutes = max(1, (int) setting('security.idle_timeout_minutes', 15));
    $unread = $authUser ? \Illuminate\Support\Facades\DB::table('notifications')->where('notifiable_type', $authUser->getMorphClass())->where('notifiable_id', $authUser->getKey())->whereNull('read_at')->count() : 0;
@endphp
<body class="h-full font-sans antialiased text-slate-800"
      x-data="idleGuard({{ $idleMinutes * 60 }}, 60)" x-init="start()" @click.window="touch()" @keydown.window="touch()">
<div class="flex min-h-full">
    {{-- Sidebar: items appear only when the user holds the permission --}}
    <aside class="hidden w-64 shrink-0 flex-col border-r border-slate-200 bg-white md:flex" aria-label="{{ __('nav.main') }}">
        <div class="flex h-16 items-center gap-3 border-b border-slate-200 px-5">
            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-600 text-white font-bold">Rx</div>
            <span class="text-lg font-bold tracking-tight text-slate-900">{{ __('common.app_short') }}</span>
        </div>
        <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4 text-sm">
            <a href="{{ route('dashboard') }}" @class(['block rounded-lg px-3 py-2 font-medium', 'bg-teal-50 text-teal-700' => request()->routeIs('dashboard'), 'text-slate-700 hover:bg-slate-50' => ! request()->routeIs('dashboard')])>{{ __('nav.dashboard') }}</a>
            @foreach ($nav as $group)
                <div>
                    <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('nav.group.'.$group['key']) }}</p>
                    @foreach ($group['items'] as $item)
                        <a href="{{ route($item['route']) }}" @class(['block rounded-lg px-3 py-2', 'bg-teal-50 font-medium text-teal-700' => request()->routeIs($item['route'].'*'), 'text-slate-700 hover:bg-slate-50' => ! request()->routeIs($item['route'].'*')])>{{ __($item['label']) }}</a>
                    @endforeach
                </div>
            @endforeach
        </nav>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-6">
            <div class="text-sm text-slate-500 md:hidden font-semibold text-slate-900">{{ __('common.app_short') }}</div>
            <div class="hidden md:block"></div>
            @if ($authUser)
                <div class="flex items-center gap-4">
                    <x-language-switcher />
                    <a href="{{ route('notifications') }}" class="relative text-sm text-slate-600 hover:text-slate-900" aria-label="{{ __('nav.notifications') }}">
                        {{ __('nav.notifications') }}
                        @if ($unread > 0)<span class="ml-1 rounded-full bg-red-600 px-1.5 py-0.5 text-xs font-semibold text-white">{{ $unread }}</span>@endif
                    </a>
                    <div class="text-right leading-tight">
                        <div class="text-sm font-semibold text-slate-900">{{ $authUser->name }}</div>
                        <div class="text-xs text-slate-500">
                            {{ $actingRole?->label() ?? __('roles.none') }}
                            @if (count($authUser->roleEnums()) > 1)
                                · <a class="text-teal-700 underline" href="{{ route('acting-role') }}">{{ __('auth.switch_role') }}</a>
                            @endif
                        </div>
                    </div>
                    <a href="{{ route('security') }}" class="text-xs text-slate-500 underline hover:text-slate-800">{{ __('nav.security') }}</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 hover:text-red-600">{{ __('auth.logout') }}</button>
                    </form>
                </div>
            @endif
        </header>

        <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">{{ session('error') }}</div>
            @endif
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    </div>
</div>

<livewire:shared.confirm-with-password />

{{-- Idle warning (UC-33 step 4): the server enforces the timeout; this only warns. --}}
<div x-show="warning" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" role="alertdialog" aria-modal="true">
    <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
        <h2 class="text-lg font-semibold text-slate-900">{{ __('auth.idle_warning_title') }}</h2>
        <p class="mt-1 text-sm text-slate-600">{{ __('auth.idle_warning_body') }} <span class="font-semibold" x-text="remaining"></span> s</p>
        <div class="mt-4 flex justify-end">
            <button type="button" @click="stay()" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">{{ __('auth.stay_signed_in') }}</button>
        </div>
    </div>
</div>
<style>[x-cloak]{display:none!important}</style>
<script>
    function idleGuard(limit, warnBefore) {
        return {
            warning: false, remaining: 0, last: Date.now(), timer: null,
            start() { this.timer = setInterval(() => this.tick(), 1000); },
            touch() { if (!this.warning) this.last = Date.now(); },
            tick() {
                const idle = Math.floor((Date.now() - this.last) / 1000);
                this.remaining = Math.max(0, limit - idle);
                this.warning = this.remaining <= warnBefore;
                if (this.remaining <= 0) { clearInterval(this.timer); window.location.href = '{{ route('login') }}'; }
            },
            stay() {
                fetch('{{ route('keep-alive') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } })
                    .then(() => { this.last = Date.now(); this.warning = false; });
            },
        };
    }
</script>
@livewireScripts
</body>
</html>
