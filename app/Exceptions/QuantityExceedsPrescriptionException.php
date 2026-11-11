<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the requested dispense quantity exceeds the prescribed remaining
 * quantity and no licensed override reason was provided.
 *
 * @uc UC-21
 *
 * @fr FR-4.9
 *
 * @br BR-17
 *
 * @ac AC-12
 */
class QuantityExceedsPrescriptionException extends RuntimeException {}
