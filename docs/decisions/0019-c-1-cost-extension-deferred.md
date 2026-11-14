# 0019. Conflict C-1: Cost Extension Deferred

Date: 2026-11-14
Author: Coding Agent
Use Case: UC-29 (View cash and revenue summaries)
Status: Accepted

## Context
BRD v2.0 references unit cost at receipt, profit and loss (FR-6.3), and valuation (FR-6.7).
However, Use Case specification v1.1 states cost of goods is not recorded (Conflict C-1 in `AGENTS.md` Section 12).
Prompt STEP 4 states:
"CONDITIONAL EXTENSION, build only after the BA confirms cost is kept (C-1, FR-6.3, 6.7, 7.3, D-07, AC-30): profit and loss with revenue, cost of goods at batch cost, gross profit, expenses and net profit; inventory valuation at batch cost with quarantine shown separately. Cost data is visible only to owner and PM through CostVisibility. If the BA says no, skip this step and record the decision."

## Decision
As the Business Analyst has not confirmed unit cost retention, the conditional profit and loss and inventory valuation extension is deferred.
UC-29 is delivered with cash by payment method, gross sales, refunds, net sales, recorded operating expenses, and daily reconciliation figures from locked days.
The screen and exported reports prominently display the E1 disclaimer:
"This is not a profit and loss statement. Cost of goods sold is not part of this view."

