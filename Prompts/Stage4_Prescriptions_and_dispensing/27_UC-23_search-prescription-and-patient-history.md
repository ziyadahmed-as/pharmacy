# UC-23  Search prescription and patient history

| Field | Value |
|---|---|
| Stage | 4. Prescriptions and dispensing |
| Build order | 27 |
| Priority | Must (Phase 1) |
| Depends on | UC-19 |
| Suggested branch | `uc-23-search-prescription-and-patient-history` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-23 Search prescription and patient history. Depends on: UC-19. BRD: FR-4.12, 5.6, 7.4. AC-26.

STEP 1 Query object `PrescriptionSearch` by patient, prescriber, drug, date range and status; indexes for the filters.
STEP 2 Livewire `Prescriptions\Search` and `Prescriptions\Show` (lines, dispensing events, status history, scan). Show rejected and suspect prescriptions with a filter.
STEP 3 Every view of health data writes an audit entry `customer.health.viewed` or `prescription.viewed`.
STEP 4 E1: users without permission cannot open prescription or health fields; direct URL and Livewire calls are denied. SA never has access.
STEP 5 Export of the prescription log for owner and PM only (part of the compliance report in UC-30).

TESTS: search by each filter; opening a prescription is audited; unauthorised role denied by URL (AC-26); suspect filter works; status history visible.

DONE WHEN: all green; Stage 4 milestone demonstrable.
````
