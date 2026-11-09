<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-slate-900">{{ __('nav.notifications') }}</h1>
        <button type="button" wire:click="markAllRead" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">{{ __('dashboard.mark_all_read') }}</button>
    </div>
    <div class="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white shadow-sm">
        @forelse ($items as $item)
            @php($data = json_decode($item->data, true) ?: [])
            <div class="flex items-start justify-between gap-4 p-4" wire:key="n-{{ $item->id }}">
                <div>
                    <p @class(['text-sm', 'font-semibold text-slate-900' => ! $item->read_at, 'text-slate-600' => $item->read_at])>{{ __($data['message_key'] ?? 'dashboard.notification_generic', $data['message_params'] ?? []) }}</p>
                    <p class="mt-0.5 text-xs text-slate-400">{{ local_time(\Illuminate\Support\Carbon::parse($item->created_at)) }}</p>
                </div>
                @unless ($item->read_at)
                    <button type="button" wire:click="markRead('{{ $item->id }}')" class="text-xs text-teal-700 underline">{{ __('dashboard.mark_read') }}</button>
                @endunless
            </div>
        @empty
            <p class="p-6 text-center text-sm text-slate-500">{{ __('dashboard.no_notifications') }}</p>
        @endforelse
    </div>
    {{ $items->links() }}
</div>
