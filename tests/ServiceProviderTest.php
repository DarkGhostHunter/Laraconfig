<?php

namespace Tests;

use DarkGhostHunter\Laraconfig\Facades\Setting;
use DarkGhostHunter\Laraconfig\LaraconfigServiceProvider;
use DarkGhostHunter\Laraconfig\Registrar\SettingRegistrar;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ServiceProviderTest extends BaseTestCase
{
    /** @var \Illuminate\Filesystem\Filesystem */
    protected mixed $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = $this->app->make(Filesystem::class);
    }

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

        static::assertFileEquals(base_path('config/laraconfig.php'), __DIR__ . '/../config/laraconfig.php');
    }

    public function test_publishes_migrations(): void
    {
        $this->filesystem->ensureDirectoryExists(database_path('migrations'));

        $this->artisan(
            'vendor:publish',
            [
                '--provider' => 'DarkGhostHunter\Laraconfig\LaraconfigServiceProvider',
                '--tag' => 'migrations',
            ]
        )->run();

        static::assertTrue(
            collect($this->filesystem->files($this->app->databasePath('migrations')))
                ->contains(function (\SplFileInfo $file) {
                    return Str::endsWith($file->getPathname(), '_create_user_settings_table.php')
                        || Str::endsWith($file->getPathname(), '_create_user_settings_metadata_table.php');
                })
        );
    }

    protected function tearDown(): void
    {
        $this->filesystem->delete(base_path('config/laraconfig.php'));
        $this->filesystem->cleanDirectory(database_path('migrations'));

        parent::tearDown();
    }
}
