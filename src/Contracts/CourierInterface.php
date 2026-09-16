<?php

namespace Shipkit\CourierBD\Contracts;

use Shipkit\CourierBD\DTOs\OrderRequest;
use Shipkit\CourierBD\DTOs\OrderResponse;

interface CourierInterface
{
    /**
     * Create a shipment order with the courier.
     */
    public function createOrder(OrderRequest $order): OrderResponse;

    /**
     * Track a shipment by consignment ID.
     */
    public function track(string $consignmentId): OrderResponse;

    /**
     * Cancel an existing order by consignment ID.
     */
    public function cancelOrder(string $consignmentId): bool;

    /**
     * Calculate delivery fee for an order.
     */
    public function calculateFee(OrderRequest $order): float;

    /**
     * Check if a specific area/zone identifier is covered by the courier.
     */
    public function checkCoverage(string $areaIdentifier): bool;

    /**
     * Get the driver courier name key.
     */
    public function getName(): string;
}
