<?php

namespace App\Livewire\Forms;

use App\Models\Staff;
use Livewire\Attributes\Validate;
use Livewire\Form;

class StaffForm extends Form
{
    public ?int $staffId = null;

    #[Validate('required|string|max:150')]
    public string $name = '';

    #[Validate('nullable|string|max:40')]
    public string $phone = '';

    #[Validate('nullable|string|max:255')]
    public string $address = '';

    /** Never pre-filled: blank means "keep the stored value". */
    #[Validate('nullable|string|max:60')]
    public string $id_number = '';

    #[Validate('nullable|string|max:100')]
    public string $title = '';

    #[Validate('nullable|date')]
    public string $hire_date = '';

    #[Validate('required|in:active,on_leave')]
    public string $status = 'active';

    #[Validate('nullable|string|max:150')]
    public string $emergency_contact_name = '';

    #[Validate('nullable|string|max:40')]
    public string $emergency_contact_phone = '';

    public function fillFrom(Staff $staff): void
    {
        $this->staffId = $staff->id;
        $this->name = $staff->name;
        $this->phone = (string) $staff->phone;
        $this->address = (string) $staff->address;
        $this->title = (string) $staff->title;
        $this->hire_date = $staff->hire_date?->toDateString() ?? '';
        $this->status = $staff->status === Staff::ENDED ? Staff::ACTIVE : $staff->status;
        $this->emergency_contact_name = (string) $staff->emergency_contact_name;
        $this->emergency_contact_phone = (string) $staff->emergency_contact_phone;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $data = $this->only(['name', 'phone', 'address', 'id_number', 'title', 'hire_date', 'status', 'emergency_contact_name', 'emergency_contact_phone']);

        return array_map(fn ($v) => $v === '' ? null : $v, $data) + ['status' => $this->status];
    }
}
