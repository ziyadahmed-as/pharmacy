<?php

namespace App\Livewire\Audit;

use App\Models\User;
use App\Services\AuditLogQuery;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Read-only. No update or delete exists anywhere on this screen (E1). */
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $user = '';

    #[Url]
    public string $role = '';

    #[Url]
    public string $action = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $subject = '';

    #[Locked]
    public ?int $detailId = null;

    public function mount(): void
    {
        $this->authorizeView();
    }

    private function authorizeView(): void
    {
        $u = Auth::user();
        abort_unless($u->hasPermission('audit.view') || $u->hasPermission('audit.view.technical'), 403);
    }

    public function updating($name): void
    {
        $this->resetPage();
    }

    public function showDetail(int $id): void
    {
        $this->authorizeView();
        $this->detailId = $id;
    }

    public function closeDetail(): void
    {
        $this->detailId = null;
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return array_filter([
            'user' => $this->user ?: null, 'role' => $this->role ?: null, 'action' => $this->action ?: null,
            'from' => $this->from ?: null, 'to' => $this->to ?: null, 'subject' => $this->subject ?: null,
        ]);
    }

    public function render()
    {
        $this->authorizeView();
        $viewer = Auth::user();
        $query = new AuditLogQuery($this->filters());

        $detail = $this->detailId ? $query->builder($viewer)->whereKey($this->detailId)->first() : null;

        return view('livewire.audit.index', [
            'logs' => $query->builder($viewer)->paginate(25),
            'users' => User::orderBy('username')->get(['id', 'username']),
            'detail' => $detail,
            'viewer' => $viewer,
            'canExport' => $viewer->hasPermission('audit.export'),
            'exportQuery' => http_build_query($this->filters()),
        ])->layout('layouts.app', ['title' => __('nav.audit')]);
    }
}
