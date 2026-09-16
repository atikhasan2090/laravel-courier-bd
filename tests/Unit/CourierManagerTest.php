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
}
