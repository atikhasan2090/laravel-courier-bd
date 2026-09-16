<?php

namespace Shipkit\CourierBD\Exceptions;

use Exception;

class CourierApiException extends Exception
{
    public function __construct(
        string $message,
        public int $statusCode = 0,
        public array $rawResponse = []
    ) {
        parent::__construct($message, $statusCode);
    }
}
