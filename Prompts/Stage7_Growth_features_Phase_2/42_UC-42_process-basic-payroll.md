# UC-42  Process basic payroll

| Field | Value |
|---|---|
| Stage | 7. Growth features (Phase 2) |
| Build order | 42 |
| Priority | Should (Phase 2) |
| Depends on | UC-41, UC-28 |
| Suggested branch | `uc-42-process-basic-payroll` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-42 Process basic payroll. Depends on: UC-41, UC-28. BRD: FR-10.7, 10.8, 10.9; D-10; BR-13.

STEP 1 Data: `salary_profiles` (staff, base salary, allowances), `payroll_runs` (month, status), `payroll_lines` (staff, gross, allowances, overtime, deductions, advances, net), `salary_advances`.
STEP 2 Action `PreparePayroll` for a month: list each staff member with salary, allowances, overtime from attendance, deductions and salary advances; the user reviews and confirms; Owner approves if the PM prepared it.
STEP 3 Generate monthly payslips (PDF) and record the payment; post the salary expense to UC-28 as category salaries.
STEP 4 E1: payroll tax is out of scope; handle only gross and listed deductions.
STEP 5 Visibility: pay data only for Owner, PM and an authorised accountant (BR-13); staff see their own payslip only; SA none.
STEP 6 Optional later: commission or bonus from sales targets (FR-10.8) and staff performance report (FR-10.9) as separate follow-up tasks; do not build now.

TESTS: totals per staff correct; advances deducted; payslip matches the run; confirm and payment recorded; salary expense posted once; pay data hidden from staff and SA.

DONE WHEN: all green.
````
