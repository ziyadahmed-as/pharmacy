<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Business data gate: Only Staff, Manager, and Owner. System Admin is denied.
        Gate::define('access-business-data', function (User $user) {
            return $user->canAccessBusinessData();
        });

        // Technical system administration gate: Only System Admin.
        Gate::define('manage-system', function (User $user) {
            return $user->hasRole(UserRole::SYSTEM_ADMINISTRATOR);
        });

        // Role-based gates
        Gate::define('role-pharmacy-staff', fn (User $user) => $user->hasRole(UserRole::PHARMACY_STAFF));
        Gate::define('role-pharmacy-manager', fn (User $user) => $user->hasRole(UserRole::PHARMACY_MANAGER));
        Gate::define('role-owner', fn (User $user) => $user->hasRole(UserRole::OWNER));
        Gate::define('role-system-admin', fn (User $user) => $user->hasRole(UserRole::SYSTEM_ADMINISTRATOR));
    }
}
