<?php

namespace DarkGhostHunter\Laraconfig;

use DarkGhostHunter\Laraconfig\Registrar\SettingRegistrar;
use Generator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * @internal
 */
class LaraconfigServiceProvider extends ServiceProvider
{
    /**
     * The migration files.
     *
     * @var array|string[]
     */
    protected const MIGRATION_FILES = [
        __DIR__ . '/../database/migrations/00_00_00_000000_create_user_settings_table.php',
        __DIR__ . '/../database/migrations/00_00_00_000000_create_user_settings_metadata_table.php',
    ];

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laraconfig.php', 'laraconfig');

        $this->app->singleton(SettingRegistrar::class, static function($app): SettingRegistrar {
            return new SettingRegistrar(
                $app['config'],
                new Collection(),
                new Collection(),
                $app[Filesystem::class],
                $app
            );
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\MigrateCommand::class,
                Console\Commands\PublishCommand::class,
                Console\Commands\CleanCommand::class,
            ]);

            $this->publishes([__DIR__.'/../config/laraconfig.php' => config_path('laraconfig.php')], 'config');

            $this->publishes(iterator_to_array($this->migrationPathNames()), 'migrations');
        }
    }

    /**
     * Returns the migration file destination path name.
     *
     * @return \Generator
     */
    protected function migrationPathNames(): Generator
    {
        foreach (static::MIGRATION_FILES as $file) {
            yield $file => $this->app->databasePath(
                'migrations/' . now()->format('Y_m_d_His') . Str::after($file, '00_00_00_000000')
            );
        }
    }
}
