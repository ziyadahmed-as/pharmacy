<?php

namespace App\Livewire\Users;

use App\Actions\Users\CreateUserAccount;
use App\Actions\Users\RequestUserChange;
use App\Actions\Users\ResetPassword;
use App\Actions\Users\UnlockAccount;
use App\Enums\Role as RoleEnum;
use App\Exceptions\ApprovalException;
use App\Models\ApprovalRequest;
use App\Models\CredentialReveal;
use App\Models\Staff;
use App\Models\User;
use App\Services\CredentialRevealService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * UC-35 user accounts and UC-36 recovery row actions. The SA manages accounts and
 * never sees business data; PM and Owner can view the list (read only).
 */
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    // which modal is open: create | roles | deactivate | reactivate | unlock | reset | ''
    public string $modal = '';

    #[Locked]
    public ?int $targetId = null;

    // create wizard
    public ?int $staffId = null;

    public string $username = '';

    /** @var list<string> */
    public array $roles = [];

    // reason / recovery fields
    public string $reason = '';

    public bool $identityVerified = false;

    public string $note = '';

    // one-time reveals (never persisted by this component beyond the request)
    public ?string $revealedPassword = null;

    public ?string $revealedFor = null;

    public function mount(): void
    {
        Gate::authorize('users.view');
    }

    public function updating($name): void
    {
        $this->resetPage();
    }

    public function open(string $modal, ?int $targetId = null): void
    {
        Gate::authorize('users.manage');
        if (in_array($modal, ['unlock', 'reset'], true)) {
            Gate::authorize('users.recover');
        }
        $this->resetErrorBag();
        $this->reset(['staffId', 'username', 'reason', 'identityVerified', 'note', 'revealedPassword', 'revealedFor']);
        $this->targetId = $targetId;
        $this->modal = $modal;
        $this->roles = $modal === 'roles' && $targetId ? User::findOrFail($targetId)->roles()->pluck('slug')->all() : [];
    }

    public function closeModal(): void
    {
        $this->reset(['modal', 'targetId', 'revealedPassword', 'revealedFor']);
    }

    public function createAccount(CreateUserAccount $action): void
    {
        $this->guarded(function () use ($action) {
            $action->execute(Auth::user(), Staff::findOrFail($this->staffId), $this->username, $this->roles);
            session()->flash('status', __('users.request_sent'));
            $this->closeModal();
        });
    }

    public function saveRoles(RequestUserChange $action): void
    {
        $this->guarded(function () use ($action) {
            $action->changeRoles(Auth::user(), User::findOrFail($this->targetId), $this->roles, $this->reason ?: null);
            session()->flash('status', __('users.request_sent'));
            $this->closeModal();
        });
    }

    public function deactivate(RequestUserChange $action): void
    {
        $this->guarded(function () use ($action) {
            $action->deactivate(Auth::user(), User::findOrFail($this->targetId), $this->reason);
            session()->flash('status', __('users.request_sent'));
            $this->closeModal();
        });
    }

    public function reactivate(RequestUserChange $action): void
    {
        $this->guarded(function () use ($action) {
            $action->reactivate(Auth::user(), User::findOrFail($this->targetId), $this->reason);
            session()->flash('status', __('users.request_sent'));
            $this->closeModal();
        });
    }

    public function unlock(UnlockAccount $action): void
    {
        $this->guarded(function () use ($action) {
            $action->execute(Auth::user(), User::findOrFail($this->targetId), $this->identityVerified, $this->note);
            session()->flash('status', __('users.unlocked'));
            $this->closeModal();
        });
    }

    public function resetPassword(ResetPassword $action): void
    {
        $this->guarded(function () use ($action) {
            $target = User::findOrFail($this->targetId);
            $result = $action->execute(Auth::user(), $target, $this->identityVerified, $this->note);
            $this->revealedPassword = $result['temporary_password'];
            $this->revealedFor = $target->username;
            $this->modal = 'reveal';
        });
    }

    public function revealCredential(int $id, CredentialRevealService $service): void
    {
        Gate::authorize('users.manage');
        $result = $service->reveal($id, Auth::user());
        if (! $result) {
            $this->addError('reveal', __('users.reveal_gone'));

            return;
        }
        $this->revealedPassword = $result['password'];
        $this->revealedFor = $result['username'];
        $this->modal = 'reveal';
    }

    /** Runs a use case; shows validation / rule errors inline instead of failing the page. */
    private function guarded(callable $fn): void
    {
        try {
            $fn();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
        } catch (ApprovalException $e) {
            $this->addError('approval', $e->getMessage());
        }
    }

    public function render()
    {
        Gate::authorize('users.view');
        $me = Auth::user();

        $users = User::query()->with('roles')
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('username', 'like', "%{$this->search}%")->orWhere('name', 'like', "%{$this->search}%")))
            ->when($this->statusFilter === 'locked', fn ($q) => $q->whereNotNull('locked_at'))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true)->where('account_status', 'active')->whereNull('locked_at'))
            ->when($this->statusFilter === 'pending_approval', fn ($q) => $q->where('account_status', 'pending_approval'))
            ->when($this->statusFilter === 'deactivated', fn ($q) => $q->where('is_active', false)->where('account_status', '!=', 'pending_approval')->where('account_status', '!=', 'rejected'))
            ->where('account_status', '!=', 'rejected')
            ->orderBy('username')->paginate(15);

        // which accounts already have an approval waiting
        $waiting = ApprovalRequest::pending()->whereNotNull('concerns_user_id')->pluck('concerns_user_id')->all();

        return view('livewire.users.index', [
            'users' => $users,
            'waiting' => $waiting,
            'canManage' => $me->hasPermission('users.manage'),
            'canRecover' => $me->hasPermission('users.recover'),
            'availableStaff' => $this->modal === 'create' ? Staff::whereNull('user_id')->where('status', '!=', Staff::ENDED)->orderBy('name')->get() : collect(),
            'allRoles' => RoleEnum::cases(),
            'reveals' => $me->hasPermission('users.manage') ? CredentialReveal::where('for_user_id', $me->id)->where('expires_at', '>', now())->with([])->get() : collect(),
            'target' => $this->targetId ? User::find($this->targetId) : null,
        ])->layout('layouts.app', ['title' => __('nav.users')]);
    }
}
