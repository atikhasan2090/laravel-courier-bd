<?php

namespace Shipkit\CourierBD\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Shipkit\CourierBD\Enums\DeliveryStatus;
use Shipkit\CourierBD\Models\Shipment;

class ShipmentStatusUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $consignmentId,
        public DeliveryStatus $previousStatus,
        public DeliveryStatus $newStatus,
        public string $courier,
        public ?Shipment $shipment = null
    ) {}
}
