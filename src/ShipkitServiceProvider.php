<?php

namespace Shipkit\CourierBD;

use Illuminate\Support\ServiceProvider;
use Shipkit\CourierBD\Console\InstallCommand;

class ShipkitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/shipkit.php', 'shipkit');

        $this->app->singleton('courier', function ($app) {
            return new CourierManager($app);
        });

        $this->app->alias('courier', CourierManager::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/shipkit.php' => config_path('shipkit.php'),
            ], 'shipkit-config');

            $timestamp = date('Y_m_d_His');
            $this->publishes([
                __DIR__ . '/../database/migrations/create_shipments_table.php.stub' => database_path("migrations/{$timestamp}_create_shipments_table.php"),
            ], 'shipkit-migrations');

            $this->commands([
                InstallCommand::class,
            ]);
        }

        $this->loadRoutesFrom(__DIR__ . '/../routes/webhooks.php');
    }
}
