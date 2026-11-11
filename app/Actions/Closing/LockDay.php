<?php

namespace App\Actions\Closing;

use App\Enums\Role;
use App\Models\DailyClosing;
use App\Models\Shift;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DailySummaryCalculator;
use App\Services\ReAuthentication;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Reconcile daily system tenders against counted amounts, verify variances,
 * require reasons for any discrepancies, and permanently lock the accounting day.
 *
 * @uc UC-18 @fr FR-3.10, FR-6.1, FR-6.6 @ac AC-24
 */
class LockDay
{
    /**
     * @param  array<string, int>  $countedTotalsByMethod  Counted tenders in santim per method
     * @param  array<string, string>  $varianceReasons  Mandatory reason for each non-zero variance
     *
     * @throws AuthorizationException
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function execute(
        User $user,
        string $date,
        array $countedTotalsByMethod,
        array $varianceReasons = [],
        ?string $notes = null,
        bool $reauthConfirmed = false
    ): DailyClosing {
        // 1. Authorisation: PM or Owner only (A1)
        if (! $user->hasRole(Role::PharmacyManager) && ! $user->hasRole(Role::Owner)) {
            throw new AuthorizationException(__('closing.unauthorized_to_lock_day'));
        }

        $formattedDate = Carbon::parse($date)->toDateString();

        // 2. Cannot lock an already locked day
        if (DailyClosing::isLockedForDate($formattedDate)) {
            throw new RuntimeException(__('closing.day_already_locked'));
        }

        // 3. E1: Refuse when ANY shift of the day is still open
        $openShiftsCount = Shift::whereDate('opened_at', $formattedDate)
            ->where('status', 'open')
            ->count();

        if ($openShiftsCount > 0) {
            throw new RuntimeException(__('closing.open_shifts_exist_error', ['count' => $openShiftsCount]));
        }

        // 4. Re-authentication verification
        if (! $reauthConfirmed && ! ReAuthentication::consume('closing.lock_day')) {
            throw new RuntimeException(__('auth.reauth_required'));
        }

        // 5. Compute system totals from shift closings
        $systemTotals = DailySummaryCalculator::calculateSystemTotals($formattedDate);

        // Normalize counted tenders
        $normalizedCounted = [];
        $allMethods = array_unique(array_merge(array_keys($systemTotals), array_keys($countedTotalsByMethod)));

        foreach ($allMethods as $method) {
            $counted = (int) ($countedTotalsByMethod[$method] ?? 0);
            if ($counted < 0) {
                throw new InvalidArgumentException(__('closing.counted_amount_negative_error'));
            }
            $normalizedCounted[$method] = $counted;
        }

        // 6. Compare by method and check reasons for variances
        $variances = [];
        $cleanedReasons = [];

        foreach ($allMethods as $method) {
            $sys = $systemTotals[$method] ?? 0;
            $cnt = $normalizedCounted[$method];
            $variance = $cnt - $sys;
            $variances[$method] = $variance;

            if ($variance !== 0) {
                $reason = trim((string) ($varianceReasons[$method] ?? ''));
                if ($reason === '') {
                    throw new InvalidArgumentException(__('closing.variance_reason_required_for_method', [
                        'method' => ucfirst(str_replace('_', ' ', $method)),
                    ]));
                }
                $cleanedReasons[$method] = $reason;
            }
        }

        return DB::transaction(function () use ($user, $formattedDate, $systemTotals, $normalizedCounted, $variances, $cleanedReasons, $notes) {
            $closing = DailyClosing::create([
                'date' => $formattedDate,
                'status' => DailyClosing::STATUS_LOCKED,
                'system_totals_by_method' => $systemTotals,
                'counted_totals_by_method' => $normalizedCounted,
                'variances_by_method' => $variances,
                'variance_reasons' => $cleanedReasons,
                'closed_by' => $user->id,
                'verified_by' => $user->id,
                'locked_at' => now(),
                'notes' => $notes,
            ]);

            AuditLogger::record(
                action: 'day.locked',
                subject: $closing,
                old: [],
                new: [
                    'date' => $formattedDate,
                    'status' => 'locked',
                    'system_totals' => $systemTotals,
                    'counted_totals' => $normalizedCounted,
                    'variances' => $variances,
                ],
                user: $user,
                description: "Day {$formattedDate} closed and locked by {$user->username}"
            );

            return $closing;
        });
    }
}
