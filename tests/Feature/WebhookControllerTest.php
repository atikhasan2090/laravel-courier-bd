<?php

namespace Shipkit\CourierBD\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Shipkit\CourierBD\Enums\DeliveryStatus;
use Shipkit\CourierBD\Events\ShipmentDelivered;
use Shipkit\CourierBD\Events\ShipmentStatusUpdated;
use Shipkit\CourierBD\Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    public function test_webhook_dispatches_events_for_pathao(): void
    {
        Event::fake([
            ShipmentStatusUpdated::class,
            ShipmentDelivered::class,
        ]);

        $payload = [
            'consignment_id' => 'PTH999',
            'order_status' => 'Delivered',
        ];

        $response = $this->postJson('/shipkit/webhooks/pathao', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'consignment_id' => 'PTH999',
            'courier' => 'pathao',
            'normalized_status' => 'delivered',
        ]);

        Event::assertDispatched(ShipmentStatusUpdated::class, function ($event) {
            return $event->consignmentId === 'PTH999' &&
                $event->newStatus === DeliveryStatus::Delivered;
        });

        Event::assertDispatched(ShipmentDelivered::class, function ($event) {
            return $event->consignmentId === 'PTH999';
        });
    }

    public function test_webhook_dispatches_events_for_steadfast(): void
    {
        Event::fake([
            ShipmentStatusUpdated::class,
        ]);

        $payload = [
            'consignment_id' => 'ST555',
            'status' => 'in_review',
        ];

        $response = $this->postJson('/shipkit/webhooks/steadfast', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'consignment_id' => 'ST555',
            'courier' => 'steadfast',
            'normalized_status' => 'processing',
        ]);

        Event::assertDispatched(ShipmentStatusUpdated::class);
    }

    public function test_webhook_returns_404_for_unsupported_courier(): void
    {
        $response = $this->postJson('/shipkit/webhooks/non_existent_courier', [
            'consignment_id' => '123',
            'status' => 'delivered',
        ]);

        $response->assertStatus(404);
        $response->assertJson([
            'status' => 'error',
        ]);
    }

    public function test_webhook_rejects_unauthorized_request_when_secret_is_set(): void
    {
        config(['shipkit.couriers.pathao.webhook_secret' => 'super_secret_key']);

        $payload = [
            'consignment_id' => 'PTH999',
            'order_status' => 'Delivered',
        ];

        // Request without secret
        $response = $this->postJson('/shipkit/webhooks/pathao', $payload);
        $response->assertStatus(401);

        // Request with correct secret in Authorization header
        $authResponse = $this->postJson('/shipkit/webhooks/pathao', $payload, [
            'Authorization' => 'Bearer super_secret_key',
        ]);
        $authResponse->assertStatus(200);
    }

    public function test_webhook_suppresses_duplicate_delivered_event(): void
    {
        // Pre-create shipment that is ALREADY delivered
        \Shipkit\CourierBD\Models\Shipment::create([
            'merchant_order_id' => 'ORD-DUP',
            'courier' => 'pathao',
            'consignment_id' => 'PTH-DUP-1',
            'status' => DeliveryStatus::Delivered,
        ]);

        Event::fake([ShipmentDelivered::class, ShipmentStatusUpdated::class]);

        $response = $this->postJson('/shipkit/webhooks/pathao', [
            'consignment_id' => 'PTH-DUP-1',
            'order_status' => 'Delivered',
        ]);

        $response->assertStatus(200);

        // ShipmentDelivered should NOT be dispatched because it was already delivered
        Event::assertNotDispatched(ShipmentDelivered::class);
    }
}
