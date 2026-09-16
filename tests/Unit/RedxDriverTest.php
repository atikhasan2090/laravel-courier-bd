<?php

namespace Shipkit\CourierBD\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Shipkit\CourierBD\DTOs\OrderRequest;
use Shipkit\CourierBD\Enums\DeliveryStatus;
use Shipkit\CourierBD\Facades\Courier;
use Shipkit\CourierBD\Tests\TestCase;

class RedxDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'shipkit.couriers.redx' => [
                'sandbox' => true,
                'api_token' => 'mocked_redx_token',
            ],
        ]);
    }

    public function test_redx_order_creation(): void
    {
        Http::fake([
            '*/parcels' => Http::response([
                'tracking_id' => 'REDX789',
                'charge' => 65.0,
                'cod_charge' => 15.0,
            ], 200),
        ]);

        $order = new OrderRequest(
            merchantOrderId: 'ORD-202',
            recipientName: 'Karim Chowdhury',
            recipientPhone: '01800000000',
            recipientAddress: 'Banani, Dhaka',
            amountToCollect: 2000,
            itemWeight: 0.8
        );

        $response = Courier::via('redx')->createOrder($order);

        $this->assertEquals('REDX789', $response->consignmentId);
        $this->assertEquals(DeliveryStatus::Pending, $response->status);
        $this->assertEquals(65.0, $response->deliveryFee);
        $this->assertEquals(15.0, $response->codFee);
        $this->assertEquals('redx', $response->courier);
    }

    public function test_redx_tracking(): void
    {
        Http::fake([
            '*/parcels/REDX789' => Http::response([
                'parcel' => [
                    'status' => 'in-transit',
                    'charge' => 65.0,
                    'merchant_invoice_id' => 'ORD-202',
                ],
            ], 200),
        ]);

        $response = Courier::via('redx')->track('REDX789');

        $this->assertEquals('REDX789', $response->consignmentId);
        $this->assertEquals(DeliveryStatus::InTransit, $response->status);
    }
}
