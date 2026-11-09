<?php

namespace App\Console\Commands;

use App\Services\ApprovalService;
use Illuminate\Console\Command;

class ExpireApprovals extends Command
{
    protected $signature = 'approvals:expire';

    protected $description = 'Expire pending approval requests past their expiry time (UC-34 E3)';

    public function handle(ApprovalService $approvals): int
    {
        $count = $approvals->expireOverdue();
        $this->info("Expired {$count} approval request(s).");

        return self::SUCCESS;
    }
}
