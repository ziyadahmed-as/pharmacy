<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('nav.notifications') }}</h1>
            <p class="mt-1 text-sm text-slate-500">Alerts, approval updates, and system notices</p>
        </div>
        <button type="button" wire:click="markAllRead" class="rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
            {{ __('dashboard.mark_all_read') }}
        </button>
    </div>

    <div class="divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        @forelse ($items as $item)
            @php($data = json_decode($item->data, true) ?: [])
            <div class="flex items-start justify-between gap-4 p-5 hover:bg-slate-50/50 transition {{ ! $item->read_at ? 'bg-teal-50/30' : '' }}" wire:key="n-{{ $item->id }}">
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ ! $item->read_at ? 'bg-teal-100 text-teal-700' : 'bg-slate-100 text-slate-400' }}">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </div>
                    <div>
                        <p @class(['text-sm', 'font-bold text-slate-900' => ! $item->read_at, 'text-slate-600 font-medium' => $item->read_at])>
                            {{ __($data['message_key'] ?? 'dashboard.notification_generic', $data['message_params'] ?? []) }}
                        </p>
                        <p class="mt-1 text-xs text-slate-400 font-medium">{{ local_time(\Illuminate\Support\Carbon::parse($item->created_at)) }}</p>
                    </div>
                </div>
                @unless ($item->read_at)
                    <button type="button" wire:click="markRead('{{ $item->id }}')" class="rounded-lg bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-700 hover:bg-teal-100 transition whitespace-nowrap">
                        {{ __('dashboard.mark_read') }}
                    </button>
                @endunless
            </div>
        @empty
            <div class="p-12 text-center text-slate-400 italic">
                {{ __('dashboard.no_notifications') }}
            </div>
        @endforelse
    </div>
    @if ($items->hasPages())
        <div class="px-5 py-3 border-t border-slate-200 bg-slate-50/50">
            {{ $items->links() }}
        </div>
    @endif
</div>
