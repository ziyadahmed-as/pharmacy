<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a prescription is not in a dispensable state (not Verified /
 * Partially Dispensed, already fully dispensed, expired, or not found).
 *
 * @uc UC-21
 *
 * @fr FR-4.8, FR-4.9
 *
 * @ac AC-03, AC-12
 */
class PrescriptionNotDispensableException extends RuntimeException {}
