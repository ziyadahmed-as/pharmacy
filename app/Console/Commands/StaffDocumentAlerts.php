<?php

namespace App\Console\Commands;

use App\Actions\Staff\CheckDocumentExpiry;
use Illuminate\Console\Command;

/**
 * Command to check expiring and expired staff documents and alert managers.
 *
 * @uc UC-39
 *
 * @fr FR-9.1, FR-10.3
 */
class StaffDocumentAlerts extends Command
{
    protected $signature = 'staff:document-alerts {--lookahead= : Days in advance to check for expiring documents}';

    protected $description = 'Notify PM and Owner about expiring and expired staff documents';

    public function handle(CheckDocumentExpiry $action): int
    {
        $lookahead = $this->option('lookahead') !== null ? (int) $this->option('lookahead') : null;
        $result = $action->execute($lookahead);

        $this->info("Staff document alerts sent: {$result['notified']}, reminders queued: {$result['reminders']}");

        return self::SUCCESS;
    }
}
