<?php

namespace Shipkit\CourierBD\Contracts;

use Illuminate\Http\Request;
use Shipkit\CourierBD\DTOs\OrderRequest;
use Shipkit\CourierBD\DTOs\OrderResponse;
use Shipkit\CourierBD\Enums\DeliveryStatus;

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
     * Map a courier-specific raw status string to normalized DeliveryStatus enum.
     */
    public function mapStatus(string $rawStatus): DeliveryStatus;

    /**
     * Verify incoming webhook request authentication / signature.
     */
    public function verifyWebhook(Request $request): bool;

    /**
     * Get the driver courier name key.
     */
    public function getName(): string;
}
