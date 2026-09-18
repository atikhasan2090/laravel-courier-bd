<?php

namespace Shipkit\CourierBD\Tests\Unit;

use Shipkit\CourierBD\Enums\DeliveryStatus;
use Shipkit\CourierBD\Models\Shipment;
use Shipkit\CourierBD\Tests\TestCase;

class ShipmentModelTest extends TestCase
{
    public function test_can_create_shipment_and_casts_work(): void
    {
        $shipment = Shipment::create([
            'merchant_order_id' => 'ORD-TEST-001',
            'courier' => 'pathao',
            'consignment_id' => 'PTH998877',
            'status' => DeliveryStatus::Pending,
            'delivery_fee' => 60.0,
            'cod_fee' => 10.0,
            'total_fee' => 70.0,
            'tracking_url' => 'https://pathao.com/courier/tracking?consignment_id=PTH998877',
            'recipient_name' => 'Abul Hasan',
            'recipient_phone' => '01711223344',
            'recipient_address' => 'Dhaka, Bangladesh',
            'raw_response' => ['sample' => 'data'],
        ]);

        $this->assertDatabaseHas('shipments', [
            'consignment_id' => 'PTH998877',
            'merchant_order_id' => 'ORD-TEST-001',
            'courier' => 'pathao',
        ]);

        $fresh = Shipment::find($shipment->id);
        $this->assertInstanceOf(DeliveryStatus::class, $fresh->status);
        $this->assertEquals(DeliveryStatus::Pending, $fresh->status);
        $this->assertSame(60.0, $fresh->delivery_fee);
        $this->assertSame(10.0, $fresh->cod_fee);
        $this->assertSame(70.0, $fresh->total_fee);
        $this->assertIsArray($fresh->raw_response);
        $this->assertEquals(['sample' => 'data'], $fresh->raw_response);
    }

    public function test_shipment_scopes(): void
    {
        Shipment::create([
            'merchant_order_id' => 'ORD-1',
            'courier' => 'pathao',
            'consignment_id' => 'C1',
            'status' => DeliveryStatus::Pending,
        ]);

        Shipment::create([
            'merchant_order_id' => 'ORD-2',
            'courier' => 'redx',
            'consignment_id' => 'C2',
            'status' => DeliveryStatus::InTransit,
        ]);

        Shipment::create([
            'merchant_order_id' => 'ORD-3',
            'courier' => 'steadfast',
            'consignment_id' => 'C3',
            'status' => DeliveryStatus::Delivered,
        ]);

        Shipment::create([
            'merchant_order_id' => 'ORD-4',
            'courier' => 'pathao',
            'consignment_id' => 'C4',
            'status' => DeliveryStatus::Cancelled,
        ]);

        $this->assertCount(1, Shipment::pending()->get());
        $this->assertCount(1, Shipment::inTransit()->get());
        $this->assertCount(1, Shipment::delivered()->get());
        $this->assertCount(1, Shipment::cancelled()->get());

        $this->assertCount(1, Shipment::forMerchantOrder('ORD-1')->get());
        $this->assertCount(1, Shipment::forConsignment('C2')->get());
    }
}
