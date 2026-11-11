<?php

use App\Http\Controllers\AuditExportController;
use App\Http\Controllers\KeepAliveController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\ReceiptController;
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
use App\Livewire\Catalogue\Form;
use App\Livewire\Catalogue\Import;
use App\Livewire\Catalogue\Index;
use App\Livewire\Catalogue\Show;
use App\Livewire\Dashboard;
use App\Livewire\Inventory\Receive;
use App\Livewire\Inventory\ShowReceipt;
use App\Livewire\Notifications;
use App\Livewire\Pos as PosUi;
use App\Livewire\Quarantine as QuarantineUi;
use App\Livewire\Settings as SettingsUi;
use App\Livewire\Shifts as ShiftsUi;
use App\Livewire\Staff as StaffUi;
use App\Livewire\Stock as StockUi;
use App\Livewire\Tills as TillsUi;
use App\Livewire\Users as UsersUi;
use Illuminate\Support\Facades\Route;

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

            // UC-01 / UC-03: Product Catalogue
            Route::get('/catalogue', Index::class)->middleware('can:catalogue.view')->name('catalogue.index');
            Route::get('/catalogue/import', Import::class)->middleware('can:catalogue.manage')->name('catalogue.import');
            Route::get('/catalogue/create', Form::class)->middleware('can:catalogue.edit_basic')->name('catalogue.create');
            Route::get('/catalogue/{product}', Show::class)->middleware('can:catalogue.view')->name('catalogue.show');
            Route::get('/catalogue/{product}/edit', Form::class)->middleware('can:catalogue.edit_basic')->name('catalogue.edit');

            // UC-04: Stock Receipts
            Route::get('/inventory/receive', Receive::class)->middleware('can:stock.receive')->name('inventory.receive');
            Route::get('/inventory/receipts/{receipt}', ShowReceipt::class)->middleware('can:stock.receive')->name('inventory.receipts.show');

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
            Route::get('/tills', TillsUi\Index::class)->middleware('can:tills.manage')->name('tills.index');

            // UC-13: POS Sales & Receipts
            Route::get('/pos', PosUi\Sale::class)->middleware('can:shifts.open')->name('pos.sale');
            Route::get('/sales/{sale}/receipt', [ReceiptController::class, 'show'])->middleware('can:shifts.open')->name('sales.receipt');

            // UC-38: settings
            Route::get('/settings', SettingsUi\Index::class)->middleware('can:settings.view')->name('settings.index');

            // Route list used by the "SA never reaches business data" test; it grows with each stage.
            Route::get('/pharmacy/business-data', fn () => response()->json(['section' => 'business_data']))
                ->middleware('can:access-business-data')->name('business-data.probe');
        });
    });
});
