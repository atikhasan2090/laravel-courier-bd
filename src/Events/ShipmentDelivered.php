<?php

namespace Shipkit\CourierBD\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Shipkit\CourierBD\Models\Shipment;

class ShipmentDelivered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $consignmentId,
        public string $courier,
        public ?Shipment $shipment = null
    ) {}
}
