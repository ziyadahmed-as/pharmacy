<?php

namespace App\Console\Commands;

use App\Models\Sale;
use Illuminate\Console\Command;

class VerifySaleLinesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pms:verify-sale-lines {--limit=100 : Maximum number of mismatched sales to display}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List existing sales whose lines do not sum to the sale total, or contain negative line subtotals';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Verifying sale lines against sale totals...');

        $sales = Sale::query()
            ->with(['lines'])
            ->where('status', 'completed')
            ->orderBy('id')
            ->get();

        $mismatches = [];

        foreach ($sales as $sale) {
            $linesSum = (int) $sale->lines->sum('subtotal');
            $hasNegativeLine = $sale->lines->contains(fn ($line) => $line->subtotal < 0);
            $difference = $linesSum - (int) $sale->total;

            if ($difference !== 0 || $hasNegativeLine) {
                $mismatches[] = [
                    'id' => $sale->id,
                    'receipt_no' => $sale->receipt_no,
                    'completed_at' => $sale->completed_at?->format('Y-m-d H:i') ?? '-',
                    'total' => number_format($sale->total / 100, 2).' ETB',
                    'lines_sum' => number_format($linesSum / 100, 2).' ETB',
                    'difference' => number_format($difference / 100, 2).' ETB',
                    'has_negative' => $hasNegativeLine ? 'YES' : 'NO',
                ];
            }
        }

        if (empty($mismatches)) {
            $this->info('All sale lines match sale totals perfectly. No discrepancies found.');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        $displayRows = array_slice($mismatches, 0, $limit);

        $this->warn(sprintf('Found %d sale(s) with mismatched line subtotals:', count($mismatches)));
        $this->table(
            ['ID', 'Receipt No', 'Completed At', 'Sale Total', 'Lines Sum', 'Difference', 'Negative Lines'],
            $displayRows
        );

        if (count($mismatches) > $limit) {
            $this->comment(sprintf('... and %d more mismatched sale(s).', count($mismatches) - $limit));
        }

        return self::FAILURE;
    }
}
