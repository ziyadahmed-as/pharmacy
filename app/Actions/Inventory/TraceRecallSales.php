<?php

namespace App\Actions\Inventory;

use App\Models\DispensingEvent;
use App\Models\Recall;
use App\Models\SaleLine;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

/**
 * Action to trace all sales and dispensing events that used the recalled batches (UC-10 Step 2).
 *
 * Lists customer contact information, sale/dispensing date, quantity, receipt/prescription details
 * for follow-up patient calls.
 */
class TraceRecallSales
{
    /**
     * @return Collection<int, array{
     *     event_type: string,
     *     date: string,
     *     reference_no: string,
     *     batch_id: int,
     *     batch_no: string,
     *     quantity: int,
     *     customer_id: int|null,
     *     customer_name: string,
     *     customer_phone: string|null,
     *     prescriber_name: string|null,
     *     prescriber_licence: string|null,
     *     user_name: string
     * }>
     *
     * @throws AuthorizationException
     */
    public function execute(Recall $recall, User $user): Collection
    {
        if ($user->isSystemAdmin() || ! $user->canAccessBusinessData()) {
            throw new AuthorizationException(__('recalls.unauthorized'));
        }

        $batchIds = $recall->batches()->pluck('batch_id')->all();

        $traceResults = collect();

        if (empty($batchIds)) {
            return $traceResults;
        }

        // 1. Trace completed Sales containing any of the recalled batches
        $saleLines = SaleLine::with(['sale.customer', 'sale.prescription.customer', 'sale.prescription.prescriber', 'sale.user', 'batch'])
            ->whereIn('batch_id', $batchIds)
            ->whereHas('sale', fn ($q) => $q->where('status', 'completed'))
            ->get();

        foreach ($saleLines as $line) {
            $sale = $line->sale;
            $customer = $sale->customer ?? $sale->prescription?->customer;
            $prescriber = $sale->prescription?->prescriber;

            $customerName = $customer?->name ?? 'Walk-in Customer';
            $customerPhone = $customer?->phone;
            $prescriberName = $prescriber?->name ?? $sale->prescription?->prescriber_name;
            $prescriberLicence = $prescriber?->licence_number ?? $sale->prescription?->prescriber_registration_no;

            $traceResults->push([
                'event_type' => 'sale',
                'date' => $sale->completed_at ? $sale->completed_at->toIso8601String() : $sale->created_at->toIso8601String(),
                'reference_no' => $sale->receipt_no,
                'batch_id' => $line->batch_id,
                'batch_no' => $line->batch?->batch_no ?? '-',
                'quantity' => $line->quantity,
                'customer_id' => $customer?->id,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'prescriber_name' => $prescriberName,
                'prescriber_licence' => $prescriberLicence,
                'user_name' => $sale->user?->name ?? '-',
            ]);
        }

        // 2. Trace Dispensing Events that used the recalled batches (could be dispensing without sale or prescription dispensing)
        $dispensingEvents = DispensingEvent::with(['prescriptionLine.prescription.customer', 'prescriptionLine.prescription.prescriber', 'user', 'batch', 'sale'])
            ->whereIn('batch_id', $batchIds)
            ->get();

        foreach ($dispensingEvents as $event) {
            // Avoid duplicate reporting if the dispensing event was already tied to a tracked sale line above
            if ($event->sale_id && $saleLines->contains(fn ($l) => $l->sale_id === $event->sale_id && $l->batch_id === $event->batch_id)) {
                continue;
            }

            $prescription = $event->prescriptionLine?->prescription;
            $customer = $prescription?->customer;
            $prescriber = $prescription?->prescriber;

            $traceResults->push([
                'event_type' => 'dispense',
                'date' => $event->dispensed_at ? $event->dispensed_at->toIso8601String() : $event->created_at->toIso8601String(),
                'reference_no' => $prescription?->prescription_number ?? ($event->sale?->receipt_no ?? "DISP-{$event->id}"),
                'batch_id' => $event->batch_id,
                'batch_no' => $event->batch?->batch_no ?? '-',
                'quantity' => $event->quantity,
                'customer_id' => $customer?->id,
                'customer_name' => $customer?->name ?? 'Unknown Patient',
                'customer_phone' => $customer?->phone,
                'prescriber_name' => $prescriber?->name ?? $prescription?->prescriber_name,
                'prescriber_licence' => $prescriber?->licence_number ?? $prescription?->prescriber_registration_no,
                'user_name' => $event->user?->name ?? '-',
            ]);
        }

        AuditLogger::record(
            action: 'stock.recall.traced',
            subject: $recall,
            old: [],
            new: [
                'total_events' => $traceResults->count(),
                'total_units_sold' => $traceResults->sum('quantity'),
            ],
            metadata: [
                'recall_no' => $recall->recall_no,
                'batch_ids' => $batchIds,
            ],
            user: $user,
            description: "Recall {$recall->recall_no} traced {$traceResults->count()} distribution events"
        );

        return $traceResults->sortByDesc('date')->values();
    }
}
