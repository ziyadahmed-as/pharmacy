# UC-32  View owner dashboard

| Field | Value |
|---|---|
| Stage | 7. Growth features (Phase 2) |
| Build order | 40 |
| Priority | Should (Phase 2) |
| Depends on | UC-30 |
| Suggested branch | `uc-32-view-owner-dashboard` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-32 View owner dashboard. Depends on: UC-30. BRD: FR-7.7, 9.2; NFR-5.

STEP 1 Tiles from existing queries: sales today, top sellers, low stock, expiring items, cash and trends. Cost and profit tiles only when the cost extension from UC-29 exists, and only for the Owner.
STEP 2 Livewire dashboard, responsive for desktop and mobile; each tile opens its report; lazy-load tiles (`#[Lazy]`).
STEP 3 Optional daily summary by email or SMS (FR-9.2) through the same gateway interface.
STEP 4 Owner only.

TESTS: tile figures equal report figures; mobile layout renders; other roles denied; lazy loading does not block the page.

DONE WHEN: all green.
````
