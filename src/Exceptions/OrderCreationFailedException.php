<?php

namespace Shipkit\CourierBD\Exceptions;

use Exception;

class OrderCreationFailedException extends Exception
{
    public function __construct(
        string $message,
        public int $statusCode = 0,
        public array $rawResponse = []
    ) {
        parent::__construct($message, $statusCode);
    }

    public static function make(string $courier, string $reason, array $rawResponse = [], int $statusCode = 0): self
    {
        return new self("Failed to create order with {$courier}: {$reason}", $statusCode, $rawResponse);
    }
}
