<?php

namespace App\Livewire\Staff;

use App\Models\Staff;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public bool $expiringOnly = false;

    public function mount(): void
    {
        Gate::authorize('viewAny', Staff::class);
    }

    public function updating($name): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $user = Auth::user();
        Gate::authorize('viewAny', Staff::class);

        $days = max(1, (int) setting('staff.licence_alert_days', 60));
        $limit = now()->timezone(config('pms.timezone'))->addDays($days)->toDateString();

        $query = Staff::query()->with(['licences', 'user'])
            ->when(! $user->hasPermission('staff.manage'), fn ($q) => $q->where('user_id', $user->id))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->search}%")->orWhere('title', 'like', "%{$this->search}%")))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->expiringOnly, fn ($q) => $q->whereHas('licences', fn ($l) => $l->whereDate('expires_at', '<=', $limit)))
            ->orderBy('name');

        return view('livewire.staff.index', [
            'staff' => $query->paginate(15),
            'canManage' => $user->hasPermission('staff.manage'),
            'limit' => $limit,
        ])->layout('layouts.app', ['title' => __('nav.staff')]);
    }
}
