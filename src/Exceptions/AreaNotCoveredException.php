<?php

namespace Shipkit\CourierBD\Exceptions;

use Exception;

class AreaNotCoveredException extends Exception
{
    public static function make(string $courier, string $area): self
    {
        return new self("The area [{$area}] is not covered by {$courier} courier service.");
    }
}
