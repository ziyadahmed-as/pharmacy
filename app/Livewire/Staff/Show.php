<?php

namespace App\Livewire\Staff;

use App\Actions\Staff\AddOrRenewLicence;
use App\Actions\Staff\DeleteStaffDocument;
use App\Actions\Staff\EndEmployment;
use App\Actions\Staff\UploadStaffDocument;
use App\Models\Staff;
use App\Models\StaffDocument;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class Show extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $staffId;

    public string $lic_number = '';

    public string $lic_body = '';

    public string $lic_category = '';

    public string $lic_issued = '';

    public string $lic_expires = '';

    public string $end_date = '';

    public string $end_reason = '';

    public bool $confirmEnd = false;

    // Document upload properties
    public string $doc_type = 'contract';

    public $doc_file = null;

    public string $doc_issued = '';

    public string $doc_expires = '';

    public function mount(Staff $staff): void
    {
        Gate::authorize('view', $staff);
        $this->staffId = $staff->id;
    }

    public function addLicence(AddOrRenewLicence $action): void
    {
        $staff = Staff::findOrFail($this->staffId);
        Gate::authorize('manage', Staff::class);

        try {
            $action->execute(Auth::user(), $staff, [
                'number' => $this->lic_number,
                'issuing_body' => $this->lic_body,
                'category' => $this->lic_category ?: null,
                'issued_at' => $this->lic_issued ?: null,
                'expires_at' => $this->lic_expires,
            ]);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $f => $m) {
                $this->addError('lic_'.match ($f) {
                    'issuing_body' => 'body', 'issued_at' => 'issued', 'expires_at' => 'expires', default => $f
                }, $m[0]);
            }

            return;
        }

        $this->reset(['lic_number', 'lic_body', 'lic_category', 'lic_issued', 'lic_expires']);
        session()->flash('status', __('staff.licence_saved'));
    }

    public function endEmployment(EndEmployment $action): void
    {
        Gate::authorize('manage', Staff::class);
        $this->validate(['end_date' => ['nullable', 'date']]);

        $action->execute(Auth::user(), Staff::findOrFail($this->staffId), $this->end_date ?: null, $this->end_reason ?: null);
        $this->confirmEnd = false;
        session()->flash('status', __('staff.employment_ended'));
    }

    public function uploadDocument(UploadStaffDocument $action): void
    {
        $staff = Staff::findOrFail($this->staffId);
        Gate::authorize('manage', Staff::class);

        $this->validate([
            'doc_type' => ['required', 'string', 'in:'.implode(',', StaffDocument::TYPES)],
            'doc_file' => ['required', 'file', 'max:5120', 'mimes:pdf,png,jpg,jpeg,webp'],
            'doc_issued' => ['nullable', 'date'],
            'doc_expires' => ['nullable', 'date'],
        ]);

        try {
            $action->execute(Auth::user(), $staff, [
                'type' => $this->doc_type,
                'file' => $this->doc_file,
                'issued_at' => $this->doc_issued ?: null,
                'expires_at' => $this->doc_expires ?: null,
            ]);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $f => $m) {
                $this->addError('doc_'.$f, $m[0]);
            }

            return;
        }

        $this->reset(['doc_file', 'doc_issued', 'doc_expires']);
        $this->doc_type = 'contract';
        session()->flash('status', __('staff.documents.saved'));
    }

    public function deleteDocument(int $docId, DeleteStaffDocument $action): void
    {
        Gate::authorize('manage', Staff::class);
        $doc = StaffDocument::where('staff_id', $this->staffId)->findOrFail($docId);

        $action->execute(Auth::user(), $doc);
        session()->flash('status', __('staff.documents.deleted'));
    }

    public function render()
    {
        $staff = Staff::with(['licences', 'documents.uploadedBy', 'user'])->findOrFail($this->staffId);
        Gate::authorize('view', $staff);
        $user = Auth::user();

        return view('livewire.staff.show', [
            'staff' => $staff,
            'canManage' => Gate::allows('manage', Staff::class),
            'sensitive' => Gate::allows('viewSensitive', $staff),
            'validNow' => $staff->hasValidLicence(),
        ])->layout('layouts.app', ['title' => $staff->name]);
    }
}
