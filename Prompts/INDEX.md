# PMS Build Prompts: Index (one file per use case)

Stack: Laravel 12 + Livewire 3. Start with `AGENTS.md`, then run the files in this order. Each file is a separate, self-contained prompt.

| Order | Use case | Name | Stage | File | Depends on |
|---|---|---|---|---|---|
| 0 | S0 | Foundation | 1 | `Stage1_Foundation_and_security/00_S0_Foundation.md` | None |
| 1 | UC-33 | Log in and log out | 1 | `Stage1_Foundation_and_security/01_UC-33_log-in-and-log-out.md` | S0 |
| 2 | UC-39 | Manage staff record and licence | 1 | `Stage1_Foundation_and_security/02_UC-39_manage-staff-record-and-licence.md` | UC-33 |
| 3 | UC-35 | Manage user accounts and roles | 1 | `Stage1_Foundation_and_security/03_UC-35_manage-user-accounts-and-roles.md` | UC-33, UC-39 |
| 4 | UC-36 | Reset password and unlock account | 1 | `Stage1_Foundation_and_security/04_UC-36_reset-password-and-unlock-account.md` | UC-35 |
| 5 | UC-34 | Process approval request | 1 | `Stage1_Foundation_and_security/05_UC-34_process-approval-request.md` | UC-33, UC-35 |
| 6 | UC-31 | Review audit log | 1 | `Stage1_Foundation_and_security/06_UC-31_review-audit-log.md` | UC-33 |
| 7 | UC-38 | Configure settings and pharmacy profile | 1 | `Stage1_Foundation_and_security/07_UC-38_configure-settings-and-pharmacy-profile.md` | UC-33, UC-34 |
| 8 | UC-01 | Manage product catalogue | 2 | `Stage2_Catalogue_and_stock_base/08_UC-01_manage-product-catalogue.md` | UC-33 |
| 9 | UC-02 | Maintain selling price | 2 | `Stage2_Catalogue_and_stock_base/09_UC-02_maintain-selling-price.md` | UC-01 |
| 10 | UC-03 | Import products from file | 2 | `Stage2_Catalogue_and_stock_base/10_UC-03_import-products-from-file.md` | UC-01 |
| 11 | UC-04 | Receive stock | 2 | `Stage2_Catalogue_and_stock_base/11_UC-04_receive-stock.md` | UC-01, UC-34, UC-38 |
| 12 | UC-11 | View stock and ledger | 2 | `Stage2_Catalogue_and_stock_base/12_UC-11_view-stock-and-ledger.md` | UC-04 |
| 13 | UC-05 | Monitor stock alerts and reorder list | 2 | `Stage2_Catalogue_and_stock_base/13_UC-05_monitor-stock-alerts-and-reorder-list.md` | UC-04, UC-38 |
| 14 | UC-08 | Quarantine stock | 2 | `Stage2_Catalogue_and_stock_base/14_UC-08_quarantine-stock.md` | UC-04 |
| 15 | UC-12 | Open shift | 3 | `Stage3_Sales_and_cash_shifts/15_UC-12_open-shift.md` | UC-33, UC-38 |
| 16 | UC-13 | Sell OTC and non-drug items | 3 | `Stage3_Sales_and_cash_shifts/16_UC-13_sell-otc-and-non-drug-items.md` | UC-02, UC-04, UC-12 |
| 17 | UC-14 | Hold and resume a sale | 3 | `Stage3_Sales_and_cash_shifts/17_UC-14_hold-and-resume-a-sale.md` | UC-13 |
| 18 | UC-17 | Close shift | 3 | `Stage3_Sales_and_cash_shifts/18_UC-17_close-shift.md` | UC-12, UC-13 |
| 19 | UC-15 | Void a sale | 3 | `Stage3_Sales_and_cash_shifts/19_UC-15_void-a-sale.md` | UC-13, UC-34 |
| 20 | UC-16 | Process customer return and refund | 3 | `Stage3_Sales_and_cash_shifts/20_UC-16_process-customer-return-and-refund.md` | UC-13, UC-08, UC-34 |
| 21 | UC-18 | Close and lock the day | 3 | `Stage3_Sales_and_cash_shifts/21_UC-18_close-and-lock-the-day.md` | UC-17 |
| 22 | UC-25 | Manage customer record | 4 | `Stage4_Prescriptions_and_dispensing/22_UC-25_manage-customer-record.md` | UC-33 |
| 23 | UC-19 | Register prescription | 4 | `Stage4_Prescriptions_and_dispensing/23_UC-19_register-prescription.md` | UC-25, UC-01 |
| 24 | UC-20 | Verify prescription | 4 | `Stage4_Prescriptions_and_dispensing/24_UC-20_verify-prescription.md` | UC-19, UC-39 |
| 25 | UC-21 | Dispense prescription | 4 | `Stage4_Prescriptions_and_dispensing/25_UC-21_dispense-prescription.md` | UC-20, UC-13 |
| 26 | UC-22 | Dispense controlled drug | 4 | `Stage4_Prescriptions_and_dispensing/26_UC-22_dispense-controlled-drug.md` | UC-21 |
| 27 | UC-23 | Search prescription and patient history | 4 | `Stage4_Prescriptions_and_dispensing/27_UC-23_search-prescription-and-patient-history.md` | UC-19 |
| 28 | UC-06 | Request stock adjustment | 5 | `Stage5_Inventory_control_and_compliance/28_UC-06_request-stock-adjustment.md` | UC-04, UC-34 |
| 29 | UC-07 | Perform stocktake | 5 | `Stage5_Inventory_control_and_compliance/29_UC-07_perform-stocktake.md` | UC-04, UC-06 |
| 30 | UC-09 | Dispose of stock, including return to supplier | 5 | `Stage5_Inventory_control_and_compliance/30_UC-09_dispose-of-stock-including-return-to-supplier.md` | UC-08, UC-34 |
| 31 | UC-10 | Manage recall | 5 | `Stage5_Inventory_control_and_compliance/31_UC-10_manage-recall.md` | UC-08, UC-09 |
| 32 | UC-37 | Back up and restore data | 6 | `Stage6_Finance_reporting_and_go_live_readiness/32_UC-37_back-up-and-restore-data.md` | UC-33, UC-34 |
| 33 | UC-28 | Record operating expense | 6 | `Stage6_Finance_reporting_and_go_live_readiness/33_UC-28_record-operating-expense.md` | UC-33 |
| 34 | UC-29 | View cash and revenue summaries | 6 | `Stage6_Finance_reporting_and_go_live_readiness/34_UC-29_view-cash-and-revenue-summaries.md` | UC-18, UC-28 |
| 35 | UC-30 | Generate and export a report | 6 | `Stage6_Finance_reporting_and_go_live_readiness/35_UC-30_generate-and-export-a-report.md` | UC-04, UC-13, UC-21 |
| 36 | UC-40 | Manage roster and shifts | 6 | `Stage6_Finance_reporting_and_go_live_readiness/36_UC-40_manage-roster-and-shifts.md` | UC-39 |
| 37 | UC-24 | Dispense in parts and refill | 7 | `Stage7_Growth_features_Phase_2/37_UC-24_dispense-in-parts-and-refill.md` | UC-21 |
| 38 | UC-26 | Sell on credit and collect payment | 7 | `Stage7_Growth_features_Phase_2/38_UC-26_sell-on-credit-and-collect-payment.md` | UC-13, UC-25 |
| 39 | UC-27 | Send refill reminders | 7 | `Stage7_Growth_features_Phase_2/39_UC-27_send-refill-reminders.md` | UC-25 |
| 40 | UC-32 | View owner dashboard | 7 | `Stage7_Growth_features_Phase_2/40_UC-32_view-owner-dashboard.md` | UC-30 |
| 41 | UC-41 | Manage attendance and leave | 7 | `Stage7_Growth_features_Phase_2/41_UC-41_manage-attendance-and-leave.md` | UC-39 |
| 42 | UC-42 | Process basic payroll | 7 | `Stage7_Growth_features_Phase_2/42_UC-42_process-basic-payroll.md` | UC-41, UC-28 |

## Stages, milestones and decisions needed first

| Stage | Name | Milestone | Decisions needed before this stage |
|---|---|---|---|
| 1 | Foundation and security | Users sign in by role; approvals and the audit log work; pharmacy profile and settings are entered. | OQ-03 (retention and backup period). |
| 2 | Catalogue and stock base | Opening stock is loaded and visible by batch and expiry; alerts and quarantine work. | OQ-07 if customer data is loaded early. |
| 3 | Sales and cash shifts | The pharmacy can sell OTC and non-drug items, void, refund, close shifts and lock the day. | OQ-05 payment methods, OQ-06 refund limit and return window, OQ-08 offline POS, OQ-10 receipt and tax. |
| 4 | Prescriptions and dispensing | Prescriptions are registered, verified and dispensed; controlled drugs are recorded in the register. | OQ-01 controlled drugs, OQ-02 prescription rules, OQ-07 patient data, OQ-09 technician scope. |
| 5 | Inventory control and compliance | Adjustments, stocktake, disposal with certificate, and recall all work. | OQ-04 recall, disposal and storage duties. |
| 6 | Finance, reporting and go-live readiness | Reports, expenses and cash summaries work; backup and restore tested; ready for parallel run and go-live. | OQ-06 expense limit; C-1 cost recording before the profit and loss step of UC-29. |
| 7 | Growth features (Phase 2) | Credit, refills and reminders, dashboard, attendance, leave and payroll are available. | OQ-08 if offline mode is pulled in. |

Conflicts between the BRD and the use case file are listed in `AGENTS.md` section 12 (C-1 to C-9) with the default each prompt follows.
