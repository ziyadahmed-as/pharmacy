<?php

namespace App\Exceptions;

use Exception;

class ShiftAlreadyOpenException extends Exception
{
    public function __construct(
        string $message = 'Till already has an active open shift.',
        int $code = 422,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
