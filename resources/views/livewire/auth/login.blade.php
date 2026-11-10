<div>
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10 backdrop-blur-md border border-white/20 text-2xl font-extrabold text-white shadow-xl mb-3">
            Rx
        </div>
        <h1 class="text-3xl font-extrabold tracking-tight text-white drop-shadow-sm">{{ setting('profile.name') ?: __('common.app_name') }}</h1>
        <p class="mt-2 text-sm text-teal-100 font-medium">{{ __('auth.login_subtitle') }}</p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="rounded-2xl bg-white/95 backdrop-blur-md px-6 py-8 shadow-2xl ring-1 ring-slate-900/10 sm:px-10">
            @if ($errors->has('username'))
                <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" role="alert">
                    <div class="flex items-center gap-2 font-semibold">
                        <svg class="h-5 w-5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        {{ __('auth.failed_title') }}
                    </div>
                    <p class="mt-1 text-xs text-rose-700">{{ $errors->first('username') }}</p>
                </div>
            @endif

            <form wire:submit="login" class="space-y-5" x-data="{ show: false }">
                <div>
                    <label for="username" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('auth.username') }}</label>
                    <div class="relative mt-1">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <input wire:model="username" id="username" type="text" autocomplete="username" required autofocus
                               placeholder="e.g. admin or staff@pms.local"
                               class="block w-full rounded-xl border border-slate-300 py-2.5 pl-9 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('auth.password') }}</label>
                    <div class="relative mt-1">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                        <input wire:model="password" id="password" :type="show ? 'text' : 'password'" autocomplete="current-password" required
                               placeholder="••••••••"
                               class="block w-full rounded-xl border border-slate-300 py-2.5 pl-9 pr-14 text-sm text-slate-900 placeholder:text-slate-400 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                        <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex items-center pr-3 text-xs font-medium text-slate-500 hover:text-teal-700 transition" x-text="show ? '{{ __('auth.hide') }}' : '{{ __('auth.show') }}'"></button>
                    </div>
                </div>

                <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center gap-2 cursor-pointer text-slate-600 text-xs">
                        <input type="checkbox" wire:model="remember" class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                        {{ __('auth.remember_me') }}
                    </label>
                </div>

                <button type="submit" wire:loading.attr="disabled" class="flex w-full items-center justify-center rounded-xl bg-teal-600 px-4 py-3 text-sm font-semibold text-white shadow-md hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 disabled:opacity-60 transition">
                    <span wire:loading.remove wire:target="login">{{ __('auth.login') }}</span>
                    <span wire:loading wire:target="login" class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        {{ __('common.please_wait') }}
                    </span>
                </button>
            </form>
        </div>
    </div>
</div>
