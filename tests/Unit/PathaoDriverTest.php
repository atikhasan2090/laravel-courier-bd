<?php

namespace Shipkit\CourierBD\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Shipkit\CourierBD\DTOs\OrderRequest;
use Shipkit\CourierBD\Enums\DeliveryStatus;
use Shipkit\CourierBD\Facades\Courier;
use Shipkit\CourierBD\Tests\TestCase;

class PathaoDriverTest extends TestCase
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
        ]);
    }

    public function test_pathao_order_creation(): void
    {
        Http::fake([
            '*/aladdin/api/v1/issue-token' => Http::response([
                'access_token' => 'mocked_access_token',
                'token_type' => 'Bearer',
            ], 200),
            '*/aladdin/api/v1/orders' => Http::response([
                'type' => 'success',
                'code' => 200,
                'data' => [
                    'consignment_id' => 'PTH123456',
                    'delivery_fee' => 60.0,
                    'cod_fee' => 0.0,
                ],
            ], 200),
        ]);

        $order = new OrderRequest(
            merchantOrderId: 'ORD-101',
            recipientName: 'Rahim Uddin',
            recipientPhone: '01700000000',
            recipientAddress: 'Dhanmondi 32, Dhaka',
            recipientCity: '1',
            recipientZone: '1',
            amountToCollect: 1500,
            itemWeight: 1.0,
            itemDescription: 'T-shirt'
        );

        $response = Courier::via('pathao')->createOrder($order);

        $this->assertEquals('PTH123456', $response->consignmentId);
        $this->assertEquals(DeliveryStatus::Pending, $response->status);
        $this->assertEquals(60.0, $response->deliveryFee);
        $this->assertEquals('pathao', $response->courier);
    }

    public function test_pathao_tracking(): void
    {
        Http::fake([
            '*/aladdin/api/v1/issue-token' => Http::response([
                'access_token' => 'mocked_access_token',
            ], 200),
            '*/aladdin/api/v1/orders/PTH123456/info' => Http::response([
                'data' => [
                    'order_status' => 'Delivered',
                    'merchant_order_id' => 'ORD-101',
                    'delivery_fee' => 60.0,
                ],
            ], 200),
        ]);

        $response = Courier::via('pathao')->track('PTH123456');

        $this->assertEquals('PTH123456', $response->consignmentId);
        $this->assertEquals(DeliveryStatus::Delivered, $response->status);
    }
}
