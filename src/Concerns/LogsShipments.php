<?php

namespace Shipkit\CourierBD\Concerns;

use Shipkit\CourierBD\DTOs\OrderRequest;
use Shipkit\CourierBD\DTOs\OrderResponse;
use Shipkit\CourierBD\Events\ShipmentCreated;
use Shipkit\CourierBD\Models\Shipment;

trait LogsShipments
{
    /**
     * Record shipment in database if auto-logging is enabled and dispatch ShipmentCreated event.
     */
    protected function recordShipment(OrderRequest $order, OrderResponse $response): OrderResponse
    {
        $shipment = null;

        if (config('shipkit.auto_log', true) && ! empty($response->consignmentId)) {
            try {
                $shipment = Shipment::create([
                    'merchant_order_id' => $order->merchantOrderId,
                    'courier' => $response->courier,
                    'consignment_id' => $response->consignmentId,
                    'status' => $response->status,
                    'delivery_fee' => $response->deliveryFee,
                    'cod_fee' => $response->codFee,
                    'total_fee' => $response->getTotalFee(),
                    'tracking_url' => $response->trackingUrl,
                    'recipient_name' => $order->recipientName,
                    'recipient_phone' => $order->getNormalizedPhone(),
                    'recipient_address' => $order->recipientAddress,
                    'raw_response' => $response->rawResponse,
                ]);
            } catch (\Throwable $e) {
                // Ignore DB logging failure (e.g., table not migrated or DB connection issue)
            }
        }

        event(new ShipmentCreated($response, $shipment));

        return $response;
    }
}
