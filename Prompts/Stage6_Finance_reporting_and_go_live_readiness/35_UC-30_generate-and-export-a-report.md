# UC-30  Generate and export a report

| Field | Value |
|---|---|
| Stage | 6. Finance, reporting and go-live readiness |
| Build order | 35 |
| Priority | Must (Phase 1) |
| Depends on | UC-04, UC-13, UC-21 |
| Suggested branch | `uc-30-generate-and-export-a-report` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-30 Generate and export a report. Depends on: UC-04, UC-13, UC-21. BRD: FR-7.1 to 7.6, 2.19, 4.12. AC-29.

STEP 1 Report registry: each report is a class (`ReportDefinition`) with name, permission, filters and a query. Types: sales (daily, weekly, monthly, annual; by product, category, staff member and payment method; returns and refunds), inventory (current stock, low stock, near-expiry, expired, batch tracking, ledger, disposals and supplier returns), compliance (controlled-drug register, prescription log and dispensing, recall report, disposal report incl. disposal loss by reason, product and period), audit (user activity, voids, refunds, adjustments, disposals, price changes).
STEP 2 Livewire `Reports\Index` and `Reports\Run`: choose type, date range and filters; the system checks the user's role before running; show the result; daily reports must render in under 5 seconds and one-year reports in under 10 (NFR-1).
STEP 3 Export to PDF and Excel and print; sensitive exports are logged (audit `report.exported`).
STEP 4 A1: pharmacy_staff see only operational reports and their own shift totals. E1: no access means denied, also by direct URL and Livewire call. SA sees no business report.
STEP 5 Cost and profit columns only for owner and PM; omit them from queries for others.

TESTS: each report type returns correct figures on seeded data; role matrix per report incl. direct URL (E1); staff limited to own shift (A1); PDF and Excel match the screen (AC-29); timing assertions on 200 sales per day for a year; export logged.

DONE WHEN: all green.
````
