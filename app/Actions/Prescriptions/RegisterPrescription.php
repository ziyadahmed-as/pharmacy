<?php

namespace App\Actions\Prescriptions;

use App\Actions\Customers\SaveCustomer;
use App\Models\Customer;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentSequence;
use App\Services\SettingsRepository;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Register a new prescription with product lines from a licensed prescriber.
 *
 * @uc UC-19
 * @fr FR-4.2, FR-4.8, FR-4.11
 */
class RegisterPrescription
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SaveCustomer $saveCustomer,
    ) {}

    /**
     * @param  array{name?: string, phone?: string, customer_id?: int|null}  $patientData
     * @param  array<int, array{product_id: int, dose: string, quantity: int}>  $lines
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function execute(
        User $user,
        string $prescriberName,
        string $prescriberRegNo,
        string $prescriptionDate,
        array $patientData,
        array $lines,
        ?string $scanPath = null,
    ): Prescription {
        // 1. Authorisation
        if (! $user->hasPermission('prescriptions.register')) {
            throw new AuthorizationException(__('prescriptions.unauthorized_to_register'));
        }

        // 2. Validate mandatory fields (E2)
        $prescriberName = trim($prescriberName);
        if ($prescriberName === '') {
            throw new InvalidArgumentException(__('prescriptions.prescriber_name_required'));
        }

        $prescriberRegNo = trim($prescriberRegNo);
        if ($prescriberRegNo === '') {
            throw new InvalidArgumentException(__('prescriptions.prescriber_reg_no_required'));
        }

        if (empty($lines)) {
            throw new InvalidArgumentException(__('prescriptions.at_least_one_line_required'));
        }

        // 3. Parse prescription date
        try {
            $parsedDate = Carbon::parse($prescriptionDate);
        } catch (\Throwable) {
            throw new InvalidArgumentException(__('prescriptions.invalid_date'));
        }

        if ($parsedDate->isFuture()) {
            throw new InvalidArgumentException(__('prescriptions.date_cannot_be_in_future'));
        }

        // 4. Validity period check (E1)
        $validityDays = $this->settings->get('prescriptions.validity_days');
        if ($validityDays === null) {
            // Setting is "Not set" — block until PM configures it
            throw new RuntimeException(__('prescriptions.validity_days_not_configured'));
        }

        $validityDays = (int) $validityDays;
        $daysSincePrescription = abs((int) now()->diffInDays($parsedDate, false));
        // diffInDays with false returns positive if past
        $daysSincePrescription = abs((int) Carbon::now()->startOfDay()->diffInDays($parsedDate->startOfDay(), false));
        if ($daysSincePrescription < 0) {
            $daysSincePrescription = abs($daysSincePrescription);
        }
        // recalculate cleanly
        $ageInDays = (int) abs(Carbon::now()->startOfDay()->diffInDays($parsedDate->copy()->startOfDay()));
        if ($ageInDays > $validityDays) {
            throw new RuntimeException(__('prescriptions.prescription_too_old', [
                'days' => $validityDays,
                'age' => $ageInDays,
            ]));
        }

        // 5. Validate product lines (only Rx and controlled)
        $validatedLines = [];
        foreach ($lines as $line) {
            $product = Product::find($line['product_id'] ?? null);
            if (! $product) {
                throw new InvalidArgumentException(__('prescriptions.product_not_found'));
            }

            if (! in_array($product->classification, ['rx', 'controlled'], true)) {
                throw new InvalidArgumentException(__('prescriptions.only_rx_controlled_allowed', [
                    'product' => $product->brand ?? $product->name,
                ]));
            }

            $dose = trim((string) ($line['dose'] ?? ''));
            if ($dose === '') {
                throw new InvalidArgumentException(__('prescriptions.dose_required_for_product', [
                    'product' => $product->brand ?? $product->name,
                ]));
            }

            $qty = (int) ($line['quantity'] ?? 0);
            if ($qty <= 0) {
                throw new InvalidArgumentException(__('prescriptions.quantity_must_be_positive'));
            }

            $validatedLines[] = compact('product', 'dose', 'qty');
        }

        return DB::transaction(function () use ($user, $prescriberName, $prescriberRegNo, $parsedDate, $patientData, $validatedLines, $scanPath) {
            // 6. Resolve or create customer (A1)
            $customer = $this->resolveCustomer($user, $patientData);

            // 7. Generate unique prescription number
            $prescriptionNumber = DocumentSequence::next('prescription', 'RX');

            // 8. Create prescription (registered → pending_verification)
            $prescription = Prescription::create([
                'prescription_number' => $prescriptionNumber,
                'customer_id' => $customer->id,
                'prescriber_name' => $prescriberName,
                'prescriber_registration_no' => $prescriberRegNo,
                'prescription_date' => $parsedDate->toDateString(),
                'status' => Prescription::STATUS_REGISTERED,
                'scan_path' => $scanPath,
                'created_by' => $user->id,
            ]);

            // 9. Add lines
            foreach ($validatedLines as $line) {
                $prescription->lines()->create([
                    'product_id' => $line['product']->id,
                    'dose' => $line['dose'],
                    'quantity_prescribed' => $line['qty'],
                    'quantity_dispensed' => 0,
                ]);
            }

            // 10. Log initial status (registered → pending_verification)
            $prescription->statusHistory()->create([
                'from_status' => null,
                'to_status' => Prescription::STATUS_REGISTERED,
                'user_id' => $user->id,
                'role_used' => \App\Services\ActingRole::for($user)?->value,
                'notes' => 'Prescription registered',
            ]);

            // 11. Transition to pending verification
            $prescription->transitionStatus(Prescription::STATUS_PENDING_VERIFICATION, $user, 'Awaiting clinical verification');

            // 12. Audit log
            AuditLogger::record(
                action: 'prescription.registered',
                subject: $prescription,
                old: [],
                new: [
                    'prescription_number' => $prescriptionNumber,
                    'customer_id' => $customer->id,
                    'prescriber' => $prescriberName,
                    'prescriber_reg' => $prescriberRegNo,
                    'line_count' => count($validatedLines),
                ],
                user: $user,
                description: "Prescription {$prescriptionNumber} registered for {$customer->name} by {$user->name}"
            );

            return $prescription->load(['lines.product', 'customer', 'statusHistory']);
        });
    }

    /**
     * @param  array{name?: string, phone?: string, customer_id?: int|null}  $patientData
     */
    private function resolveCustomer(User $user, array $patientData): Customer
    {
        if (! empty($patientData['customer_id'])) {
            $customer = Customer::find($patientData['customer_id']);
            if ($customer) {
                return $customer;
            }
        }

        // Create inline if name and phone provided
        if (! empty($patientData['name']) && ! empty($patientData['phone'])) {
            // Check by phone first
            $existing = Customer::where('phone', trim($patientData['phone']))->first();
            if ($existing) {
                return $existing;
            }

            return $this->saveCustomer->execute($user, [
                'name' => $patientData['name'],
                'phone' => $patientData['phone'],
            ]);
        }

        throw new InvalidArgumentException(__('prescriptions.patient_required'));
    }
}

