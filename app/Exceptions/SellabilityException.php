<?php

namespace App\Exceptions;

use Exception;

class SellabilityException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $reason = 'not_sellable',
        int $code = 422,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
