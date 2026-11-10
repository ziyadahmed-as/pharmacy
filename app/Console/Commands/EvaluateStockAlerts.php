<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\Product;
use App\Models\StockAlert;
use App\Services\Notifier;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Command `stock:evaluate-alerts` (UC-05 Step 1).
 * Evaluates low stock, critical stock (below minimum, A1), near-expiry windows (90, 60, 30), and expired batches.
 * Deduplicates per batch and window, and sends in-app notifications to PM and Owner.
 *
 * @uc UC-05 @fr FR-2.6, 2.7, 9.1 @ac AC-33
 */
class EvaluateStockAlerts extends Command
{
    protected $signature = 'stock:evaluate-alerts {--product= : Specific product ID to evaluate}';

    protected $description = 'Evaluate inventory levels and batch expiries to generate alerts and notifications';

    public function handle(): int
    {
        $productId = $this->option('product');
        $createdAlerts = 0;

        $today = now()->timezone(config('pms.timezone'))->startOfDay();
        $windows = setting('inventory.near_expiry_alert_days', [90, 60, 30]);
        if (! is_array($windows)) {
            $windows = [90, 60, 30];
        }
        sort($windows); // Ascending: [30, 60, 90] to match tightest window first

        // 1. Evaluate Product Levels (Low stock & Critical stock)
        $productQuery = Product::query()->where('is_active', true);
        if ($productId) {
            $productQuery->where('id', $productId);
        }

        $productQuery->chunk(100, function ($products) use (&$createdAlerts) {
            foreach ($products as $product) {
                if ($product->reorder_level === null && $product->min_level === null) {
                    continue;
                }

                $sellableStock = (int) DB::table('batch_stocks')
                    ->join('batches', 'batches.id', '=', 'batch_stocks.batch_id')
                    ->where('batches.product_id', $product->id)
                    ->where('batch_stocks.status', 'sellable')
                    ->sum('batch_stocks.quantity');

                $minLevel = $product->min_level;
                $reorderLevel = $product->reorder_level;

                // Critical Stock (A1): Below minimum
                if ($minLevel !== null && $minLevel > 0 && $sellableStock < $minLevel) {
                    $dedupe = "critical-product-{$product->id}";
                    $alert = StockAlert::firstOrCreate(
                        ['dedupe_key' => $dedupe],
                        [
                            'product_id' => $product->id,
                            'type' => 'critical_stock',
                            'severity' => 'critical',
                            'current_quantity' => $sellableStock,
                            'reorder_level' => $reorderLevel,
                            'min_level' => $minLevel,
                            'message' => __('catalogue.alert_msg_critical', [
                                'brand' => $product->brand,
                                'current' => $sellableStock,
                                'min' => $minLevel,
                            ]),
                            'status' => 'active',
                        ]
                    );

                    if ($alert->wasRecentlyCreated) {
                        $createdAlerts++;
                        Notifier::toRoles([Role::PharmacyManager, Role::Owner], 'stock.alert.critical', [
                            'message_key' => 'catalogue.alert_msg_critical',
                            'message_params' => ['brand' => $product->brand, 'current' => $sellableStock, 'min' => $minLevel],
                            'product_id' => $product->id,
                        ], $dedupe);
                    }
                }
                // Low Stock: At or below reorder level (and not below min level)
                elseif ($reorderLevel !== null && $reorderLevel > 0 && $sellableStock <= $reorderLevel) {
                    $dedupe = "low-product-{$product->id}";
                    $alert = StockAlert::firstOrCreate(
                        ['dedupe_key' => $dedupe],
                        [
                            'product_id' => $product->id,
                            'type' => 'low_stock',
                            'severity' => 'warning',
                            'current_quantity' => $sellableStock,
                            'reorder_level' => $reorderLevel,
                            'min_level' => $minLevel,
                            'message' => __('catalogue.alert_msg_low', [
                                'brand' => $product->brand,
                                'current' => $sellableStock,
                                'reorder' => $reorderLevel,
                            ]),
                            'status' => 'active',
                        ]
                    );

                    if ($alert->wasRecentlyCreated) {
                        $createdAlerts++;
                        Notifier::toRoles([Role::PharmacyManager, Role::Owner], 'stock.alert.low', [
                            'message_key' => 'catalogue.alert_msg_low',
                            'message_params' => ['brand' => $product->brand, 'current' => $sellableStock, 'reorder' => $reorderLevel],
                            'product_id' => $product->id,
                        ], $dedupe);
                    }
                } else {
                    // Stock is healthy; resolve past low/critical alerts
                    StockAlert::where('product_id', $product->id)
                        ->whereIn('type', ['low_stock', 'critical_stock'])
                        ->where('status', '!=', 'resolved')
                        ->update(['status' => 'resolved']);
                }
            }
        });

        // 2. Evaluate Batches (Near Expiry and Expired)
        $batchQuery = Batch::query()
            ->with(['product'])
            ->whereHas('stocks', fn ($q) => $q->where('quantity', '>', 0));

        if ($productId) {
            $batchQuery->where('product_id', $productId);
        }

        $batchQuery->chunk(100, function ($batches) use ($today, $windows, &$createdAlerts) {
            foreach ($batches as $batch) {
                if (! $batch->expiry_date) {
                    continue;
                }

                $expiry = Carbon::parse($batch->expiry_date)->startOfDay();
                $diffDays = (int) $today->diffInDays($expiry, false);

                $stockQty = (int) $batch->stocks()->sum('quantity');
                if ($stockQty <= 0) {
                    continue;
                }

                // Expired (AC-33)
                if ($diffDays <= 0) {
                    $dedupe = "expired-batch-{$batch->id}";
                    $alert = StockAlert::firstOrCreate(
                        ['dedupe_key' => $dedupe],
                        [
                            'product_id' => $batch->product_id,
                            'batch_id' => $batch->id,
                            'type' => 'expired',
                            'severity' => 'critical',
                            'current_quantity' => $stockQty,
                            'message' => __('catalogue.alert_msg_expired', [
                                'brand' => $batch->product->brand,
                                'batch' => $batch->batch_no,
                                'date' => $expiry->toDateString(),
                            ]),
                            'status' => 'active',
                        ]
                    );

                    if ($alert->wasRecentlyCreated) {
                        $createdAlerts++;
                        Notifier::toRoles([Role::PharmacyManager, Role::Owner], 'stock.alert.expired', [
                            'message_key' => 'catalogue.alert_msg_expired',
                            'message_params' => ['brand' => $batch->product->brand, 'batch' => $batch->batch_no, 'date' => $expiry->toDateString()],
                            'batch_id' => $batch->id,
                            'product_id' => $batch->product_id,
                        ], $dedupe);
                    }
                } else {
                    // Near Expiry Windows
                    foreach ($windows as $window) {
                        if ($diffDays <= (int) $window) {
                            $dedupe = "near-expiry-batch-{$batch->id}-window-{$window}";
                            $alert = StockAlert::firstOrCreate(
                                ['dedupe_key' => $dedupe],
                                [
                                    'product_id' => $batch->product_id,
                                    'batch_id' => $batch->id,
                                    'type' => 'near_expiry',
                                    'severity' => ($window <= 30) ? 'critical' : 'warning',
                                    'threshold_days' => (int) $window,
                                    'current_quantity' => $stockQty,
                                    'message' => __('catalogue.alert_msg_near_expiry', [
                                        'brand' => $batch->product->brand,
                                        'batch' => $batch->batch_no,
                                        'days' => $diffDays,
                                        'window' => $window,
                                    ]),
                                    'status' => 'active',
                                ]
                            );

                            if ($alert->wasRecentlyCreated) {
                                $createdAlerts++;
                                Notifier::toRoles([Role::PharmacyManager, Role::Owner], 'stock.alert.near_expiry', [
                                    'message_key' => 'catalogue.alert_msg_near_expiry',
                                    'message_params' => ['brand' => $batch->product->brand, 'batch' => $batch->batch_no, 'days' => $diffDays, 'window' => $window],
                                    'batch_id' => $batch->id,
                                    'product_id' => $batch->product_id,
                                ], $dedupe);
                            }
                            break; // Stop at the tightest matched threshold
                        }
                    }
                }
            }
        });

        $this->info("Stock alert evaluation complete. New alerts created: {$createdAlerts}");

        return self::SUCCESS;
    }
}
