<?php

namespace App\Livewire\Staff;

use App\Actions\Staff\SaveStaffRecord;
use App\Livewire\Forms\StaffForm;
use App\Models\Staff;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Create / edit form (UC-39 step 5). The photo goes to private storage. */
class Edit extends Component
{
    use WithFileUploads;

    public StaffForm $form;

    #[Locked]
    public ?int $staffId = null;

    public $photo = null;

    public function mount(?Staff $staff = null): void
    {
        Gate::authorize('manage', Staff::class);

        if ($staff && $staff->exists) {
            $this->staffId = $staff->id;
            $this->form->fillFrom($staff);
        }
    }

    public function save(SaveStaffRecord $action)
    {
        Gate::authorize('manage', Staff::class);
        $this->form->validate();
        $this->validate(['photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);

        $staff = $this->staffId ? Staff::findOrFail($this->staffId) : null;
        $data = $this->form->payload();

        if ($this->photo) {
            $data['photo_path'] = $this->photo->store('staff-photos', 'local');
        }

        $saved = $action->execute(Auth::user(), $data, $staff);

        session()->flash('status', __('common.saved'));

        return redirect()->route('staff.show', $saved);
    }

    public function render()
    {
        return view('livewire.staff.edit')->layout('layouts.app', ['title' => __('staff.edit_title')]);
    }
}
