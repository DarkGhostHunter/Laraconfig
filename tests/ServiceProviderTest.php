<?php

namespace Tests;

use DarkGhostHunter\Laraconfig\Facades\Setting;
use DarkGhostHunter\Laraconfig\LaraconfigServiceProvider;
use DarkGhostHunter\Laraconfig\Registrar\SettingRegistrar;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;

class ServiceProviderTest extends BaseTestCase
{
    /** @var \Illuminate\Support\Carbon */
    static protected $now;

    /** @var \Illuminate\Filesystem\Filesystem */
    protected mixed $filesystem;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        static::$now = Carbon::create(2020, 1, 1, 19, 30);

        Carbon::setTestNow(static::$now);
    }

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

        static::assertFileEquals(
            database_path('migrations/2020_01_01_193000_create_user_settings_table.php'),
            __DIR__ . '/../database/migrations/00_00_00_000000_create_user_settings_table.php'
        );

        static::assertFileEquals(
            database_path('migrations/2020_01_01_193000_create_user_settings_metadata_table.php'),
            __DIR__ . '/../database/migrations/00_00_00_000000_create_user_settings_metadata_table.php'
        );
    }

    protected function tearDown(): void
    {
        if ($this->filesystem->exists(base_path('config/laraconfig.php'))) {
            $this->filesystem->delete(base_path('config/laraconfig.php'));
        }

        if ($this->filesystem->exists(database_path('00_00_00_000000_create_user_settings_metadata_table.php'))) {
            $this->filesystem->delete(database_path('00_00_00_000000_create_user_settings_metadata_table.php'));
        }

        if ($this->filesystem->exists(database_path('00_00_00_000000_create_user_settings_metadata_table.php'))) {
            $this->filesystem->delete(database_path('00_00_00_000000_create_user_settings_metadata_table.php'));
        }

        parent::tearDown();
    }
}
