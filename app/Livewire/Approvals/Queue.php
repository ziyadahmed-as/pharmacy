<?php

namespace App\Livewire\Approvals;

use App\Models\ApprovalRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Queue extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'pending';

    #[Url]
    public string $type = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', ApprovalRequest::class);
        if ($this->tab === 'pending' && ! Auth::user()->hasPermission('approvals.process')) {
            $this->tab = 'mine';
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['pending', 'mine'], true) ? $tab : 'mine';
        $this->resetPage();
    }

    public function render()
    {
        $user = Auth::user();
        Gate::authorize('viewAny', ApprovalRequest::class);

        $query = $this->tab === 'pending' && $user->hasPermission('approvals.process')
            ? ApprovalRequest::query()->decidableBy($user)
            : ApprovalRequest::query()->where('initiator_id', $user->id);

        $query->when($this->type !== '', fn ($q) => $q->where('type', $this->type))->with('initiator')->latest('id');

        return view('livewire.approvals.queue', [
            'requests' => $query->paginate(15),
            'types' => ApprovalRequest::query()->distinct()->pluck('type'),
            'canProcess' => $user->hasPermission('approvals.process'),
        ])->layout('layouts.app', ['title' => __('nav.approvals')]);
    }
}
