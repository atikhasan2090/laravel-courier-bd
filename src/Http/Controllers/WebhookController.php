<?php

namespace Shipkit\CourierBD\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Shipkit\CourierBD\Enums\DeliveryStatus;
use Shipkit\CourierBD\Events\ShipmentDelivered;
use Shipkit\CourierBD\Events\ShipmentStatusUpdated;
use Shipkit\CourierBD\Facades\Courier;
use Shipkit\CourierBD\Models\Shipment;

class WebhookController extends Controller
{
    public function handle(Request $request, string $courier): JsonResponse
    {
        $payload = $request->all();

        $consignmentId = match ($courier) {
            'pathao' => (string) ($payload['consignment_id'] ?? $payload['order_id'] ?? ''),
            'redx' => (string) ($payload['tracking_id'] ?? $payload['parcel_id'] ?? ''),
            'steadfast' => (string) ($payload['consignment_id'] ?? $payload['tracking_code'] ?? ''),
            default => (string) ($payload['consignment_id'] ?? ''),
        };

        $rawStatus = match ($courier) {
            'pathao' => (string) ($payload['order_status'] ?? $payload['order_status_slug'] ?? ''),
            'redx' => (string) ($payload['status'] ?? $payload['parcel_status'] ?? ''),
            'steadfast' => (string) ($payload['status'] ?? $payload['delivery_status'] ?? ''),
            default => (string) ($payload['status'] ?? ''),
        };

        if (empty($consignmentId) || empty($rawStatus)) {
            return response()->json(['status' => 'ignored', 'message' => 'Missing consignment_id or status'], 400);
        }

        try {
            /** @var \Shipkit\CourierBD\Contracts\CourierInterface $driver */
            $driver = Courier::via($courier);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => "Unsupported courier driver [{$courier}]",
            ], 404);
        }

        if (! $driver->verifyWebhook($request)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized webhook request',
            ], 401);
        }

        // Map status using driver method
        $newStatus = $driver->mapStatus($rawStatus);

        $shipment = null;
        $previousStatus = DeliveryStatus::Pending;

        try {
            if (config('shipkit.auto_log', true)) {
                $shipment = Shipment::where('consignment_id', $consignmentId)->first();
                if ($shipment) {
                    $previousStatus = $shipment->status;
                    $shipment->status = $newStatus;
                    $existingRaw = is_array($shipment->raw_response) ? $shipment->raw_response : [];
                    $shipment->raw_response = array_merge($existingRaw, ['webhook' => $payload]);
                    $shipment->save();
                }
            }
        } catch (\Throwable $e) {
            // DB connection or logging optional
        }

        // Dispatch status updated event
        event(new ShipmentStatusUpdated($consignmentId, $previousStatus, $newStatus, $courier, $shipment));

        // Dispatch delivered event only on first delivery transition
        if ($newStatus === DeliveryStatus::Delivered && $previousStatus !== DeliveryStatus::Delivered) {
            event(new ShipmentDelivered($consignmentId, $courier, $shipment));
        }

        return response()->json([
            'status' => 'success',
            'consignment_id' => $consignmentId,
            'courier' => $courier,
            'normalized_status' => $newStatus->value,
        ]);
    }
}
