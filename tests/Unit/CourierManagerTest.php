<?php

namespace Shipkit\CourierBD\Tests\Unit;

use Shipkit\CourierBD\Contracts\CourierInterface;
use Shipkit\CourierBD\Drivers\PathaoCourier;
use Shipkit\CourierBD\Drivers\RedxCourier;
use Shipkit\CourierBD\Drivers\SteadfastCourier;
use Shipkit\CourierBD\Facades\Courier;
use Shipkit\CourierBD\Tests\TestCase;

class CourierManagerTest extends TestCase
{
    public function test_it_resolves_default_driver(): void
    {
        $driver = Courier::driver();

        $this->assertInstanceOf(CourierInterface::class, $driver);
        $this->assertInstanceOf(SteadfastCourier::class, $driver);
    }

    public function test_it_resolves_pathao_driver(): void
    {
        $driver = Courier::via('pathao');

        $this->assertInstanceOf(PathaoCourier::class, $driver);
        $this->assertEquals('pathao', $driver->getName());
    }

    public function test_it_resolves_redx_driver(): void
    {
        $driver = Courier::via('redx');

        $this->assertInstanceOf(RedxCourier::class, $driver);
        $this->assertEquals('redx', $driver->getName());
    }

    public function test_it_resolves_steadfast_driver(): void
    {
        $driver = Courier::via('steadfast');

        $this->assertInstanceOf(SteadfastCourier::class, $driver);
        $this->assertEquals('steadfast', $driver->getName());
    }

    public function test_it_throws_courier_not_supported_exception_for_unknown_driver(): void
    {
        $this->expectException(\Shipkit\CourierBD\Exceptions\CourierNotSupportedException::class);
        Courier::via('dhl_bangladesh');
    }

    public function test_order_request_helpers(): void
    {
        $order = \Shipkit\CourierBD\DTOs\OrderRequest::fromArray([
            'order_id' => 'ORD-123',
            'phone' => '+8801712-345 678',
            'city' => 'Dhaka',
            'address' => 'Mirpur, Dhaka',
        ]);

        $this->assertEquals('01712345678', $order->recipientPhone);
        $this->assertEquals('01712345678', $order->getNormalizedPhone());
        $this->assertTrue($order->isInsideDhaka());

        $outsideOrder = \Shipkit\CourierBD\DTOs\OrderRequest::fromArray([
            'order_id' => 'ORD-456',
            'phone' => '8801812345678',
            'city' => 'Chittagong',
            'address' => 'GEC Circle, Chittagong',
        ]);

        $this->assertEquals('01812345678', $outsideOrder->recipientPhone);
        $this->assertFalse($outsideOrder->isInsideDhaka());
    }
}
