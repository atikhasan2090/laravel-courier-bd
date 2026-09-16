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
}
