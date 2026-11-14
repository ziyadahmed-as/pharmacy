<?php

namespace App\Providers;

use App\Approvals\ApprovalHandlerRegistry;
use App\Approvals\Handlers\ChangeRolesHandler;
use App\Approvals\Handlers\CreateUserHandler;
use App\Approvals\Handlers\DeactivateUserHandler;
use App\Approvals\Handlers\ExpenseApprovalHandler;
use App\Approvals\Handlers\QuarantineReleaseHandler;
use App\Approvals\Handlers\ReactivateUserHandler;
use App\Approvals\Handlers\RefundApprovalHandler;
use App\Approvals\Handlers\RestoreApprovalHandler;
use App\Approvals\Handlers\SettingsUpdateHandler;
use App\Approvals\Handlers\StockAdjustmentHandler;
use App\Approvals\Handlers\StockDisposalHandler;
use App\Approvals\Handlers\VoidSaleHandler;
use App\Contracts\ExpiredBatchQuarantiner;
use App\Enums\Role;
use App\Models\User;
use App\Reports\ReportRegistry;
use App\Services\ExpiredBatchQuarantinerService;
use App\Services\SettingsRepository;
use App\Support\Permissions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once app_path('Support/helpers.php');

        $this->app->singleton(SettingsRepository::class);
        $this->app->singleton(ExpiredBatchQuarantiner::class, ExpiredBatchQuarantinerService::class);
        $this->app->singleton(ReportRegistry::class);

        // Approval handlers (UC-34): later use cases add their handler class here.
        $handlers = [
            CreateUserHandler::class,
            ChangeRolesHandler::class,
            DeactivateUserHandler::class,
            ReactivateUserHandler::class,
            SettingsUpdateHandler::class,
            QuarantineReleaseHandler::class,
            VoidSaleHandler::class,
            RefundApprovalHandler::class,
            StockAdjustmentHandler::class,
            StockDisposalHandler::class,
            RestoreApprovalHandler::class,
            ExpenseApprovalHandler::class,
        ];
        $this->app->tag($handlers, 'approval.handlers');
        $this->app->singleton(ApprovalHandlerRegistry::class, fn ($app) => new ApprovalHandlerRegistry($app->tagged('approval.handlers')));
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // One gate per active permission, built from the registry (BRD matrix 4.3).
        foreach (Permissions::activeNames() as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        // Patient, prescription, sales, cost and financial data: never the SA.
        Gate::define('access-business-data', fn (User $user) => $user->canAccessBusinessData());

        // Technical administration.
        Gate::define('manage-system', fn (User $user) => $user->hasRole(Role::SystemAdmin));

        Gate::define('role-pharmacy-staff', fn (User $user) => $user->hasRole(Role::PharmacyStaff));
        Gate::define('role-pharmacy-manager', fn (User $user) => $user->hasRole(Role::PharmacyManager));
        Gate::define('role-owner', fn (User $user) => $user->hasRole(Role::Owner));
        Gate::define('role-system-admin', fn (User $user) => $user->hasRole(Role::SystemAdmin));
    }
}
