<?php

namespace Shipkit\CourierBD\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Shipkit\CourierBD\DTOs\OrderResponse;
use Shipkit\CourierBD\Models\Shipment;

class ShipmentCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public OrderResponse $orderResponse,
        public ?Shipment $shipment = null
    ) {}
}
