<?php

use App\Http\Controllers\LogoutController;
use App\Livewire\Auth\ForcePasswordChange;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Pharmacy Management System (PMS)
|--------------------------------------------------------------------------
*/

// Guest Routes
Route::middleware('guest')->group(function () {
    Route::get('/', function () {
        return redirect()->route('login');
    });

    Route::get('/login', Login::class)->name('login');
    Route::get('/two-factor-challenge', TwoFactorChallenge::class)->name('two-factor.login');
});

// Authenticated Routes
Route::middleware(['auth', 'account.active'])->group(function () {
    // Forced Password Change workflow
    Route::get('/force-password-change', ForcePasswordChange::class)
        ->name('password.force-change');

    // Manual Logout
    Route::post('/logout', LogoutController::class)
        ->name('logout');

    // Main PMS Dashboard (requires password changed)
    Route::middleware('password.changed')->group(function () {
        Route::get('/dashboard', Dashboard::class)
            ->name('dashboard');

        // Role-guarded test routes to verify role authorization
        Route::get('/pharmacy/clinical-data', function () {
            return response()->json(['status' => 'authorized', 'section' => 'clinical_and_sales_data']);
        })->middleware('can:access-business-data')->name('test.business-data');

        Route::get('/admin/system-tools', function () {
            return response()->json(['status' => 'authorized', 'section' => 'system_administration']);
        })->middleware('role:system_administrator')->name('test.admin-only');
    });
});
