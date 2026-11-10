<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('common.app_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'Noto Sans Ethiopic', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                    colors: {
                        brand: { 50:'#f0fdfa', 100:'#ccfbf1', 200:'#99f6e4', 500:'#14b8a6', 600:'#0d9488', 700:'#0f766e', 800:'#115e59', 900:'#134e4a' }
                    }
                }
            }
        }
    </script>
    @livewireStyles
</head>
@php
    $authUser = auth()->user();
    $nav = $authUser ? \App\Support\Navigation::forUser($authUser) : [];
    $actingRole = $authUser ? \App\Services\ActingRole::for($authUser) : null;
    $idleMinutes = max(1, (int) setting('security.idle_timeout_minutes', 15));
    $unread = $authUser ? \Illuminate\Support\Facades\DB::table('notifications')->where('notifiable_type', $authUser->getMorphClass())->where('notifiable_id', $authUser->getKey())->whereNull('read_at')->count() : 0;
    $initials = $authUser ? collect(explode(' ', $authUser->name))->map(fn($w) => strtoupper(substr($w,0,1)))->take(2)->implode('') : '?';
    $currentShift = $authUser ? \App\Services\CurrentShift::forUser($authUser) : null;
@endphp
<body class="h-full font-sans antialiased text-slate-800"
      x-data="{ sidebarOpen: false, userMenuOpen: false, ...idleGuard({{ $idleMinutes * 60 }}, 60) }"
      x-init="start()"
      @click.window="touch(); userMenuOpen = false"
      @keydown.window="touch()">

<div class="flex h-full">
    {{-- ========== SIDEBAR ========== --}}
    {{-- Mobile overlay --}}
    <div x-show="sidebarOpen" x-cloak class="fixed inset-0 z-40 bg-slate-900/50 md:hidden" @click="sidebarOpen = false"></div>

    <aside
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
        class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-slate-200 bg-white shadow-xl transition-transform duration-300 md:static md:z-auto md:shadow-none"
        aria-label="{{ __('nav.main') }}">

        {{-- Brand --}}
        <div class="flex h-16 shrink-0 items-center gap-3 border-b border-slate-100 px-5">
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-teal-500 to-teal-700 text-white font-bold text-sm shadow-sm">Rx</div>
            <div>
                <div class="text-sm font-bold tracking-tight text-slate-900 leading-tight">{{ __('common.app_short') }}</div>
                <div class="text-xs text-slate-400 leading-tight">PMS v1.0</div>
            </div>
            {{-- Close btn (mobile) --}}
            <button @click="sidebarOpen = false" class="ml-auto -mr-1 rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 md:hidden">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4 text-sm">
            {{-- Dashboard link --}}
            <a href="{{ route('dashboard') }}"
               @class([
                   'flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition',
                   'bg-teal-50 text-teal-700 border-l-2 border-teal-600' => request()->routeIs('dashboard'),
                   'text-slate-600 hover:bg-slate-50 hover:text-slate-900 border-l-2 border-transparent' => ! request()->routeIs('dashboard'),
               ])>
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                {{ __('nav.dashboard') }}
            </a>

            @foreach ($nav as $group)
                <div class="pt-4">
                    <p class="px-3 pb-1.5 text-xs font-semibold uppercase tracking-widest text-slate-400">
                        {{ __('nav.group.'.$group['key']) }}
                    </p>
                    @foreach ($group['items'] as $item)
                        <a href="{{ route($item['route']) }}"
                           @class([
                               'flex items-center gap-3 rounded-lg px-3 py-2 transition',
                               'bg-teal-50 font-semibold text-teal-700 border-l-2 border-teal-600' => request()->routeIs($item['route'].'*'),
                               'text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-medium border-l-2 border-transparent' => ! request()->routeIs($item['route'].'*'),
                           ])>
                            @if (!empty($item['icon']))
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    {!! $item['icon'] !!}
                                </svg>
                            @else
                                <span class="h-4 w-4 shrink-0"></span>
                            @endif
                            {{ __($item['label']) }}
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>

        {{-- Sidebar footer --}}
        @if ($authUser)
        <div class="border-t border-slate-100 p-3">
            <div class="flex items-center gap-2 rounded-lg px-2 py-2">
                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-100 text-xs font-semibold text-teal-700">{{ $initials }}</div>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-xs font-semibold text-slate-900">{{ $authUser->name }}</div>
                    <div class="truncate text-xs text-slate-400">{{ $actingRole?->label() ?? __('roles.none') }}</div>
                </div>
            </div>
        </div>
        @endif
    </aside>

    {{-- ========== MAIN COLUMN ========== --}}
    <div class="flex min-w-0 flex-1 flex-col overflow-hidden">

        {{-- ---- HEADER ---- --}}
        <header class="sticky top-0 z-30 flex h-16 shrink-0 items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-6 shadow-sm">

            {{-- Left: hamburger + page title --}}
            <div class="flex items-center gap-3">
                <button @click.stop="sidebarOpen = !sidebarOpen" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 md:hidden" aria-label="Menu">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <div class="hidden md:block text-sm font-semibold text-slate-900">
                    {{ setting('profile.name') ?: __('common.app_name') }}
                </div>
            </div>

            {{-- Right: actions --}}
            @if ($authUser)
            <div class="flex items-center gap-2">
                @if ($currentShift)
                    <div class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs font-medium text-emerald-800 shadow-sm" title="{{ __('shifts.shift_open') }}: {{ $currentShift->till->name }}">
                        <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="font-semibold">{{ $currentShift->till->name }}</span>
                        <span class="text-emerald-600 font-mono">({{ number_format($currentShift->opening_float / 100, 2) }} ETB)</span>
                    </div>
                @elseif ($authUser->hasPermission('shifts.open'))
                    <a href="{{ route('shifts.open') }}"
                       class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-amber-50 border border-amber-200 px-3 py-1 text-xs font-medium text-amber-800 hover:bg-amber-100 transition shadow-sm"
                       title="{{ __('shifts.no_open_shift') }}">
                        <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                        <span>{{ __('shifts.no_open_shift') }}</span>
                        <span class="underline text-amber-900 font-semibold ml-0.5">{{ __('shifts.open_shift') }}</span>
                    </a>
                @endif

                <x-language-switcher />

                {{-- Notification Bell --}}
                <a href="{{ route('notifications') }}"
                   class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900 transition"
                   aria-label="{{ __('nav.notifications') }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    @if ($unread > 0)
                        <span class="absolute top-1 right-1 flex h-4 w-4 items-center justify-center rounded-full bg-rose-500 text-xs font-bold text-white leading-none">{{ $unread > 9 ? '9+' : $unread }}</span>
                    @endif
                </a>

                {{-- User menu --}}
                <div class="relative" @click.stop>
                    <button @click="userMenuOpen = !userMenuOpen"
                            class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-100 transition text-left">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white shadow-sm">{{ $initials }}</div>
                        <div class="hidden sm:block">
                            <div class="text-sm font-semibold text-slate-900 leading-tight">{{ $authUser->name }}</div>
                            <div class="text-xs text-slate-500 leading-tight">{{ $actingRole?->label() ?? __('roles.none') }}</div>
                        </div>
                        <svg class="h-4 w-4 text-slate-400 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    {{-- Dropdown --}}
                    <div x-show="userMenuOpen" x-cloak
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 top-full mt-1.5 w-52 origin-top-right rounded-xl border border-slate-200 bg-white py-1.5 shadow-xl ring-1 ring-slate-100">

                        <div class="border-b border-slate-100 px-4 py-2.5">
                            <div class="text-sm font-semibold text-slate-900">{{ $authUser->name }}</div>
                            <div class="text-xs text-slate-500">{{ $authUser->username }}</div>
                        </div>

                        @if (count($authUser->roleEnums()) > 1)
                        <a href="{{ route('acting-role') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 transition">
                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            {{ __('auth.switch_role') }}
                        </a>
                        @endif

                        <a href="{{ route('security') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 transition">
                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            {{ __('nav.security') }}
                        </a>

                        <div class="border-t border-slate-100 mt-1 pt-1">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-sm text-rose-600 hover:bg-rose-50 transition">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                    {{ __('auth.logout') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </header>

        {{-- ---- PAGE CONTENT ---- --}}
        <main class="flex-1 overflow-y-auto">
            <div class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                @if (session('status'))
                    <div class="mb-5 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800" role="status">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                        {{ session('status') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="mb-5 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
                        {{ session('error') }}
                    </div>
                @endif
                {{ $slot ?? '' }}
                @yield('content')
            </div>
        </main>
    </div>
</div>

<livewire:shared.confirm-with-password />

{{-- Idle warning --}}
<div x-show="warning" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm"
     role="alertdialog" aria-modal="true">
    <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-slate-200">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-100">
                <svg class="h-5 w-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <h2 class="text-base font-semibold text-slate-900">{{ __('auth.idle_warning_title') }}</h2>
        </div>
        <p class="mt-3 text-sm text-slate-600">{{ __('auth.idle_warning_body') }} <span class="font-semibold text-slate-900" x-text="remaining"></span>s</p>
        <div class="mt-5 flex justify-end">
            <button type="button" @click="stay()" class="rounded-lg bg-teal-600 px-5 py-2 text-sm font-semibold text-white hover:bg-teal-700 transition shadow-sm">
                {{ __('auth.stay_signed_in') }}
            </button>
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
