<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('nav.settings') }}</h1>
            <p class="mt-1 text-sm text-slate-500">System parameters, security controls, and operational configurations</p>
        </div>
        @unless ($canEdit)
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-1.5 text-xs text-slate-500 font-medium">
                {{ __('settings.read_only') }}
            </div>
        @endunless
    </div>

    <div class="border-b border-slate-200" role="tablist">
        <nav class="-mb-px flex space-x-6 overflow-x-auto">
            @foreach ($groups as $g)
                <button type="button" role="tab" wire:click="$set('group', '{{ $g }}')" wire:key="g-{{ $g }}"
                        class="py-3 px-1 border-b-2 font-semibold text-sm transition whitespace-nowrap {{ $group === $g ? 'border-teal-600 text-teal-700' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
                    {{ __('settings.group.'.$g) }}
                </button>
            @endforeach
        </nav>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50/75 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3.5 text-left">{{ __('settings.key') }}</th>
                        <th class="px-5 py-3.5 text-left">{{ __('settings.value') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach ($rows as $key => $row)
                        <tr wire:key="s-{{ $key }}" class="hover:bg-slate-50/70 transition">
                            <td class="px-5 py-3.5">
                                <div class="font-mono text-xs font-semibold text-slate-900">{{ $key }}</div>
                                <div class="text-xs text-slate-400 mt-0.5">{{ $row['description'] }}</div>
                            </td>
                            <td class="px-5 py-3.5 text-xs">
                                @if ($row['notSet'])
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 ring-1 ring-amber-600/20">{{ __('common.not_set') }}</span>
                                @else
                                    <span class="font-mono text-slate-900 font-medium">{{ is_array($row['current']) ? implode(', ', $row['current']) : (is_bool($row['current']) ? ($row['current'] ? __('common.yes') : __('common.no')) : $row['current']) }}</span>
                                @endif
                                @if ($row['pending'])
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-sky-50 text-sky-700 ring-1 ring-sky-600/20">{{ __('settings.pending') }}</span>
                                @endif
                            </td>
                            <td class="space-x-3 px-5 py-3.5 text-right text-xs whitespace-nowrap">
                                <button type="button" class="text-slate-500 hover:text-slate-800 font-medium transition" wire:click="showHistory('{{ $key }}')">{{ __('settings.history') }}</button>
                                @if ($canEdit && ! $row['pending'])
                                    <button type="button" class="text-teal-700 hover:text-teal-900 font-semibold transition" wire:click="edit('{{ $key }}')">{{ __('settings.request_change') }}</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($historyKey)
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h2 class="font-bold text-slate-900 text-sm">
                    {{ __('settings.history') }}: <code class="font-mono text-teal-700">{{ $historyKey }}</code>
                </h2>
                <button type="button" wire:click="showHistory(null)" class="text-xs text-slate-500 hover:text-slate-700 font-medium">
                    {{ __('common.close') }} ✕
                </button>
            </div>
            <ul class="divide-y divide-slate-100 text-xs">
                @forelse ($history as $h)
                    <li class="py-2.5 flex items-center justify-between" wire:key="h-{{ $h->id }}">
                        <span class="text-slate-500">{{ local_time(\Illuminate\Support\Carbon::parse($h->created_at)) }}</span>
                        <div class="font-mono text-slate-800">
                            <span class="text-rose-600 line-through">{{ $h->old_value }}</span> &rarr; <span class="text-emerald-700 font-bold">{{ $h->new_value }}</span>
                        </div>
                    </li>
                @empty
                    <li class="py-4 text-center text-slate-400 italic">{{ __('common.no_results') }}</li>
                @endforelse
            </ul>
        </div>
    @endif

    @if ($editKey)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
            <form wire:submit="request" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-slate-200">
                <h2 class="text-lg font-bold text-slate-900 pb-2 border-b border-slate-100">{{ __('settings.request_change') }}</h2>
                <p class="font-mono text-xs text-teal-700 mt-2 font-semibold">{{ $editKey }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ __('settings.approval_note') }}</p>
                <div class="mt-4 space-y-4">
                    @if ($editKey === 'profile.logo')
                        <div>
                            <label for="logo" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('settings.logo') }}</label>
                            <input id="logo" type="file" wire:model="logo" accept="image/*" class="mt-1 block w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100">
                            @error('logo') <p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div>
                            <label for="value" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('settings.value') }}</label>
                            <input id="value" wire:model="value" class="mt-1 w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                            <p class="mt-1 text-xs text-slate-400">{{ __('settings.list_hint') }}</p>
                            @error('value') <p class="mt-1 text-xs text-rose-600 font-medium" role="alert">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    <div>
                        <label for="reason" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('common.reason') }}</label>
                        <input id="reason" wire:model="reason" placeholder="Why is this change required?" class="mt-1 w-full rounded-xl border border-slate-300 py-2 px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/20 transition">
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2.5 pt-4 border-t border-slate-100">
                    <button type="button" wire:click="cancel" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">{{ __('common.cancel') }}</button>
                    <button type="submit" class="rounded-xl bg-teal-600 px-5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition">{{ __('users.send_request') }}</button>
                </div>
            </form>
        </div>
    @endif
</div>
