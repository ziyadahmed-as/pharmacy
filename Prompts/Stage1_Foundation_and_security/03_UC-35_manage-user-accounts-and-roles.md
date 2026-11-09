# UC-35  Manage user accounts and roles

| Field | Value |
|---|---|
| Stage | 1. Foundation and security |
| Build order | 3 |
| Priority | Must (Phase 1) |
| Depends on | UC-33, UC-39 |
| Suggested branch | `uc-35-manage-user-accounts-and-roles` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-35 Manage user accounts and roles. Depends on: UC-33, UC-39. BRD: FR-8.1, 8.2, 8.5, 8.8, 8.9, 10.2; BR-14. AC-28, AC-32. See conflict C-4.

BUILD-ORDER NOTE: UC-34 (the approval engine) is built next. So implement every account change as an `ApprovableAction` (`ActivateUser`, `ChangeUserRoles`, `DeactivateUser`, `ReactivateUser`) and request it through an interface `ApprovalGateway` with one TEMPORARY implementation that stores a pending record and exposes a manual approve method used only in tests. UC-34 will replace the binding with the real engine.

STEP 1 Data: users.status (pending_approval|active|deactivated), `role_assignment_history` (user, old roles, new roles, actor, approver, time).
STEP 2 Action `CreateUserAccount`: SA picks an active staff record that has no user, sets username and role(s). The account is `pending_approval` and cannot log in. Routing rule to record: PM approves; Owner approves if the change concerns the PM; PM approves if it concerns the Owner.
STEP 3 On approval: activate, generate a random temporary password, show it ONCE to the SA, never log or store it in plain text, set must_change_password. Link `staff.user_id`.
STEP 4 `ChangeUserRoles` (A1), `DeactivateUser` and `ReactivateUser` (A2) through the same gateway; deactivation calls `EndUserSessions`; history kept.
STEP 5 `RoleCombinationRule`: system_admin with any other role is refused with no exception (C-4). Other combinations (A3) allowed; permissions add up.
STEP 6 Livewire: users list (status filters), create wizard, role editor, deactivate/reactivate with reason, "waiting for approval" state, one-time password reveal modal with copy button.
STEP 7 SA must not reach patient, prescription, sales, cost or financial screens: add a route test list that grows with each stage.

TESTS: account unusable until approved (AC-28); request concerning the PM routes to the Owner; SA plus another role refused both ways; temporary password absent from audit and database; role history rows; deactivation ends sessions and blocks login (AC-32); a pharmacy_staff user can sell, count and request but not approve (role test for AC-34).

DONE WHEN: all green; the temporary gateway is documented so UC-34 can replace it.
````
