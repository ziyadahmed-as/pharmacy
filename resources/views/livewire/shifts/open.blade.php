<div class="max-w-4xl mx-auto space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('shifts.open_shift_title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('shifts.open_shift_subtitle') }}</p>
        </div>
        @if (auth()->user()?->hasPermission('tills.manage'))
            <a href="{{ route('tills.index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
                <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>
                {{ __('shifts.manage_tills') }}
            </a>
        @endif
    </div>

    @if ($errorMessage)
        <div class="flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" role="alert">
            <svg class="h-5 w-5 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                <span class="font-bold">{{ __('shifts.error') }}:</span> {{ $errorMessage }}
            </div>
        </div>
    @endif

    {{-- Case 1: User ALREADY has an active open shift --}}
    @if ($currentShift)
        <div class="rounded-2xl border border-teal-200 bg-white p-6 sm:p-8 shadow-sm space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-teal-50 text-teal-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-lg font-bold text-slate-900">{{ __('shifts.active_shift_title') }}</h2>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20">
                            {{ __('shifts.status_open') }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">{{ __('shifts.active_shift_help') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="rounded-xl border border-slate-200 bg-slate-50/75 p-4">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('shifts.till') }}</span>
                    <div class="text-lg font-bold text-slate-900 mt-1">{{ $currentShift->till->name }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50/75 p-4">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('shifts.opened_at') }}</span>
                    <div class="text-lg font-bold text-slate-900 mt-1">{{ local_time($currentShift->opened_at, 'Y-m-d H:i') }}</div>
                </div>
                <div class="rounded-xl border border-teal-200 bg-teal-50/60 p-4">
                    <span class="text-xs font-semibold uppercase tracking-wider text-teal-800">{{ __('shifts.opening_float') }}</span>
                    <div class="text-lg font-extrabold text-teal-900 mt-1 font-mono">
                        {{ number_format($currentShift->opening_float / 100, 2) }} <span class="text-xs font-normal text-teal-700">ETB</span>
                    </div>
                </div>
            </div>

            <div class="pt-2 text-xs text-slate-500">
                <span>{{ __('shifts.user_single_shift_notice') }}</span>
            </div>
        </div>

    {{-- Case 2: User has NO open shift, show picker and open form --}}
    @else
        <form wire:submit.prevent="openShift" class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-8">
            {{-- Till Selection --}}
            <div>
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800">{{ __('shifts.step_select_till') }}</h3>
                    </div>
                    <span class="text-xs text-slate-400">{{ __('shifts.one_shift_per_till_rule') }}</span>
                </div>

                @error('selectedTillId') <span class="text-xs text-rose-600 mt-1 block font-medium">{{ $message }}</span> @enderror

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @forelse ($tills as $till)
                        @php
                            $isOccupied = $till->activeShift !== null;
                            $isSelected = $selectedTillId === $till->id;
                        @endphp
                        <button
                            type="button"
                            wire:click="selectTill({{ $till->id }})"
                            @disabled($isOccupied)
                            class="relative rounded-xl border p-4 text-left transition flex flex-col justify-between
                                {{ $isOccupied ? 'border-slate-200 bg-slate-50/60 opacity-60 cursor-not-allowed' : ($isSelected ? 'border-teal-600 bg-teal-50/50 ring-2 ring-teal-500/20' : 'border-slate-200 hover:border-teal-400 hover:bg-slate-50') }}"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <span class="text-sm font-bold {{ $isSelected ? 'text-teal-900' : 'text-slate-900' }}">{{ $till->name }}</span>
                                @if ($isOccupied)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 ring-1 ring-rose-600/20 whitespace-nowrap">
                                        🔒 {{ __('shifts.occupied') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20 whitespace-nowrap">
                                        🟢 {{ __('shifts.available') }}
                                    </span>
                                @endif
                            </div>

                            <div class="mt-3 text-xs">
                                @if ($isOccupied)
                                    <span class="text-slate-500">{{ __('shifts.in_use_by', ['user' => $till->activeShift->user?->name ?? '—']) }}</span>
                                @else
                                    <span class="text-slate-400">{{ __('shifts.ready_to_open') }}</span>
                                @endif
                            </div>
                        </button>
                    @empty
                        <div class="col-span-full py-8 text-center text-slate-400 italic">
                            {{ __('shifts.no_active_tills_found') }}
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Opening Float --}}
            <div>
                <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                    <svg class="h-4 w-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800">{{ __('shifts.step_enter_opening_float') }}</h3>
                </div>

                <div class="mt-4 max-w-sm">
                    <label for="openingFloat" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('shifts.opening_float_etb') }} <span class="text-rose-500">*</span></label>
                    <div class="relative mt-1">
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            id="openingFloat"
                            wire:model="openingFloat"
                            class="block w-full rounded-xl border border-slate-300 py-2.5 pl-3 pr-14 text-sm font-mono font-bold text-slate-900 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition"
                            placeholder="0.00"
                            required
                        />
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-xs font-semibold text-slate-400">
                            ETB
                        </div>
                    </div>
                    <p class="mt-1.5 text-xs text-slate-500">{{ __('shifts.opening_float_hint') }}</p>
                    @error('openingFloat') <span class="text-xs text-rose-600 mt-1 block font-medium">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- Submit Button --}}
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <a href="{{ route('dashboard') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                    {{ __('common.cancel') }}
                </a>
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    @disabled(! $selectedTillId)
                    class="rounded-xl bg-teal-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 disabled:opacity-50 transition"
                >
                    <span wire:loading.remove>{{ __('shifts.button_open_shift') }}</span>
                    <span wire:loading class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        {{ __('common.please_wait') }}
                    </span>
                </button>
            </div>
        </form>
    @endif
</div>

