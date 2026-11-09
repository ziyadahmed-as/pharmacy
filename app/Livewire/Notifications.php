<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/** In-app notifications for the signed-in user (FR-9.1). */
class Notifications extends Component
{
    use WithPagination;

    public function markRead(string $id): void
    {
        DB::table('notifications')
            ->where('id', $id)
            ->where('notifiable_type', Auth::user()->getMorphClass())
            ->where('notifiable_id', Auth::id())
            ->update(['read_at' => now()]);
    }

    public function markAllRead(): void
    {
        DB::table('notifications')
            ->where('notifiable_type', Auth::user()->getMorphClass())
            ->where('notifiable_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function render()
    {
        $items = DB::table('notifications')
            ->where('notifiable_type', Auth::user()->getMorphClass())
            ->where('notifiable_id', Auth::id())
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('livewire.notifications', ['items' => $items])
            ->layout('layouts.app', ['title' => __('nav.notifications')]);
    }
}
