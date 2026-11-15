# Business date and day lock correctness

**Status:** accepted (Defect fix / Architectural hardening)

## Context
1. `config/app.php` timezone is UTC, but the pharmacy business timezone is `config('pms.timezone') = 'Africa/Addis_Ababa'` (UTC+3, no DST).
2. Previously, `DailyClosing::isLockedForDate(now())` used `$now->toDateString()` in UTC. At 21:30 UTC on day D (which is 00:30 on day D+1 in Addis Ababa), the check evaluated against day D in UTC rather than day D+1 in Addis Ababa.
3. Day-lock checks in `CompleteSale`, `ProcessReturn`, `RecordExpense`, `RecordCreditPayment`, and stock adjustment approvals were evaluated outside or before row locks, leaving a race condition where closing a day concurrently would not block an in-progress transaction.

## Decision
1. Added helper `business_date(?CarbonInterface $at = null): string` in `app/Support/helpers.php` that converts the given timestamp (or `now()`) to `config('pms.timezone', 'Africa/Addis_Ababa')` and formats it as `Y-m-d`.
2. Updated `DailyClosing::isLockedForDate(string|CarbonInterface|null $date = null)` to automatically resolve null or CarbonInterface parameters to `business_date()`.
3. Replaced occurrences of `toDateString()`, `whereDate()`, and `today()` with `business_date()` across day locks, daily closings, daily reports, shift operations, owner dashboard tiles, and roster comparisons.
4. Moved the day-lock check inside database transactions after acquiring row locks:
   - `CompleteSale`: inside `DB::transaction(...)` after locking the shift row (`Shift::lockForUpdate()`).
   - `ProcessReturn`: inside `DB::transaction(...)` after locking the shift row, validating both today's business date and the original sale's business date.
   - `RecordExpense`: inside `DB::transaction(...)` validating the expense date.
   - `RecordCreditPayment`: inside `DB::transaction(...)` after locking the credit account row (`CreditAccount::lockForUpdate()`).
   - `StockAdjustmentHandler::onApproved` & `RequestStockAdjustment`: inside `DB::transaction(...)`.
5. Created tests in `tests/Feature/Closing/BusinessDateAndDayLockTest.php` covering `Carbon::setTestNow` at 21:30 UTC, 20:59 UTC, and 00:30 UTC, verifying open, locked, and unlocked day behavior, and verifying that concurrent day closure aborts in-progress sales.
