<?php

namespace App\Exceptions;

use Exception;

class UserAlreadyHasOpenShiftException extends Exception
{
    public function __construct(
        string $message = 'User already has an active open shift.',
        int $code = 422,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
