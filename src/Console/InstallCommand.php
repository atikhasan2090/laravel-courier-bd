<?php

namespace Shipkit\CourierBD\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'shipkit:install';

    protected $description = 'Install and publish configuration & assets for Laravel Courier BD';

    public function handle(): int
    {
        $this->info('Installing Laravel Courier BD package...');

        $this->comment('Publishing configuration...');
        $this->call('vendor:publish', [
            '--tag' => 'shipkit-config',
            '--force' => true,
        ]);

        $this->comment('Publishing migrations...');
        $this->call('vendor:publish', [
            '--tag' => 'shipkit-migrations',
        ]);

        $this->info('Laravel Courier BD installed successfully!');
        $this->line('Set your Pathao, RedX, and Steadfast API keys in your .env file.');

        return Command::SUCCESS;
    }
}
