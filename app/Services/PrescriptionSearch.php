<?php

namespace App\Services;

use App\Models\Prescription;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query object for searching prescriptions and patient history.
 * Supports filtering by patient, prescriber, drug, date range, status, and suspect flag.
 *
 * @uc UC-23
 *
 * @fr FR-4.12, FR-5.6, FR-7.4
 *
 * @ac AC-26
 */
class PrescriptionSearch
{
    /**
     * Build the search query for prescriptions.
     *
     * @param  array{
     *     patient?: string|null,
     *     prescriber?: string|null,
     *     drug?: string|null,
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     status?: string|null,
     *     suspect_only?: bool|string|null,
     *     q?: string|null,
     * }  $filters
     * @return Builder<Prescription>
     */
    public static function build(array $filters = []): Builder
    {
        $query = Prescription::query()
            ->with(['customer', 'creator', 'lines.product', 'lines.dispensingEvents']);

        // General keyword search
        if (! empty($filters['q'])) {
            $term = '%'.trim($filters['q']).'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('prescription_number', 'like', $term)
                    ->orWhere('prescriber_name', 'like', $term)
                    ->orWhere('prescriber_registration_no', 'like', $term)
                    ->orWhereHas('customer', function (Builder $cq) use ($term) {
                        $cq->where('name', 'like', $term)
                            ->orWhere('phone', 'like', $term);
                    })
                    ->orWhereHas('lines.product', function (Builder $pq) use ($term) {
                        $pq->where('brand', 'like', $term)
                            ->orWhere('generic_name', 'like', $term);
                    });
            });
        }

        // Filter: Patient
        if (! empty($filters['patient'])) {
            $patient = '%'.trim($filters['patient']).'%';
            $query->whereHas('customer', function (Builder $cq) use ($patient) {
                $cq->where('name', 'like', $patient)
                    ->orWhere('phone', 'like', $patient);
            });
        }

        // Filter: Prescriber
        if (! empty($filters['prescriber'])) {
            $prescriber = '%'.trim($filters['prescriber']).'%';
            $query->where(function (Builder $q) use ($prescriber) {
                $q->where('prescriber_name', 'like', $prescriber)
                    ->orWhere('prescriber_registration_no', 'like', $prescriber);
            });
        }

        // Filter: Drug / Medicine
        if (! empty($filters['drug'])) {
            $drug = '%'.trim($filters['drug']).'%';
            $query->whereHas('lines.product', function (Builder $pq) use ($drug) {
                $pq->where('brand', 'like', $drug)
                    ->orWhere('generic_name', 'like', $drug);
            });
        }

        // Filter: Date Range (from)
        if (! empty($filters['date_from'])) {
            $query->whereDate('prescription_date', '>=', $filters['date_from']);
        }

        // Filter: Date Range (to)
        if (! empty($filters['date_to'])) {
            $query->whereDate('prescription_date', '<=', $filters['date_to']);
        }

        // Filter: Suspect prescriptions only (FR-4.12)
        $suspectOnly = filter_var($filters['suspect_only'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($suspectOnly) {
            $query->where('status', Prescription::STATUS_REJECTED);
        } elseif (! empty($filters['status'])) {
            // Filter: Status
            $query->where('status', $filters['status']);
        }

        return $query->latest('prescription_date')->latest('id');
    }
}
