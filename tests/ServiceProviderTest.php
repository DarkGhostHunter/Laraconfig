<?php

namespace Tests;

use DarkGhostHunter\Laraconfig\Facades\Setting;
use DarkGhostHunter\Laraconfig\LaraconfigServiceProvider;
use DarkGhostHunter\Laraconfig\Registrar\SettingRegistrar;

class ServiceProviderTest extends BaseTestCase
{
    public function test_registers_package(): void
    {
        static::assertArrayHasKey(LaraconfigServiceProvider::class, $this->app->getLoadedProviders());
    }

    public function test_facades(): void
    {
        static::assertInstanceOf(SettingRegistrar::class, Setting::getFacadeRoot());
    }

    public function test_uses_config(): void
    {
        static::assertEquals(include(__DIR__.'/../config/laraconfig.php'), config('laraconfig'));
    }

    public function test_publishes_config(): void
    {
        $this->artisan(
            'vendor:publish',
            [
                '--provider' => 'DarkGhostHunter\Laraconfig\LaraconfigServiceProvider',
                '--tag' => 'config',
            ]
        )->execute();

        $this->assertFileEquals(base_path('config/laraconfig.php'), __DIR__ . '/../config/laraconfig.php');

        unlink(base_path('config/laraconfig.php'));
    }
}