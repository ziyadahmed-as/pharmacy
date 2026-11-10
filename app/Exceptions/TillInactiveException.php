<?php

namespace App\Exceptions;

use Exception;

class TillInactiveException extends Exception
{
    public function __construct(
        string $message = 'Cannot open shift on an inactive till.',
        int $code = 422,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
