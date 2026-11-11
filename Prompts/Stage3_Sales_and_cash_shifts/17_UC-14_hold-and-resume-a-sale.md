# UC-14  Hold and resume a sale

| Field | Value |
|---|---|
| Stage | 3. Sales and cash shifts |
| Build order | 17 |
| Priority | Must (Phase 1) |
| Depends on | UC-13 |
| Suggested branch | `uc-14-hold-and-resume-a-sale` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-14 Hold and resume a sale. Depends on: UC-13. BRD: FR-3.6. AC-23.

STEP 1 Data: `held_sales` (user, shift, cart json, held_at, status held|resumed|discarded).
STEP 2 Actions `HoldSale` (stores the cart, does NOT reserve stock) and `ResumeSale` (reloads the cart and re-validates every line for stock, expiry, quarantine and current price through the same guard and resolver as UC-13).
STEP 3 A1: any invalid line (sold out, expired, price changed) is flagged and staff remove or adjust it before payment. E1: another sale may use the same stock while a sale is held.
STEP 4 Livewire: Hold button, held list on the POS, Resume and Discard actions; payment continues in the UC-13 flow.
STEP 5 Held sales older than the end of the day are listed to the PM at daily closing (UC-18) and cannot be completed on another shift without resume.


TESTS: hold stores without reserving stock; resume re-validates; changed price and sold-out line flagged (A1); another sale can take the stock first (E1); cart survives a page refresh; staff only see their own held sales (AC-23).

DONE WHEN: all green.
````
