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

    protected function setUp(): void
    {
        parent::setUp();

        $migration = include __DIR__ . '/../database/migrations/create_shipments_table.php.stub';
        $migration->up();
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('shipkit.auto_log', true);
    }
}
