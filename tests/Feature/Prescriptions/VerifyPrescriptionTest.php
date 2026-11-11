<?php

use App\Actions\Prescriptions\RegisterPrescription;
use App\Actions\Prescriptions\RejectPrescription;
use App\Actions\Prescriptions\VerifyPrescription;
use App\Livewire\Prescriptions\Show as PrescriptionShow;
use App\Models\AuditLog;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\Staff;
use App\Models\StaffLicence;
use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

/**
 * UC-20: Verify prescription
 * BRD: FR-4.3, 4.8; BR-03, BR-12; OQ-09. AC-13.
 */
beforeEach(function () {
    seedRoles();
    app(SettingsRepository::class)->put('profile.name', 'Test Pharmacy');
    app(SettingsRepository::class)->put('prescriptions.validity_days', 30);
});

function createLicensedUser(string $category = 'Pharmacist', ?string $expiresAt = null): User
{
    $user = ps();
    $staff = makeStaff(['user_id' => $user->id]);

    StaffLicence::create([
        'staff_id' => $staff->id,
        'number' => 'LIC-'.uniqid(),
        'issuing_body' => 'EFDA',
        'category' => $category,
        'issued_at' => now()->subMonths(6)->toDateString(),
        'expires_at' => $expiresAt ?? now()->addYear()->toDateString(),
    ]);

    return $user->refresh();
}

function createPendingPrescription(User $user): Prescription
{
    static $seq = 1;
    $num = $seq++;

    $product = Product::create([
        'sku' => "RX-VERIFY-{$num}",
        'brand' => "Ciprofloxacin {$num}00mg",
        'generic_name' => 'Ciprofloxacin',
        'strength' => "{$num}00mg",
        'dosage_form' => 'tablet',
        'classification' => 'rx',
        'storage_condition' => 'room_temperature',
        'is_active' => true,
    ]);

    return app(RegisterPrescription::class)->execute(
        user: $user,
        prescriberName: 'Dr. Prescriber',
        prescriberRegNo: 'DOC-555',
        prescriptionDate: now()->subDays(1)->toDateString(),
        patientData: [
            'name' => 'John Patient',
            'phone' => '+251911998877',
        ],
        lines: [
            [
                'product_id' => $product->id,
                'dose' => '500mg bid x 5d',
                'quantity' => 10,
            ],
        ],
    );
}

it('licensed staff verifies prescription and records who and when (FR-4.3, AC-13)', function () {
    $registrar = ps();
    $verifier = createLicensedUser('Pharmacist');
    $prescription = createPendingPrescription($registrar);

    expect($prescription->status)->toBe(Prescription::STATUS_PENDING_VERIFICATION);

    $verified = app(VerifyPrescription::class)->execute($verifier, $prescription, 'Clinical checks passed');

    expect($verified->status)->toBe(Prescription::STATUS_VERIFIED)
        ->and($verified->verified_by)->toBe($verifier->id)
        ->and($verified->verified_at)->not->toBeNull();

    // Status history
    $latestHistory = $verified->statusHistory->last();
    expect($latestHistory->to_status)->toBe(Prescription::STATUS_VERIFIED)
        ->and($latestHistory->user_id)->toBe($verifier->id)
        ->and($latestHistory->notes)->toBe('Clinical checks passed');

    // Audit log
    expect(AuditLog::where('action', 'prescription.verified')->where('auditable_id', (string) $prescription->id)->exists())->toBeTrue();
});

it('refuses verification when licence has expired (AC-13, E1)', function () {
    $registrar = ps();
    // Licence expired yesterday
    $verifier = createLicensedUser('Pharmacist', now()->subDay()->toDateString());
    $prescription = createPendingPrescription($registrar);

    expect(function () use ($verifier, $prescription) {
        app(VerifyPrescription::class)->execute($verifier, $prescription);
    })->toThrow(RuntimeException::class, __('prescriptions.verify_refused_licence'));

    // Status remains unchanged
    expect($prescription->refresh()->status)->toBe(Prescription::STATUS_PENDING_VERIFICATION);

    // Audit entry prescription.verify.refused exists
    expect(AuditLog::where('action', 'prescription.verify.refused')->where('auditable_id', (string) $prescription->id)->exists())->toBeTrue();
});

it('refuses verification when licence category is disallowed by setting (OQ-09, E1)', function () {
    $registrar = ps();
    // Staff holds Technician licence
    $verifier = createLicensedUser('Technician');
    $prescription = createPendingPrescription($registrar);

    // Only Pharmacists allowed by setting
    app(SettingsRepository::class)->put('prescriptions.licence_categories_allowed', ['Pharmacist']);

    expect(function () use ($verifier, $prescription) {
        app(VerifyPrescription::class)->execute($verifier, $prescription);
    })->toThrow(RuntimeException::class, __('prescriptions.verify_refused_licence'));

    // Refusal audit logged
    expect(AuditLog::where('action', 'prescription.verify.refused')->where('auditable_id', (string) $prescription->id)->exists())->toBeTrue();
});

it('allows verification by technician when no category restriction is set (OQ-09 default)', function () {
    $registrar = ps();
    $verifier = createLicensedUser('Technician');
    $prescription = createPendingPrescription($registrar);

    // Empty/default setting: any valid licence can verify
    app(SettingsRepository::class)->put('prescriptions.licence_categories_allowed', []);

    $verified = app(VerifyPrescription::class)->execute($verifier, $prescription);
    expect($verified->status)->toBe(Prescription::STATUS_VERIFIED);
});

it('staff without a staff record cannot verify', function () {
    $registrar = ps();
    $unlinkedUser = ps(); // has role pharmacy_staff but no staff record
    $prescription = createPendingPrescription($registrar);

    expect(function () use ($unlinkedUser, $prescription) {
        app(VerifyPrescription::class)->execute($unlinkedUser, $prescription);
    })->toThrow(RuntimeException::class);

    expect(AuditLog::where('action', 'prescription.verify.refused')->exists())->toBeTrue();
});

it('rejects prescription with reason and logs suspect-prescription event (FR-4.12, A1)', function () {
    $registrar = ps();
    $verifier = createLicensedUser('Pharmacist');
    $prescription = createPendingPrescription($registrar);

    $reason = 'Suspect altered quantity on prescription';
    $rejected = app(RejectPrescription::class)->execute($verifier, $prescription, $reason);

    expect($rejected->status)->toBe(Prescription::STATUS_REJECTED)
        ->and($rejected->rejected_by)->toBe($verifier->id)
        ->and($rejected->rejected_at)->not->toBeNull()
        ->and($rejected->rejection_reason)->toBe($reason);

    // Status history
    $latest = $rejected->statusHistory->last();
    expect($latest->to_status)->toBe(Prescription::STATUS_REJECTED)
        ->and($latest->notes)->toBe($reason);

    // Audit logs: both rejection and suspect event
    expect(AuditLog::where('action', 'prescription.rejected')->where('auditable_id', (string) $prescription->id)->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'prescription.suspect')->where('auditable_id', (string) $prescription->id)->exists())->toBeTrue();
});

it('requires a rejection reason', function () {
    $registrar = ps();
    $verifier = createLicensedUser('Pharmacist');
    $prescription = createPendingPrescription($registrar);

    expect(function () use ($verifier, $prescription) {
        app(RejectPrescription::class)->execute($verifier, $prescription, '');
    })->toThrow(InvalidArgumentException::class);
});

it('system admin cannot verify or reject prescriptions', function () {
    $admin = sa();
    $registrar = ps();
    $prescription = createPendingPrescription($registrar);

    expect(fn () => app(VerifyPrescription::class)->execute($admin, $prescription))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(RejectPrescription::class)->execute($admin, $prescription, 'Reason'))
        ->toThrow(AuthorizationException::class);
});

it('Livewire Show component handles verification checklist and verifies prescription', function () {
    $registrar = ps();
    $verifier = createLicensedUser('Pharmacist');
    $prescription = createPendingPrescription($registrar);

    Livewire::actingAs($verifier)
        ->test(PrescriptionShow::class, ['prescription' => $prescription])
        ->assertSee(__('prescriptions.verification_checks'))
        // Attempting to verify without completing all checks fails with message
        ->call('verify')
        ->assertSee(__('prescriptions.all_checks_required'))
        // Check all 5 checks
        ->set('checkAuthenticity', true)
        ->set('checkCompleteness', true)
        ->set('checkDrug', true)
        ->set('checkDose', true)
        ->set('checkQuantity', true)
        ->call('verify')
        ->assertHasNoErrors()
        ->assertSee(__('prescriptions.prescription_verified_successfully'));

    expect($prescription->refresh()->status)->toBe(Prescription::STATUS_VERIFIED);
});

it('Livewire Show component handles rejection modal and rejects prescription', function () {
    $registrar = ps();
    $verifier = createLicensedUser('Pharmacist');
    $prescription = createPendingPrescription($registrar);

    Livewire::actingAs($verifier)
        ->test(PrescriptionShow::class, ['prescription' => $prescription])
        ->call('openRejectModal')
        ->set('rejectionReason', 'Invalid prescriber registration number')
        ->call('reject')
        ->assertHasNoErrors()
        ->assertSee(__('prescriptions.prescription_rejected_successfully'));

    expect($prescription->refresh()->status)->toBe(Prescription::STATUS_REJECTED)
        ->and($prescription->rejection_reason)->toBe('Invalid prescriber registration number');
});
