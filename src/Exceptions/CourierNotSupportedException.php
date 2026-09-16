<?php

namespace Shipkit\CourierBD\Exceptions;

use Exception;

class CourierNotSupportedException extends Exception
{
    public static function make(string $driver): self
    {
        return new self("Courier driver [{$driver}] is not supported or missing required configuration.");
    }
}
