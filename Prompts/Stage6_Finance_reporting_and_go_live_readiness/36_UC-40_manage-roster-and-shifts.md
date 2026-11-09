# UC-40  Manage roster and shifts

| Field | Value |
|---|---|
| Stage | 6. Finance, reporting and go-live readiness |
| Build order | 36 |
| Priority | Must (Phase 1) |
| Depends on | UC-39 |
| Suggested branch | `uc-40-manage-roster-and-shifts` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-40 Manage roster and shifts. Depends on: UC-39. BRD: FR-10.4.

STEP 1 Data: `roster_entries` (staff, date, start, end, till nullable, note).
STEP 2 Action `SaveRosterEntry` (PM): assign staff to shifts and tills for a week or month; E1: refuse a second cash shift on the same till at overlapping times; warn on a staff member scheduled twice at once.
STEP 3 Show who is on duty today (dashboard widget and page). Staff view their own shifts (read-only).
STEP 4 Publishing: a roster is published when saved; changes are audited.
STEP 5 Livewire: week and month grid, drag-free simple assignment form, "On duty today" widget.

TESTS: assignment saved; overlapping till assignment refused (E1); staff see only their own entries; PM edits; ended or on-leave staff cannot be assigned; audit entries.

DONE WHEN: all green; Stage 6 milestone and the go-live checklist (data migration, full stock count, restore test, parallel run, training) reviewed with the BA.
````
