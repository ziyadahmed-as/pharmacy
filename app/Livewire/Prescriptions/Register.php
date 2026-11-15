<?php

namespace App\Livewire\Prescriptions;

use App\Actions\Prescriptions\RegisterPrescription;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\Prescriber;
use App\Models\Product;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

#[Layout('layouts.app')]
#[Title('Register Prescription')]
class Register extends Component
{
    use WithFileUploads;

    public ?int $prescriberId = null;

    public string $prescriberSearch = '';

    public array $prescriberSearchResults = [];

    public ?string $prescriberWarning = null;

    // Quick add prescriber modal
    public bool $showQuickAddModal = false;

    public string $quickAddName = '';

    public string $quickAddLicence = '';

    public string $quickAddFacility = '';

    public string $quickAddPhone = '';

    public string $prescriberName = '';

    public string $prescriberRegNo = '';

    public string $prescriptionDate = '';

    // Patient section
    public string $patientPhone = '';

    public string $patientName = '';

    public ?int $customerId = null;

    public ?string $customerFoundName = null;

    /** @var array<int, array{temp_id: string, product_id: int|null, product_label: string, dose: string, quantity: int, search: string, results: array}> */
    public array $lines = [];

    public mixed $scan = null;

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    /** @var array<int, array{id: int, brand: string, generic_name: string, strength: string}> */
    public array $productSearchResults = [];

    public ?string $activeLineSearch = null;

    public function updatedPrescriberSearch(): void
    {
        $term = trim($this->prescriberSearch);
        if (strlen($term) >= 1) {
            $this->prescriberSearchResults = Prescriber::search($term)
                ->take(8)
                ->get()
                ->toArray();
        } else {
            $this->prescriberSearchResults = [];
        }
    }

    public function selectPrescriber(int $id): void
    {
        $prescriber = Prescriber::find($id);
        if (! $prescriber) {
            return;
        }

        $this->prescriberId = $prescriber->id;
        $this->prescriberName = $prescriber->name;
        $this->prescriberRegNo = (string) ($prescriber->licence_number ?? '');
        $this->prescriberSearch = $prescriber->displayText();
        $this->prescriberSearchResults = [];

        if (! $prescriber->is_active) {
            $this->prescriberWarning = __('prescribers.inactive_warning', [
                'name' => $prescriber->name,
                'default' => "Warning: Prescriber {$prescriber->name} is marked as inactive.",
            ]);
        } else {
            $this->prescriberWarning = null;
        }
    }

    public function clearPrescriber(): void
    {
        $this->prescriberId = null;
        $this->prescriberName = '';
        $this->prescriberRegNo = '';
        $this->prescriberSearch = '';
        $this->prescriberSearchResults = [];
        $this->prescriberWarning = null;
    }

    public function openQuickAddPrescriber(): void
    {
        $this->reset(['quickAddName', 'quickAddLicence', 'quickAddFacility', 'quickAddPhone']);
        if ($this->prescriberSearch && ! $this->prescriberId) {
            $this->quickAddName = trim($this->prescriberSearch);
        }
        $this->resetErrorBag();
        $this->showQuickAddModal = true;
    }

    public function saveQuickAddPrescriber(): void
    {
        $this->validate([
            'quickAddName' => ['required', 'string', 'max:255'],
            'quickAddLicence' => ['nullable', 'string', 'max:64', 'unique:prescribers,licence_number'],
            'quickAddFacility' => ['nullable', 'string', 'max:255'],
            'quickAddPhone' => ['nullable', 'string', 'max:32'],
        ]);

        $cleanLicence = trim($this->quickAddLicence) !== '' ? trim($this->quickAddLicence) : null;
        $cleanFacility = trim($this->quickAddFacility) !== '' ? trim($this->quickAddFacility) : null;
        $cleanPhone = trim($this->quickAddPhone) !== '' ? trim($this->quickAddPhone) : null;

        $prescriber = Prescriber::create([
            'name' => trim($this->quickAddName),
            'licence_number' => $cleanLicence,
            'facility' => $cleanFacility,
            'phone' => $cleanPhone,
            'is_active' => true,
        ]);

        AuditLogger::record(
            action: 'prescriber.created',
            subject: $prescriber,
            old: [],
            new: $prescriber->only(['name', 'licence_number', 'facility', 'phone', 'is_active']),
            user: Auth::user(),
            description: "Quick-added prescriber {$prescriber->name}"
        );

        $this->selectPrescriber($prescriber->id);
        $this->showQuickAddModal = false;
    }

    public function mount(): void
    {
        $user = Auth::user();
        abort_if(! $user || $user->hasRole(Role::SystemAdmin), 403);
        abort_unless($user->hasPermission('prescriptions.register'), 403);

        $this->prescriptionDate = now()->toDateString();
        $this->addLine();
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'temp_id' => Str::uuid()->toString(),
            'product_id' => null,
            'product_label' => '',
            'dose' => '',
            'quantity' => 1,
            'search' => '',
            'results' => [],
        ];
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) > 1) {
            array_splice($this->lines, $index, 1);
        }
    }

    public function searchProduct(int $index): void
    {
        $search = trim($this->lines[$index]['search'] ?? '');

        if (strlen($search) < 2) {
            $this->lines[$index]['results'] = [];

            return;
        }

        $results = Product::query()
            ->where('is_active', true)
            ->whereIn('classification', ['rx', 'controlled'])
            ->where(function ($q) use ($search) {
                $q->where('brand', 'like', "%{$search}%")
                    ->orWhere('generic_name', 'like', "%{$search}%");
            })
            ->limit(8)
            ->get(['id', 'brand', 'generic_name', 'strength', 'dosage_form', 'classification'])
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'brand' => $p->brand,
                'generic_name' => $p->generic_name,
                'strength' => $p->strength,
                'dosage_form' => $p->dosage_form,
            ])
            ->all();

        $this->lines[$index]['results'] = $results;
    }

    public function selectProduct(int $index, int $productId, string $label): void
    {
        $this->lines[$index]['product_id'] = $productId;
        $this->lines[$index]['product_label'] = $label;
        $this->lines[$index]['search'] = $label;
        $this->lines[$index]['results'] = [];
    }

    public function lookupPatient(): void
    {
        $phone = trim($this->patientPhone);
        if ($phone === '') {
            $this->customerFoundName = null;
            $this->customerId = null;

            return;
        }

        $customer = Customer::where('phone', $phone)->first();

        if ($customer) {
            $this->customerId = $customer->id;
            $this->customerFoundName = $customer->name;
            $this->patientName = $customer->name;
        } else {
            $this->customerId = null;
            $this->customerFoundName = null;
        }
    }

    public function save(): void
    {
        $this->errorMessage = null;

        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        // Build lines array
        $lines = [];
        foreach ($this->lines as $line) {
            if (empty($line['product_id'])) {
                continue;
            }
            $lines[] = [
                'product_id' => (int) $line['product_id'],
                'dose' => $line['dose'],
                'quantity' => (int) $line['quantity'],
            ];
        }

        // Handle scan upload with validation (image or PDF, max 5MB)
        $scanPath = null;
        if ($this->scan) {
            $this->validate([
                'scan' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            ]);

            $ext = $this->scan->getClientOriginalExtension();
            $uuid = Str::uuid()->toString();
            $scanPath = $this->scan->storeAs('prescriptions', "{$uuid}.{$ext}", 'private');
        }

        $patientData = [
            'customer_id' => $this->customerId,
            'name' => $this->patientName,
            'phone' => $this->patientPhone,
        ];

        try {
            $prescription = app(RegisterPrescription::class)->execute(
                $user,
                $this->prescriberName,
                $this->prescriberRegNo,
                $this->prescriptionDate,
                $patientData,
                $lines,
                $scanPath,
                $this->prescriberId,
            );

            session()->flash('success', __('prescriptions.prescription_registered_successfully'));
            $this->redirect(route('prescriptions.show', $prescription), navigate: true);
        } catch (AuthorizationException|InvalidArgumentException|RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render(): View
    {
        return view('livewire.prescriptions.register');
    }
}
