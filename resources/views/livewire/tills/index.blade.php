<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('shifts.tills_title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('shifts.tills_subtitle') }}</p>
        </div>
        <a href="{{ route('shifts.open') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 transition">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('shifts.open_shift_button') }}
        </a>
    </div>

    @if ($errorMessage)
        <div class="flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" role="alert">
            <svg class="h-5 w-5 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>{{ $errorMessage }}</div>
        </div>
    @endif

    {{-- Create Till Card --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-800 pb-3 border-b border-slate-100">{{ __('shifts.add_new_till') }}</h2>
        <form wire:submit.prevent="createTill" class="mt-4 flex flex-col sm:flex-row items-end gap-3 max-w-lg">
            <div class="flex-1 w-full">
                <label for="newTillName" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('shifts.till_name') }} <span class="text-rose-500">*</span></label>
                <input
                    type="text"
                    id="newTillName"
                    wire:model="newTillName"
                    placeholder="e.g. Till 4 - Dispensary West"
                    class="mt-1 block w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition"
                    required
                />
                @error('newTillName') <span class="text-xs text-rose-600 mt-1 block font-medium">{{ $message }}</span> @enderror
            </div>
            <button
                type="submit"
                class="rounded-xl bg-teal-600 px-5 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition shrink-0"
            >
                + {{ __('shifts.create_till') }}
            </button>
        </form>
    </div>

    {{-- Tills Table --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50/75 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3.5 text-left">{{ __('shifts.till_name') }}</th>
                        <th class="px-5 py-3.5 text-left">{{ __('common.status') }}</th>
                        <th class="px-5 py-3.5 text-left">{{ __('shifts.current_shift_status') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($tills as $till)
                        @php
                            $activeShift = $till->activeShift;
                        @endphp
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="px-5 py-3.5 font-bold text-slate-900">
                                {{ $till->name }}
                            </td>
                            <td class="px-5 py-3.5">
                                @if ($till->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20">
                                        {{ __('common.active') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 ring-1 ring-slate-500/20">
                                        {{ __('common.inactive') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-xs">
                                @if ($activeShift)
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 ring-1 ring-amber-600/20">
                                            🔒 {{ __('shifts.occupied') }}
                                        </span>
                                        <span class="font-medium text-slate-700">{{ $activeShift->user?->name ?? '—' }}</span>
                                        <span class="text-slate-400">({{ local_time($activeShift->opened_at, 'H:i') }})</span>
                                    </div>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20">
                                        🟢 {{ __('shifts.free') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right text-xs whitespace-nowrap">
                                <button
                                    type="button"
                                    wire:click="toggleActive({{ $till->id }})"
                                    class="font-semibold transition {{ $till->is_active ? 'text-rose-600 hover:text-rose-800' : 'text-emerald-700 hover:text-emerald-900' }}"
                                >
                                    {{ $till->is_active ? __('shifts.deactivate_till') : __('shifts.activate_till') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-slate-400 italic">
                                {{ __('shifts.no_tills_found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

