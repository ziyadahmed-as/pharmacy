# 0025. Prescriber Register

## Context
Previously, prescriptions and controlled drug registers stored prescriber details as unnormalized strings (`prescriber_name`, `prescriber_registration_no`).
To meet pharmacy regulation standards, auditability, and safety requirements:
1. Prescribers must be modeled as structured, registered entities: `prescribers` (`id`, `name`, `licence_number` unique, `facility`, `phone`, `is_active`).
2. Prescriptions and controlled drug register entries must link to `prescriber_id` while maintaining `prescriber_name` as an immutable snapshot captured at registration.
3. Existing prescriber names must be migrated and deduplicated into normalized prescriber records.
4. Prescriptions must support selecting registered prescribers via autocomplete search (name or licence number) and quick-adding new prescribers directly inside the registration flow.
5. Inactive prescribers must trigger a warning upon selection.
6. Prescriptions containing controlled substances strictly require a prescriber holding a valid, non-empty licence number.
7. Prescriber information and licence numbers must be visible on prescription views, dispensing labels, controlled drug register screens, CSV/PDF exports, and compliance reports.

## Decision
- **Schema & Migration**: Created `prescribers` table with unique constraint on `licence_number`. Added `prescriber_id` foreign keys with `restrictOnDelete` to `prescriptions` and `controlled_drug_register`. Implemented data migration in `2026_11_15_000004_create_prescribers_table.php` deduplicating legacy records by normalized name and linking existing prescriptions and register entries. Preserved database immutability triggers on `controlled_drug_register`.
- **Model**: Created `App\Models\Prescriber` with `active` and `search` query scopes, and established `prescriber` belongs-to relationships on `Prescription` and `ControlledDrugRegisterEntry`.
- **Registration & Validation**: Updated `RegisterPrescription` action to accept optional `prescriberId`, snapshot name and licence number onto the prescription, and strictly enforce that any prescription with controlled drugs requires a prescriber with a licence number (`InvalidArgumentException`).
- **Livewire UI**:
  - Built `App\Livewire\Prescribers\Index` (`/prescribers`) with search, active/inactive filters, create/edit modal with unique licence validation, active/deactive toggles, and audit trails (`prescriber.created`, `prescriber.updated`, `prescriber.status_changed`).
  - Added search autocomplete and a quick-add modal directly inside `App\Livewire\Prescriptions\Register`, including an alert warning when an inactive prescriber is chosen.
- **Views, Labels, Exports & Reports**:
  - Prescription View (`Prescriptions\Show`): Displays prescriber name, licence number, facility, and active status badge.
  - Dispensing Label (`Dispense\Show`): Added printable dispensing label card showing patient, Rx number, prescriber name, and prescriber licence number.
  - Controlled Drug Register (`ControlledDrugs\Register`): Displays prescriber licence number directly in the prescriber column.
  - Export & Compliance Reports: `ControlledDrugExportController`, `ComplianceControlledDrugsReport`, and `CompliancePrescriptionsReport` include prescriber licence numbers.
- **Bilingual & Testing**: Provided full English and Amharic translations across all new UI text and alerts. Validated with 7 dedicated feature tests (`tests/Feature/Prescriptions/PrescriberRegisterTest.php`) and verified the entire test suite (545 tests, 2,477 assertions passing).

## Status
Accepted and verified through automated tests (`tests/Feature/Prescriptions/PrescriberRegisterTest.php`).
