<?php

namespace Shipkit\CourierBD\Exceptions;

use Exception;

class OrderCreationFailedException extends Exception
{
    public static function make(string $courier, string $reason, array $rawResponse = []): self
    {
        return new self("Failed to create order with {$courier}: {$reason}");
    }
}
