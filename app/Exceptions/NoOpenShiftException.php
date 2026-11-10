<?php

namespace App\Exceptions;

use Exception;

class NoOpenShiftException extends Exception
{
    public function __construct(
        string $message = 'An active open shift is required to perform this action.',
        int $code = 422,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
