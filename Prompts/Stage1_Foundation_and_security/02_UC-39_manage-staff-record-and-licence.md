# UC-39  Manage staff record and licence

| Field | Value |
|---|---|
| Stage | 1. Foundation and security |
| Build order | 2 |
| Priority | Must (Phase 1) |
| Depends on | UC-33 |
| Suggested branch | `uc-39-manage-staff-record-and-licence` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-39 Manage staff record and licence. Depends on: UC-33. BRD: FR-10.1 to 10.3, 9.1, 9.3; BR-12, BR-13. AC-13, AC-32.

GOAL: the Pharmacy Manager and Owner keep staff and licence data; expired licences block dispensing later.

STEP 1 Data: `staff` (name, photo path, phone, address, id_number encrypted cast, title, hire date, status active|on_leave|ended, end_date, emergency contact name and phone, nullable unique user_id). `staff_licences` (staff_id, number, issuing_body, category free text, issued_at nullable, expires_at; old rows kept as history).
STEP 2 Actions: `SaveStaffRecord`, `AddOrRenewLicence`, `EndEmployment` (status Ended, end date; if a user is linked, deactivate and call `EndUserSessions`). Never delete staff.
STEP 3 Model method `Staff::hasValidLicence(?CarbonInterface $on)` and service `LicenceGate::allows(User, action)` reading the licence; the category rule comes from a setting later (UC-38). Write and test it now.
STEP 4 Command `staff:licence-alerts` (daily): notify PM and OW when a licence expires within the configured days and when expired; no duplicate alerts for the same licence and threshold (use `Notifier`, FR-9.1).
STEP 5 Livewire: list (search, status filter, "licence expiring"), create/edit form object, licence sub-form, view page with licence history, private photo upload with size and type validation.
STEP 6 Visibility: only owner and pharmacy_manager see staff personal and pay data (BR-13); pharmacy_staff see only their own record without id_number; SA none.

TESTS: PM/OW create and edit; staff sees only own record; SA denied (also by URL); id_number encrypted in the database; hasValidLicence cases (valid, expired yesterday, expires today, none, renewed); EndEmployment disables the linked user and ends sessions (AC-32); alerts idempotent; expired licence flagged (E1).

DONE WHEN: all green; `LicenceGate` ready for UC-20 and UC-22.
````
