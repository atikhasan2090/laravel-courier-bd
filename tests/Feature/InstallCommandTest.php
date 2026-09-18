<?php

namespace Shipkit\CourierBD\Tests\Feature;

use Shipkit\CourierBD\Tests\TestCase;

class InstallCommandTest extends TestCase
{
    public function test_install_command_runs_successfully(): void
    {
        $this->artisan('shipkit:install')
            ->expectsOutput('Installing Laravel Courier BD package...')
            ->expectsOutput('Publishing configuration...')
            ->expectsOutput('Publishing migrations...')
            ->expectsOutput('Laravel Courier BD installed successfully!')
            ->assertExitCode(0);
    }
}
