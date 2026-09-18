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

            $migrationFileName = 'create_shipments_table.php';
            $migrationExists = ! empty(glob(database_path("migrations/*_{$migrationFileName}")));

            if (! $migrationExists) {
                $timestamp = date('Y_m_d_His');
                $this->publishes([
                    __DIR__ . "/../database/migrations/{$migrationFileName}.stub" => database_path("migrations/{$timestamp}_{$migrationFileName}"),
                ], 'shipkit-migrations');
            }

            $this->commands([
                InstallCommand::class,
            ]);
        }

        $this->loadRoutesFrom(__DIR__ . '/../routes/webhooks.php');
    }
}
