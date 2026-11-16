<?php

namespace App\Reports;

use App\Models\User;
use App\Reports\Contracts\ReportDefinition;
use App\Reports\Definitions\Audit\ApprovalsLogReport;
use App\Reports\Definitions\Audit\AuditAdjustmentsReport;
use App\Reports\Definitions\Audit\AuditDisposalsReport;
use App\Reports\Definitions\Audit\AuditPriceChangesReport;
use App\Reports\Definitions\Audit\AuditRefundsReport;
use App\Reports\Definitions\Audit\AuditUserActivityReport;
use App\Reports\Definitions\Audit\AuditVoidsReport;
use App\Reports\Definitions\Compliance\ComplianceControlledDrugsReport;
use App\Reports\Definitions\Compliance\ComplianceDisposalsReport;
use App\Reports\Definitions\Compliance\CompliancePrescriptionsReport;
use App\Reports\Definitions\Compliance\ComplianceRecallsReport;
use App\Reports\Definitions\Compliance\DispensingByStaffReport;
use App\Reports\Definitions\Finance\CashShiftSummaryReport;
use App\Reports\Definitions\Finance\CreditAgeingReport;
use App\Reports\Definitions\Finance\DailyClosingHistoryReport;
use App\Reports\Definitions\Finance\ExpensesByCategoryReport;
use App\Reports\Definitions\Inventory\InventoryBatchTrackingReport;
use App\Reports\Definitions\Inventory\InventoryCurrentStockReport;
use App\Reports\Definitions\Inventory\InventoryDisposalsReturnsReport;
use App\Reports\Definitions\Inventory\InventoryExpiredReport;
use App\Reports\Definitions\Inventory\InventoryLedgerReport;
use App\Reports\Definitions\Inventory\InventoryLowStockReport;
use App\Reports\Definitions\Inventory\InventoryNearExpiryReport;
use App\Reports\Definitions\Inventory\ProductMovementReport;
use App\Reports\Definitions\Inventory\StocktakeVarianceReport;
use App\Reports\Definitions\Inventory\StockValuationReport;
use App\Reports\Definitions\Sales\CustomerPurchaseHistoryReport;
use App\Reports\Definitions\Sales\SalesByCategoryReport;
use App\Reports\Definitions\Sales\SalesByPaymentMethodReport;
use App\Reports\Definitions\Sales\SalesByProductReport;
use App\Reports\Definitions\Sales\SalesByStaffReport;
use App\Reports\Definitions\Sales\SalesReturnsRefundsReport;
use App\Reports\Definitions\Sales\SalesSummaryReport;
use App\Reports\Definitions\Staff\LeaveAttendanceSummaryReport;
use App\Reports\Definitions\Staff\MonthlyAttendanceReport;
use App\Reports\Definitions\Staff\PayrollSummaryReport;
use App\Reports\Definitions\Staff\RosterVsAttendanceReport;

/**
 * Registry of all available report definitions (UC-30).
 *
 * @uc UC-30 @fr FR-7.1 to 7.6, 2.19, 4.12 @ac AC-29
 */
class ReportRegistry
{
    /** @var array<string, ReportDefinition> */
    protected array $reports = [];

    public function __construct()
    {
        $this->registerDefaults();
    }

    protected function registerDefaults(): void
    {
        // Sales
        $this->register(new SalesSummaryReport);
        $this->register(new SalesByProductReport);
        $this->register(new SalesByCategoryReport);
        $this->register(new SalesByStaffReport);
        $this->register(new SalesByPaymentMethodReport);
        $this->register(new SalesReturnsRefundsReport);
        $this->register(new CustomerPurchaseHistoryReport);

        // Inventory
        $this->register(new InventoryCurrentStockReport);
        $this->register(new InventoryLowStockReport);
        $this->register(new InventoryNearExpiryReport);
        $this->register(new InventoryExpiredReport);
        $this->register(new InventoryBatchTrackingReport);
        $this->register(new InventoryLedgerReport);
        $this->register(new InventoryDisposalsReturnsReport);
        $this->register(new ProductMovementReport);
        $this->register(new StockValuationReport);
        $this->register(new StocktakeVarianceReport);

        // Finance
        $this->register(new CashShiftSummaryReport);
        $this->register(new DailyClosingHistoryReport);
        $this->register(new ExpensesByCategoryReport);
        $this->register(new CreditAgeingReport);

        // Compliance
        $this->register(new ComplianceControlledDrugsReport);
        $this->register(new CompliancePrescriptionsReport);
        $this->register(new ComplianceRecallsReport);
        $this->register(new ComplianceDisposalsReport);
        $this->register(new DispensingByStaffReport);

        // Audit
        $this->register(new AuditUserActivityReport);
        $this->register(new AuditVoidsReport);
        $this->register(new AuditRefundsReport);
        $this->register(new AuditAdjustmentsReport);
        $this->register(new AuditDisposalsReport);
        $this->register(new AuditPriceChangesReport);
        $this->register(new ApprovalsLogReport);

        // Staff (UC-41)
        $this->register(new MonthlyAttendanceReport);
        $this->register(new PayrollSummaryReport);
        $this->register(new LeaveAttendanceSummaryReport);
        $this->register(new RosterVsAttendanceReport);
    }

    public function register(ReportDefinition $report): void
    {
        $this->reports[$report->key()] = $report;
    }

    public function get(string $key): ?ReportDefinition
    {
        return $this->reports[$key] ?? null;
    }

    /** @return array<string, ReportDefinition> */
    public function all(): array
    {
        return $this->reports;
    }

    /** @return array<string, ReportDefinition> */
    public function availableFor(User $user): array
    {
        return array_filter($this->reports, fn (ReportDefinition $report) => $report->canView($user));
    }

    /**
     * @return array<string, array{title: string, reports: array<string, ReportDefinition>}>
     */
    public function byCategoryFor(User $user): array
    {
        $available = $this->availableFor($user);
        $grouped = [
            'sales' => ['title' => __('reports.category_sales'), 'reports' => []],
            'inventory' => ['title' => __('reports.category_inventory'), 'reports' => []],
            'finance' => ['title' => __('reports.category_finance', [], 'en') ?: 'Finance', 'reports' => []],
            'compliance' => ['title' => __('reports.category_compliance'), 'reports' => []],
            'audit' => ['title' => __('reports.category_audit'), 'reports' => []],
            'staff' => ['title' => __('reports.category_staff'), 'reports' => []],
        ];

        foreach ($available as $key => $report) {
            $cat = $report->category();
            if (isset($grouped[$cat])) {
                $grouped[$cat]['reports'][$key] = $report;
            }
        }

        // Filter out empty categories
        return array_filter($grouped, fn ($g) => ! empty($g['reports']));
    }
}
