<?php

namespace App\Livewire\Approvals;

use App\Exceptions\ApprovalException;
use App\Models\ApprovalRequest;
use App\Services\ApprovalService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public int $requestId;

    public string $comment = '';

    public string $pending = ''; // 'approve' | 'reject' while waiting for the password

    public function mount(ApprovalRequest $approvalRequest): void
    {
        Gate::authorize('view', $approvalRequest);
        $this->requestId = $approvalRequest->id;
    }

    public function startDecision(string $decision): void
    {
        $request = ApprovalRequest::findOrFail($this->requestId);
        Gate::authorize('decide', $request);

        if ($decision === 'reject' && trim($this->comment) === '') {
            $this->addError('comment', __('approvals.error.comment_required'));

            return;
        }

        $this->pending = $decision === 'reject' ? 'reject' : 'approve';
        $this->dispatch('confirm-password', purpose: ApprovalService::REAUTH_PURPOSE);
    }

    #[On('password-confirmed')]
    public function decide(string $purpose, ApprovalService $service)
    {
        if ($purpose !== ApprovalService::REAUTH_PURPOSE || $this->pending === '') {
            return;
        }

        $request = ApprovalRequest::findOrFail($this->requestId);
        Gate::authorize('decide', $request);

        try {
            $this->pending === 'approve'
                ? $service->approve($request, Auth::user(), $this->comment ?: null)
                : $service->reject($request, Auth::user(), $this->comment);
        } catch (ApprovalException $e) {
            $this->addError('decision', $e->getMessage());
            $this->pending = '';

            return;
        }

        session()->flash('status', __('approvals.decided'));

        return redirect()->route('approvals.index');
    }

    public function cancelRequest(ApprovalService $service)
    {
        $request = ApprovalRequest::findOrFail($this->requestId);
        Gate::authorize('cancel', $request);
        $service->cancel($request, Auth::user());
        session()->flash('status', __('approvals.cancelled'));

        return redirect()->route('approvals.index');
    }

    public function render()
    {
        $request = ApprovalRequest::with(['initiator', 'approver', 'concernedUser'])->findOrFail($this->requestId);
        Gate::authorize('view', $request);

        $payload = $request->payload ?? [];
        unset($payload['password']);

        return view('livewire.approvals.show', [
            'r' => $request,
            'payload' => $payload,
            'canDecide' => Gate::allows('decide', $request),
            'canCancel' => Gate::allows('cancel', $request),
        ])->layout('layouts.app', ['title' => __('approvals.detail')]);
    }
}
