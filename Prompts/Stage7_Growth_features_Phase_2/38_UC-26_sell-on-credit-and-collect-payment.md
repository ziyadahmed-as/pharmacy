# UC-26  Sell on credit and collect payment

| Field | Value |
|---|---|
| Stage | 7. Growth features (Phase 2) |
| Build order | 38 |
| Priority | Should (Phase 2) |
| Depends on | UC-13, UC-25 |
| Suggested branch | `uc-26-sell-on-credit-and-collect-payment` |

**How to use this file**
1. Put `AGENTS.md` (in the parent folder) in the root of your repository.
2. Make sure the use cases listed under "Depends on" are merged and green.
3. Create the branch above, paste the prompt below into your coding agent, and let it work.
4. Check the "DONE WHEN" line, run tests, Pint and Larastan, then merge.

## Prompt

````text
Follow AGENTS.md. Implement UC-26 Sell on credit and collect payment. Depends on: UC-13, UC-25. BRD: FR-5.3, 6.4; D-02. Update FR-3.4 payment methods: add Credit only for credit customers.

STEP 1 Data: `credit_accounts` (customer, limit, balance), `credit_payments` (account, amount, method, receipt no, user).
STEP 2 PM sets the credit limit (A: PM is secondary actor). Action `CompleteCreditSale`: payment method Credit appears only for customers with an account; check limit and balance (E1: limit exceeded refuses); record the sale and add it to the balance.
STEP 3 Action `RecordCreditPayment`: reduces the balance and issues a sequential receipt.
STEP 4 Receivables ageing report and customer statement for PM and Owner.
STEP 5 Livewire: credit panel in the sale screen, collection screen, ageing and statement views.

TESTS: limit enforced; balance updates on sale, payment and refund; ageing buckets correct; statement matches transactions; staff cannot change a limit.

DONE WHEN: all green.
````
