# UC-27  Send refill reminders

| Field | Value |
|---|---|
| Stage | 7. Growth features (Phase 2) |
| Build order | 39 |
| Priority | Should (Phase 2) |
| Depends on | UC-25 |
| Suggested branch | `uc-27-send-refill-reminders` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-27 Send refill reminders. Depends on: UC-25. BRD: FR-5.5.

STEP 1 Data: `reminders` (customer, prescription line, due date, channel, status queued|sent|failed, sent_at).
STEP 2 Command builds the list of customers due for a refill using next refill dates; only customers with a phone number AND recorded consent to be contacted.
STEP 3 Staff review the list and send by SMS or messaging through a `MessageGateway` interface (choose the provider later; use a log driver now); record every send.
STEP 4 E1: a failed message is flagged for a call or retry.
STEP 5 Livewire: due list, send selected, failed list.

TESTS: only consented customers listed; send recorded; failure flagged; no health details inside the message text; staff only.

DONE WHEN: all green.
````
