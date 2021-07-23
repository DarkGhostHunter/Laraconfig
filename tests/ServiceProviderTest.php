<?php

namespace Tests;

use DarkGhostHunter\Laraconfig\Facades\Setting;
use DarkGhostHunter\Laraconfig\LaraconfigServiceProvider;
use DarkGhostHunter\Laraconfig\Registrar\SettingRegistrar;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use SplFileInfo;

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

        $files = collect($this->filesystem->files($this->app->databasePath('migrations')));

        static::assertTrue($files->contains(
            static function (SplFileInfo $file): bool {
                return preg_match('/.+\d{4}_\d{2}_\d{2}_\d{6}_(create_user_settings_table.php)$/', $file->getPathname());
            })
        );

        static::assertTrue($files->contains(
            static function (SplFileInfo $file): bool {
                return preg_match('/.+\d{4}_\d{2}_\d{2}_\d{6}_(create_user_settings_metadata_table.php)$/', $file->getPathname());
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
