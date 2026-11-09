<div class="space-y-4">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('nav.settings') }}</h1>
    @unless ($canEdit)<p class="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">{{ __('settings.read_only') }}</p>@endunless

    <div class="flex flex-wrap gap-1 border-b border-slate-200" role="tablist">
        @foreach ($groups as $g)
            <button type="button" role="tab" wire:click="$set('group', '{{ $g }}')" wire:key="g-{{ $g }}" @class(['px-4 py-2 text-sm font-medium', 'border-b-2 border-teal-600 text-teal-700' => $group === $g, 'text-slate-500 hover:text-slate-800' => $group !== $g])>{{ __('settings.group.'.$g) }}</button>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">{{ __('settings.key') }}</th><th class="px-4 py-3">{{ __('settings.value') }}</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($rows as $key => $row)
                    <tr wire:key="s-{{ $key }}">
                        <td class="px-4 py-3"><div class="font-mono text-xs text-slate-900">{{ $key }}</div><div class="text-xs text-slate-500">{{ $row['description'] }}</div></td>
                        <td class="px-4 py-3">
                            @if ($row['notSet']) <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ __('common.not_set') }}</span>
                            @else <span class="text-slate-900">{{ is_array($row['current']) ? implode(', ', $row['current']) : (is_bool($row['current']) ? ($row['current'] ? __('common.yes') : __('common.no')) : $row['current']) }}</span> @endif
                            @if ($row['pending']) <span class="ml-2 rounded bg-sky-100 px-2 py-0.5 text-xs text-sky-800">{{ __('settings.pending') }}</span> @endif
                        </td>
                        <td class="space-x-3 px-4 py-3 text-right text-xs">
                            <button type="button" class="text-slate-600 underline" wire:click="showHistory('{{ $key }}')">{{ __('settings.history') }}</button>
                            @if ($canEdit && ! $row['pending'])<button type="button" class="text-teal-700 underline" wire:click="edit('{{ $key }}')">{{ __('settings.request_change') }}</button>@endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($historyKey)
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="flex justify-between"><h2 class="font-semibold">{{ __('settings.history') }}: <code>{{ $historyKey }}</code></h2><button type="button" wire:click="showHistory(null)" class="text-sm text-slate-500">{{ __('common.close') }}</button></div>
            <ul class="mt-2 divide-y divide-slate-100 text-sm">
                @forelse ($history as $h)<li class="py-2" wire:key="h-{{ $h->id }}">{{ local_time(\Illuminate\Support\Carbon::parse($h->created_at)) }}: <code>{{ $h->old_value }}</code> → <code>{{ $h->new_value }}</code></li>
                @empty <li class="py-2 text-slate-500">{{ __('common.no_results') }}</li> @endforelse
            </ul>
        </div>
    @endif

    @if ($editKey)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true">
            <form wire:submit="request" class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h2 class="text-lg font-semibold">{{ __('settings.request_change') }}</h2><p class="font-mono text-xs text-slate-500">{{ $editKey }}</p>
                <p class="mt-2 text-sm text-slate-600">{{ __('settings.approval_note') }}</p>
                <div class="mt-4 space-y-3">
                    @if ($editKey === 'profile.logo')
                        <div><label for="logo" class="block text-sm font-medium">{{ __('settings.logo') }}</label><input id="logo" type="file" wire:model="logo" accept="image/*" class="mt-1 text-sm">@error('logo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    @else
                        <div><label for="value" class="block text-sm font-medium">{{ __('settings.value') }}</label><input id="value" wire:model="value" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><p class="mt-1 text-xs text-slate-500">{{ __('settings.list_hint') }}</p>@error('value')<p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror</div>
                    @endif
                    <div><label for="reason" class="block text-sm font-medium">{{ __('common.reason') }}</label><input id="reason" wire:model="reason" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                </div>
                <div class="mt-5 flex justify-end gap-2"><button type="button" wire:click="cancel" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">{{ __('common.cancel') }}</button><button type="submit" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white">{{ __('users.send_request') }}</button></div>
            </form>
        </div>
    @endif
</div>
