<?php

namespace App\Reports\Contracts;

use App\Models\User;

/**
 * Contract for all system report definitions (UC-30).
 *
 * @uc UC-30
 *
 * @fr FR-7.1, FR-7.2, FR-7.3, FR-7.4, FR-7.5, FR-7.6, FR-2.19, FR-4.12
 *
 * @ac AC-29
 */
interface ReportDefinition
{
    /** Unique kebab/snake key identifying the report. */
    public function key(): string;

    /** Human-readable translatable name. */
    public function name(): string;

    /** Category: sales, inventory, compliance, audit. */
    public function category(): string;

    /** Short description. */
    public function description(): string;

    /** Check if the given user is authorized to view/run this report. */
    public function canView(User $user): bool;

    /** Available filter options: date_range, product_id, category_id, user_id, etc. */
    public function availableFilters(): array;

    /** Table column headers, omitting cost/profit columns for unauthorized users (BR-06). */
    public function headers(User $user): array;

    /**
     * Run the query and return formatted headers, rows, and summary metrics.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     headers: list<string>,
     *     rows: list<list<string|int|float|null>>,
     *     summary: array<string, mixed>,
     *     row_count: int
     * }
     */
    public function query(array $filters, User $user): array;

    /** Whether export of this report should be logged as a sensitive export in audit trail. */
    public function isSensitive(): bool;
}
