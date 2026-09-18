<?php

namespace Shipkit\CourierBD\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Shipkit\CourierBD\DTOs\OrderRequest;
use Shipkit\CourierBD\Enums\DeliveryStatus;
use Shipkit\CourierBD\Events\ShipmentCreated;
use Shipkit\CourierBD\Events\ShipmentDelivered;
use Shipkit\CourierBD\Events\ShipmentStatusUpdated;
use Shipkit\CourierBD\Facades\Courier;
use Shipkit\CourierBD\Models\Shipment;
use Shipkit\CourierBD\Tests\TestCase;

class AutoLogShipmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'shipkit.couriers.pathao' => [
                'sandbox' => true,
                'client_id' => 'test_client',
                'client_secret' => 'test_secret',
                'username' => 'test_user',
                'password' => 'test_pass',
                'store_id' => 123,
            ],
            'shipkit.auto_log' => true,
        ]);
    }

    public function test_create_order_auto_logs_shipment_and_dispatches_event(): void
    {
        Event::fake([ShipmentCreated::class]);

        Http::fake([
            '*/aladdin/api/v1/issue-token' => Http::response([
                'access_token' => 'mocked_access_token',
                'token_type' => 'Bearer',
            ], 200),
            '*/aladdin/api/v1/orders' => Http::response([
                'data' => [
                    'consignment_id' => 'PTH_AUTO_100',
                    'delivery_fee' => 60.0,
                    'cod_fee' => 0.0,
                ],
            ], 200),
        ]);

        $order = new OrderRequest(
            merchantOrderId: 'INV-AUTO-LOG',
            recipientName: 'Sakib Hassan',
            recipientPhone: '+8801712345678', // Tests phone normalization as well!
            recipientAddress: 'House 5, Road 2, Dhanmondi, Dhaka',
            recipientCity: 'Dhaka', // Tests string city fallback
            recipientZone: 'Dhanmondi', // Tests string zone fallback
            amountToCollect: 1250,
            itemWeight: 1.0,
            itemDescription: 'Gadget'
        );

        $response = Courier::via('pathao')->createOrder($order);

        $this->assertEquals('PTH_AUTO_100', $response->consignmentId);

        // Verify Shipment was saved to database
        $this->assertDatabaseHas('shipments', [
            'merchant_order_id' => 'INV-AUTO-LOG',
            'courier' => 'pathao',
            'consignment_id' => 'PTH_AUTO_100',
            'recipient_phone' => '01712345678', // phone was normalized
            'delivery_fee' => 60.0,
            'total_fee' => 60.0,
        ]);

        // Verify ShipmentCreated event was dispatched with the saved model
        Event::assertDispatched(ShipmentCreated::class, function ($event) {
            return $event->orderResponse->consignmentId === 'PTH_AUTO_100'
                && $event->shipment instanceof Shipment
                && $event->shipment->consignment_id === 'PTH_AUTO_100';
        });
    }

    public function test_webhook_updates_database_shipment(): void
    {
        Event::fake([ShipmentStatusUpdated::class, ShipmentDelivered::class]);

        // Pre-create shipment record in database (simulating previous createOrder)
        $shipment = Shipment::create([
            'merchant_order_id' => 'INV-WEBHOOK-TEST',
            'courier' => 'pathao',
            'consignment_id' => 'PTH_WH_555',
            'status' => DeliveryStatus::Pending,
            'delivery_fee' => 60.0,
            'recipient_phone' => '01711111111',
        ]);

        $this->assertEquals(DeliveryStatus::Pending, $shipment->status);

        // Receive webhook stating it is delivered
        $payload = [
            'consignment_id' => 'PTH_WH_555',
            'order_status' => 'Delivered',
        ];

        $response = $this->postJson('/shipkit/webhooks/pathao', $payload);
        $response->assertStatus(200);

        // Check database was updated
        $freshShipment = Shipment::forConsignment('PTH_WH_555')->first();
        $this->assertNotNull($freshShipment);
        $this->assertEquals(DeliveryStatus::Delivered, $freshShipment->status);
        $this->assertArrayHasKey('webhook', $freshShipment->raw_response);

        // Check events dispatched
        Event::assertDispatched(ShipmentStatusUpdated::class);
        Event::assertDispatched(ShipmentDelivered::class);
    }
}
