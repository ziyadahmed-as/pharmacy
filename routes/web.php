<?php

use App\Enums\Role;
use App\Http\Controllers\AuditExportController;
use App\Http\Controllers\ControlledDrugExportController;
use App\Http\Controllers\DailyClosingReportController;
use App\Http\Controllers\KeepAliveController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\PrescriptionExportController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\RefundReceiptController;
use App\Http\Controllers\ShiftSummaryController;
use App\Http\Controllers\StaffPhotoController;
use App\Http\Controllers\StockExportController;
use App\Livewire\Alerts as AlertsUi;
use App\Livewire\Approvals as ApprovalsUi;
use App\Livewire\Audit as AuditUi;
use App\Livewire\Auth\ChooseActingRole;
use App\Livewire\Auth\ForcePasswordChange;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Security;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Livewire\Backup\RestoreChecklist;
use App\Livewire\Catalogue\Form;
use App\Livewire\Catalogue\Import;
use App\Livewire\Catalogue\Index;
use App\Livewire\Catalogue\Show;
use App\Livewire\Closing as ClosingUi;
use App\Livewire\Dashboard;
use App\Livewire\Finance\Expenses as ExpensesUi;
use App\Livewire\Inventory\Adjustments as AdjustmentsUi;
use App\Livewire\Inventory\Disposals as DisposalsUi;
use App\Livewire\Inventory\Receive;
use App\Livewire\Inventory\ShowReceipt;
use App\Livewire\Inventory\Stocktakes as StocktakesUi;
use App\Livewire\Notifications;
use App\Livewire\Pos as PosUi;
use App\Livewire\Prescriptions\Register;
use App\Livewire\Prescriptions\Search;
use App\Livewire\Quarantine as QuarantineUi;
use App\Livewire\Returns as ReturnsUi;
use App\Livewire\Sales as SalesUi;
use App\Livewire\Settings as SettingsUi;
use App\Livewire\Shifts as ShiftsUi;
use App\Livewire\Staff as StaffUi;
use App\Livewire\Stock as StockUi;
use App\Livewire\Tills as TillsUi;
use App\Livewire\Users as UsersUi;
use App\Models\Expense;
use App\Models\Prescription;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
| Pharmacy Management System - web routes.
| Every restricted route is protected by a permission gate (`can:`) AND by the
| action/component it opens (AGENTS.md section 4): never rely on hidden buttons.
*/

Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch');

Route::middleware('guest')->group(function () {
    Route::get('/', fn () => redirect()->route('login'));
    Route::get('/login', Login::class)->name('login');
    Route::get('/two-factor-challenge', TwoFactorChallenge::class)->name('two-factor.login');
});

Route::middleware(['auth', 'account.active'])->group(function () {
    Route::get('/force-password-change', ForcePasswordChange::class)->name('password.force-change');
    Route::post('/logout', LogoutController::class)->name('logout');
    Route::post('/keep-alive', KeepAliveController::class)->name('keep-alive');

    Route::middleware('password.changed')->group(function () {
        Route::get('/acting-role', ChooseActingRole::class)->name('acting-role');

        Route::middleware('acting.role')->group(function () {
            Route::get('/dashboard', Dashboard::class)->name('dashboard');
            Route::get('/notifications', Notifications::class)->name('notifications');
            Route::get('/security', Security::class)->name('security');

            // UC-39: staff records and licences
            Route::middleware('can:viewAny,App\Models\Staff')->group(function () {
                Route::get('/staff', StaffUi\Index::class)->name('staff.index');
                Route::get('/staff/create', StaffUi\Edit::class)->middleware('can:staff.manage')->name('staff.create');
                Route::get('/staff/{staff}', StaffUi\Show::class)->name('staff.show');
                Route::get('/staff/{staff}/edit', StaffUi\Edit::class)->middleware('can:staff.manage')->name('staff.edit');
                Route::get('/staff/{staff}/photo', StaffPhotoController::class)->name('staff.photo');
            });

            // UC-35 / UC-36: user accounts, recovery (row actions on the list)
            Route::get('/users', UsersUi\Index::class)->middleware('can:users.view')->name('users.index');

            // UC-34: approvals
            Route::get('/approvals', ApprovalsUi\Queue::class)->middleware('can:approvals.request')->name('approvals.index');
            Route::get('/approvals/{approvalRequest}', ApprovalsUi\Show::class)->middleware('can:approvals.request')->name('approvals.show');

            // UC-31: audit log
            Route::get('/audit', AuditUi\Index::class)->name('audit.index');
            Route::get('/audit/export/{format}', AuditExportController::class)->whereIn('format', ['csv', 'pdf'])->name('audit.export');

            // UC-37: Backup & Restore
            Route::get('/admin/backups', App\Livewire\Backup\Dashboard::class)->middleware('can:backup.run')->name('admin.backups');
            Route::get('/backup/checklist', RestoreChecklist::class)->name('backup.checklist');

            // UC-01 / UC-03: Product Catalogue
            Route::get('/catalogue', Index::class)->middleware('can:catalogue.view')->name('catalogue.index');
            Route::get('/catalogue/import', Import::class)->middleware('can:catalogue.manage')->name('catalogue.import');
            Route::get('/catalogue/create', Form::class)->middleware('can:catalogue.edit_basic')->name('catalogue.create');
            Route::get('/catalogue/{product}', Show::class)->middleware('can:catalogue.view')->name('catalogue.show');
            Route::get('/catalogue/{product}/edit', Form::class)->middleware('can:catalogue.edit_basic')->name('catalogue.edit');

            // UC-04: Stock Receipts
            Route::get('/inventory/receive', Receive::class)->middleware('can:stock.receive')->name('inventory.receive');
            Route::get('/inventory/receipts/{receipt}', ShowReceipt::class)->middleware('can:stock.receive')->name('inventory.receipts.show');

            // UC-06: Stock Adjustments
            Route::get('/inventory/adjustments', AdjustmentsUi\Index::class)->middleware('can:stock.adjust.request')->name('inventory.adjustments.index');
            Route::get('/inventory/adjustments/create', AdjustmentsUi\Create::class)->middleware('can:stock.adjust.request')->name('inventory.adjustments.create');

            // UC-07: Stocktakes
            Route::get('/inventory/stocktakes', StocktakesUi\Index::class)->middleware('can:stock.stocktake')->name('inventory.stocktakes.index');
            Route::get('/inventory/stocktakes/{stocktake}', StocktakesUi\Show::class)->middleware('can:stock.stocktake')->name('inventory.stocktakes.show');

            // UC-09: Stock Disposals & Supplier Returns
            Route::get('/inventory/disposals', DisposalsUi\Index::class)->middleware('can:stock.dispose')->name('inventory.disposals.index');
            Route::get('/inventory/disposals/{disposal}', DisposalsUi\Show::class)->middleware('can:stock.dispose')->name('inventory.disposals.show');
            Route::get('/inventory/disposals/{disposal}/certificate', DisposalsUi\Certificate::class)->middleware('can:stock.dispose')->name('inventory.disposals.certificate');

            // UC-11: Stock on Hand & Ledger
            Route::get('/inventory/stock', StockUi\Index::class)->middleware('can:stock.view')->name('inventory.stock');
            Route::get('/inventory/stock/export/{format}', [StockExportController::class, 'stockOnHand'])->whereIn('format', ['csv', 'pdf'])->name('inventory.stock.export');
            Route::get('/inventory/ledger', StockUi\Ledger::class)->middleware('can:stock.view')->name('inventory.ledger');
            Route::get('/inventory/ledger/export/{format}', [StockExportController::class, 'ledger'])->whereIn('format', ['csv', 'pdf'])->name('inventory.ledger.export');

            // UC-05: Stock Alerts & Reorder List
            Route::get('/inventory/alerts', AlertsUi\Index::class)->middleware('can:stock.view')->name('inventory.alerts');
            Route::get('/inventory/reorder/export/{format}', [StockExportController::class, 'reorderList'])->whereIn('format', ['csv', 'pdf'])->name('inventory.reorder.export');

            // UC-08: Quarantine Stock
            Route::get('/inventory/quarantine', QuarantineUi\Index::class)->middleware('can:stock.view')->name('inventory.quarantine');

            // UC-12: Cash Shifts & Tills
            Route::get('/shifts/open', ShiftsUi\Open::class)->middleware('can:shifts.open')->name('shifts.open');
            Route::get('/shifts/close', ShiftsUi\Close::class)->middleware('can:shifts.open')->name('shifts.close');
            Route::get('/shifts/{shift}/summary', [ShiftSummaryController::class, 'show'])->middleware('can:shifts.view')->name('shifts.summary');
            Route::get('/tills', TillsUi\Index::class)->middleware('can:tills.manage')->name('tills.index');

            // UC-13: POS Sales & Receipts
            Route::get('/pos', PosUi\Sale::class)->middleware('can:shifts.open')->name('pos.sale');
            Route::get('/sales/{sale}/receipt', [ReceiptController::class, 'show'])->middleware('can:shifts.open')->name('sales.receipt');

            // UC-15: Sale detail & Void request
            Route::get('/sales/{sale}', SalesUi\Show::class)->middleware('can:shifts.open')->name('sales.show');

            // UC-16: Customer returns & refunds
            Route::get('/returns', ReturnsUi\Process::class)->middleware('can:shifts.open')->name('returns.process');
            Route::get('/returns/{saleReturn}/receipt', [RefundReceiptController::class, 'show'])->middleware('can:shifts.open')->name('returns.receipt');

            // UC-18: Daily closing & day lock
            Route::get('/closing/day', ClosingUi\Day::class)->middleware('can:shifts.view')->name('closing.day');
            Route::get('/closing/day/{dailyClosing}/report', [DailyClosingReportController::class, 'show'])->middleware('can:shifts.view')->name('closing.report');

            // UC-25: Customer management (Stage 4)
            Route::get('/customers', App\Livewire\Customers\Index::class)->middleware('can:customers.view')->name('customers.index');
            Route::get('/customers/create', App\Livewire\Customers\Form::class)->middleware('can:customers.manage')->name('customers.create');
            Route::get('/customers/{customer}', App\Livewire\Customers\Show::class)->middleware('can:customers.view')->name('customers.show');
            Route::get('/customers/{customer}/edit', App\Livewire\Customers\Form::class)->middleware('can:customers.manage')->name('customers.edit');

            // UC-19, UC-20, UC-21, UC-22, UC-23: Prescriptions and Controlled Drugs (Stage 4)
            Route::get('/prescriptions', App\Livewire\Prescriptions\Index::class)->middleware('can:prescriptions.view')->name('prescriptions.index');
            Route::get('/prescriptions/search', Search::class)->middleware('can:prescriptions.view')->name('prescriptions.search');
            Route::get('/prescriptions/export/{format}', PrescriptionExportController::class)->whereIn('format', ['csv', 'pdf'])->middleware('can:prescriptions.view')->name('prescriptions.export');
            Route::get('/prescriptions/create', Register::class)->middleware('can:prescriptions.register')->name('prescriptions.create');
            Route::get('/prescriptions/{prescription}/dispense', App\Livewire\Dispense\Show::class)->middleware('can:prescriptions.dispense')->name('prescriptions.dispense');
            Route::get('/prescriptions/{prescription}', App\Livewire\Prescriptions\Show::class)->middleware('can:prescriptions.view')->name('prescriptions.show');
            Route::get('/prescriptions/{prescription}/scan', function (Prescription $prescription) {
                abort_unless(auth()->user()?->hasPermission('prescriptions.view'), 403);
                abort_if(auth()->user()->hasRole(Role::SystemAdmin), 403);
                abort_unless($prescription->scan_path && Storage::disk('private')->exists($prescription->scan_path), 404);

                return Storage::disk('private')->response($prescription->scan_path);
            })->middleware('can:prescriptions.view')->name('prescriptions.scan');

            Route::get('/controlled-drugs/register', App\Livewire\ControlledDrugs\Register::class)->middleware('can:controlled_drugs.view')->name('controlled-drugs.register');
            Route::get('/controlled-drugs/export/{format}', ControlledDrugExportController::class)->middleware('can:controlled_drugs.export')->name('controlled-drugs.export');

            // UC-28: Operating expenses (Stage 6)
            Route::get('/finance/expenses', ExpensesUi\Index::class)->middleware('can:finance.expense.view')->name('finance.expenses.index');
            Route::get('/finance/expenses/{expense}/proof', function (Expense $expense) {
                abort_unless(auth()->user()?->hasPermission('finance.expense.view'), 403);
                abort_if(auth()->user()->hasRole(Role::SystemAdmin), 403);
                abort_unless($expense->proof_path && Storage::disk('private')->exists($expense->proof_path), 404);

                return Storage::disk('private')->response($expense->proof_path);
            })->middleware('can:finance.expense.view')->name('finance.expenses.proof');

            // UC-38: settings
            Route::get('/settings', SettingsUi\Index::class)->middleware('can:settings.view')->name('settings.index');

            // Route list used by the "SA never reaches business data" test; it grows with each stage.
            Route::get('/pharmacy/business-data', fn () => response()->json(['section' => 'business_data']))
                ->middleware('can:access-business-data')->name('business-data.probe');
        });
    });
});
