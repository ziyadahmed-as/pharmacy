# UC-25  Manage customer record

| Field | Value |
|---|---|
| Stage | 4. Prescriptions and dispensing |
| Build order | 22 |
| Priority | Must (Phase 1) |
| Depends on | UC-33 |
| Suggested branch | `uc-25-manage-customer-record` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-25 Manage customer record. Depends on: UC-33. BRD: FR-5.1, 5.2, 5.6; OQ-07. AC-26.

STEP 1 Data: `customers` (name, phone, address, birth_date nullable, consent_given, consent_at, allergies encrypted, chronic_conditions encrypted), `customer_health_views` (who viewed which record and when).
STEP 2 Actions `SaveCustomer`, `RecordConsent`, `ViewHealthFields`. Allergies and chronic conditions can be saved only with consent recorded (E1). Every authorised view of health fields writes `customer.health.viewed` (E2).
STEP 3 Search by name or phone; duplicate warning by phone. Purchase history panel from sales (FR-5.2).
STEP 4 Permissions: pharmacy_staff create and update; PM and Owner view; health fields visible only to roles allowed by the BRD matrix (A1: hidden for others); SA none.
STEP 5 Livewire: search, create/edit form object, health section with consent toggle, history tab. Hosting location and breach rules are tracked in OQ-07: add a `docs/decisions/` note, no code guess.

TESTS: health fields hidden for users without permission, also by direct URL (AC-26); saving health fields without consent refused; every view logged; fields encrypted in the database; duplicate phone warning; purchase history correct.

DONE WHEN: all green.
````
