<?php

namespace Shipkit\CourierBD\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Shipkit\CourierBD\Facades\Courier;
use Shipkit\CourierBD\ShipkitServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ShipkitServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Courier' => Courier::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Setup default config if needed
    }
}
