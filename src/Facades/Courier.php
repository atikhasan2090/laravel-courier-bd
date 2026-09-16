<?php

namespace Shipkit\CourierBD\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Shipkit\CourierBD\Contracts\CourierInterface via(string $driver = null)
 * @method static \Shipkit\CourierBD\DTOs\OrderResponse createOrder(\Shipkit\CourierBD\DTOs\OrderRequest $order)
 * @method static \Shipkit\CourierBD\DTOs\OrderResponse track(string $consignmentId)
 * @method static bool cancelOrder(string $consignmentId)
 * @method static float calculateFee(\Shipkit\CourierBD\DTOs\OrderRequest $order)
 * @method static bool checkCoverage(string $areaIdentifier)
 * @method static array compareFees(\Shipkit\CourierBD\DTOs\OrderRequest $order)
 *
 * @see \Shipkit\CourierBD\CourierManager
 */
class Courier extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'courier';
    }
}
