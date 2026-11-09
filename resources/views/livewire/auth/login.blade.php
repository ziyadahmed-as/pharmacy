<div>
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <div class="flex justify-center"><div class="flex h-14 w-14 items-center justify-center rounded-xl bg-teal-600 text-xl font-bold text-white shadow-lg">Rx</div></div>
        <h1 class="mt-4 text-center text-2xl font-bold tracking-tight text-slate-900">{{ setting('profile.name') ?: __('common.app_name') }}</h1>
        <p class="mt-1 text-center text-sm text-slate-500">{{ __('auth.login_subtitle') }}</p>
    </div>
    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="border border-slate-100 bg-white px-4 py-8 shadow-xl sm:rounded-xl sm:px-10">
            @if ($errors->has('username'))
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4" role="alert">
                    <h2 class="text-sm font-medium text-red-800">{{ __('auth.failed_title') }}</h2>
                    <p class="mt-1 text-sm text-red-700">{{ $errors->first('username') }}</p>
                </div>
            @endif
            <form wire:submit="login" class="space-y-6" x-data="{ show: false }">
                <div>
                    <label for="username" class="block text-sm font-medium text-slate-700">{{ __('auth.username') }}</label>
                    <input wire:model="username" id="username" type="text" autocomplete="username" required autofocus
                           class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 focus:border-teal-500 focus:outline-none focus:ring-teal-500 sm:text-sm">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">{{ __('auth.password') }}</label>
                    <div class="relative mt-1">
                        <input wire:model="password" id="password" :type="show ? 'text' : 'password'" autocomplete="current-password" required
                               class="block w-full rounded-lg border border-slate-300 py-2 pl-3 pr-16 text-slate-900 focus:border-teal-500 focus:outline-none focus:ring-teal-500 sm:text-sm">
                        <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 px-3 text-xs text-slate-500 hover:text-slate-800" x-text="show ? '{{ __('auth.hide') }}' : '{{ __('auth.show') }}'"></button>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" wire:model="remember" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500"> {{ __('auth.remember_me') }}
                </label>
                <button type="submit" wire:loading.attr="disabled" class="flex w-full justify-center rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="login">{{ __('auth.login') }}</span>
                    <span wire:loading wire:target="login">{{ __('common.please_wait') }}</span>
                </button>
            </form>
        </div>
    </div>
</div>
