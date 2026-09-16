<?php

namespace Shipkit\CourierBD\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Shipkit\CourierBD\DTOs\OrderRequest;
use Shipkit\CourierBD\Enums\DeliveryStatus;
use Shipkit\CourierBD\Facades\Courier;
use Shipkit\CourierBD\Tests\TestCase;

class SteadfastDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'shipkit.couriers.steadfast' => [
                'sandbox' => true,
                'api_key' => 'mocked_key',
                'secret_key' => 'mocked_secret',
            ],
        ]);
    }

    public function test_steadfast_order_creation(): void
    {
        Http::fake([
            '*/create_order' => Http::response([
                'status' => 200,
                'message' => 'Order created successfully',
                'consignment' => [
                    'consignment_id' => 'ST100200',
                    'delivery_charge' => 70.0,
                    'cod_charge' => 0.0,
                ],
            ], 200),
        ]);

        $order = new OrderRequest(
            merchantOrderId: 'ORD-303',
            recipientName: 'Sakib Al Hasan',
            recipientPhone: '01900000000',
            recipientAddress: 'Mirpur 10, Dhaka',
            amountToCollect: 1000
        );

        $response = Courier::via('steadfast')->createOrder($order);

        $this->assertEquals('ST100200', $response->consignmentId);
        $this->assertEquals(DeliveryStatus::Pending, $response->status);
        $this->assertEquals(70.0, $response->deliveryFee);
        $this->assertEquals('steadfast', $response->courier);
    }

    public function test_steadfast_tracking(): void
    {
        Http::fake([
            '*/status_by_cid/ST100200' => Http::response([
                'status' => 200,
                'delivery_status' => 'delivered',
                'invoice' => 'ORD-303',
            ], 200),
        ]);

        $response = Courier::via('steadfast')->track('ST100200');

        $this->assertEquals('ST100200', $response->consignmentId);
        $this->assertEquals(DeliveryStatus::Delivered, $response->status);
    }
}
