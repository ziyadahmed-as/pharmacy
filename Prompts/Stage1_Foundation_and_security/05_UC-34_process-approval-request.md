# UC-34  Process approval request

| Field | Value |
|---|---|
| Stage | 1. Foundation and security |
| Build order | 5 |
| Priority | Must (Phase 1) |
| Depends on | UC-33, UC-35 |
| Suggested branch | `uc-34-process-approval-request` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-34 Process approval request as the REUSABLE approval engine. Depends on: UC-33, UC-35. BRD: FR-8.7, 8.2, 8.8; BR-14. AC-28.

GOAL: one engine for every sensitive approval; later use cases only register a handler.

STEP 1 Data: `approval_requests` (type, subject morph, payload json, summary, initiator_id and role, approver_id and role, status pending|approved|rejected|expired|cancelled, initiator_comment, decision_comment, expires_at, decided_at).
STEP 2 `ApprovalService`: `request()`, `approve()`, `reject()`, `cancel()`. Execution of the underlying action and the status change happen in one DB transaction with a row lock; approving twice runs the handler once; if the handler fails the request stays pending and the error is logged.
STEP 3 `ApprovalHandler` interface (`type()`, `eligibleApproverRoles()`, `onApproved()`, `onRejected()`) with a tagged registry. Register the handlers for UC-35 (`user.create`, `user.roles.update`, `user.deactivate`, `user.reactivate`) and replace the temporary `ApprovalGateway` from UC-35 with the real engine; re-point its tests. Add a demo handler for tests.
STEP 4 Routing: pharmacy_staff request goes to the PM; PM request goes to the Owner; Owner request goes to the PM (A2); SA requests: account/role changes to the PM (Owner if it concerns the PM), restore to PM or Owner, settings to PM or Owner.
STEP 5 Rules: approver is never the initiator whatever role is used (E1); approve and reject need `ConfirmWithPassword` (E2); reject needs a comment (A1); expiry from setting `approvals.expiry_hours` default 72 and hourly command `approvals:expire` (E3).
STEP 6 Livewire: `Approvals\Queue` (pending items the user may decide, filters, sidebar badge), `Approvals\Show` (details, initiator, reason, payload preview, approve/reject), "My requests" tab. Database notifications to approvers on creation and to the initiator on decision.
STEP 7 Policy `ApprovalRequestPolicy`: eligible role, not initiator, pending, not expired.

TESTS: initiator cannot approve (AC-28); wrong password or no re-authentication refused (AC-28); routing per role; expired request cannot be approved; double approval runs once; handler failure leaves it pending; all decisions audited; UC-35 flows now go through the engine.

DONE WHEN: all green; README section "How to add an approval type".
````
