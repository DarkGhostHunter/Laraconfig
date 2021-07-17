<?php

namespace Tests\Console\Commands;

use DarkGhostHunter\Laraconfig\Eloquent\Metadata;
use DarkGhostHunter\Laraconfig\Eloquent\Setting as SettingModel;
use DarkGhostHunter\Laraconfig\Facades\Setting;
use DarkGhostHunter\Laraconfig\Migrator\Data;
use DarkGhostHunter\Laraconfig\Registrar\SettingRegistrar;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\BaseTestCase;
use Tests\Dummies\DummyModel;

class MigrateCommandTest extends BaseTestCase
{
    use RefreshDatabase;

    protected Filesystem $filesystem;

    protected Collection $models;

    protected Data $data;

    public function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();

        $this->filesystem->ensureDirectoryExists(base_path('settings'));

        $this->data = new Data;

        $this->data->models = $this->models = new Collection([
            new DummyModel
        ]);

        $this->swap(Data::class, $this->data);

        DB::table('users')->insert([
            [
                'name' => 'charlie',
                'email' => 'charlie@email.com',
                'password' => '123456',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'oscar',
                'email' => 'oscar@email.com',
                'password' => '123456',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'delta',
                'email' => 'delta@email.com',
                'password' => '123456',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Executes a callback on "production" environment.
     *
     * @param  \Closure  $closure
     */
    protected function runWhileOnProduction(\Closure $closure) : void
    {
        $original = $this->app['env'];

        $this->app['env'] = 'production';

        $closure();

        $this->app['env'] = $original;
    }

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
    }

    protected function resetDeclarations(): void
    {
        foreach ($declarations = $this->app->make(SettingRegistrar::class)->getDeclarations() as $key => $declaration) {
            $declarations->forget($key);
        }
    }

    public function test_confirms_deletion_on_production(): void
    {
        Metadata::make()->forceFill([
            'name' => 'foo',
            'type' => 'string',
            'default' => null,
            'bag' => 'users',
            'group' => 'default',
            'is_enabled' => true,
        ])->save();

        SettingModel::make()->forceFill([
            'metadata_id' => 1,
            'settable_id' => 1,
            'settable_type' => 'Dummy',
        ])->save();

        $this->runWhileOnProduction(function () {
            $this->artisan('settings:migrate')
                ->expectsConfirmation('There are 1 old settings that will be deleted on sync. Proceed?')
                ->expectsOutput('Settings migration has been rejected by the user.')
                ->assertExitCode(1)
                ->run();
        });

        $this->assertDatabaseCount('user_settings_metadata', 1);
        $this->assertDatabaseCount('user_settings', 1);
    }

    public function test_bypass_confirms_deletion_on_production_with_force(): void
    {
        Metadata::make()->forceFill([
            'name' => 'foo',
            'type' => 'string',
            'default' => null,
            'bag' => 'users',
            'group' => 'default',
            'is_enabled' => true,
        ])->save();

        SettingModel::make()->forceFill([
            'metadata_id' => 1,
            'settable_id' => 1,
            'settable_type' => 'Dummy',
        ])->save();

        $this->runWhileOnProduction(function () {
            $this->artisan('settings:migrate', ['--force' => true])
                ->doesntExpectOutput('Settings migration has been rejected by the user.')
                ->assertExitCode(0)
                ->run();
        });

        $this->assertDatabaseCount('user_settings_metadata', 0);
        $this->assertDatabaseCount('user_settings', 0);
    }

    public function test_confirms_full_refresh_on_production(): void
    {
        Metadata::make()->forceFill([
            'name' => 'foo',
            'type' => 'string',
            'default' => null,
            'bag' => 'users',
            'group' => 'default',
            'is_enabled' => true,
        ])->save();

        SettingModel::make()->forceFill([
            'metadata_id' => 1,
            'settable_id' => 1,
            'settable_type' => 'Dummy',
        ])->save();

        $this->runWhileOnProduction(function () {
            $this->artisan('settings:migrate', ['--refresh' => true])
                ->expectsConfirmation('ALL settings will be deleted completely. Proceed?')
                ->expectsOutput('Settings refresh has been rejected by the user.')
                ->assertExitCode(1)
                ->run();
        });

        $this->assertDatabaseCount('user_settings_metadata', 1);
        $this->assertDatabaseCount('user_settings', 1);
    }

    public function test_bypass_confirms_full_refresh_on_production_with_force(): void
    {
        Metadata::make()->forceFill([
            'name' => 'foo',
            'type' => 'string',
            'default' => null,
            'bag' => 'users',
            'group' => 'default',
            'is_enabled' => true,
        ])->save();

        SettingModel::make()->forceFill([
            'metadata_id' => 1,
            'settable_id' => 1,
            'settable_type' => 'Dummy',
        ])->save();

        $this->runWhileOnProduction(function () {
            $this->artisan('settings:migrate', ['--refresh' => true, '--force' => true])
                ->doesntExpectOutput('Settings refresh has been rejected by the user.')
                ->assertExitCode(0)
                ->run();
        });

        $this->assertDatabaseCount('user_settings_metadata', 0);
        $this->assertDatabaseCount('user_settings', 0);
    }

    public function test_doesnt_migrates_if_manifest_empty(): void
    {
        $this->artisan('settings:migrate')
            ->expectsOutput('No metadata exists in the database, and no declaration exists.')
            ->assertExitCode(1)
            ->run();

        $this->assertDatabaseCount('user_settings_metadata', 0);
        $this->assertDatabaseCount('user_settings', 0);
    }

    public function test_migrates_even_if_user_has_different_bag(): void
    {
        Setting::name('foo')->bag('something_else_entirely');

        $this->artisan('settings:migrate')
            ->assertExitCode(0)
            ->run();

        $this->assertDatabaseCount('user_settings_metadata', 1);
        $this->assertDatabaseHas('user_settings_metadata', [
            'bag' => 'something_else_entirely'
        ]);
        $this->assertDatabaseCount('user_settings', 3);
    }

    public function test_runs_migrations_but_result_same_if_manifest_equal_to_database(): void
    {
        DB::table('user_settings_metadata')->insert($bag = [
            'id' => 1,
            'name'=> 'foo',
            'type'=> 'string',
            'default'=> null,
            'group'=> 'default',
            'bag'=> 'something_else_entirely',
            'created_at'=> '2021-07-04 18:16:12',
            'updated_at'=> '2021-07-04 18:16:12'
        ]);

        Setting::name('foo')->bag('something_else_entirely');

        $this->artisan('settings:migrate')
            ->assertExitCode(0)
            ->run();

        $this->assertDatabaseCount('user_settings_metadata', 1);
        $this->assertDatabaseHas('user_settings_metadata', $bag);
        $this->assertDatabaseCount('user_settings', 0);
    }

    public function test_creates_new_setting(): void
    {
        Setting::name('array')->array();
        Setting::name('boolean')->boolean();
        Setting::name('collection')->collection();
        Setting::name('datetime')->datetime();
        Setting::name('float')->float();
        Setting::name('integer')->integer();
        Setting::name('string')->string();

        Carbon::setTestNow($now = now());

        $this->artisan('settings:migrate')
            ->assertExitCode(0)
            ->run();

        $this->assertDatabaseCount('user_settings_metadata', 7);
        $rows = [
            [
                'id' => 1,
                'name' => 'array',
                'type' => 'array',
                'default' => NULL,
                'group' => 'default',
                'bag' => 'users',
                'created_at' => $now->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
            ],
            [
                'id' => 2,
                'name' => 'boolean',
                'type' => 'boolean',
                'default' => NULL,
                'group' => 'default',
                'bag' => 'users',
                'created_at' => $now->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
            ],
            [
                'id' => 3,
                'name' => 'collection',
                'type' => 'collection',
                'default' => NULL,
                'group' => 'default',
                'bag' => 'users',
                'created_at' => $now->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
            ],
            [
                'id' => 4,
                'name' => 'datetime',
                'type' => 'datetime',
                'default' => NULL,
                'group' => 'default',
                'bag' => 'users',
                'created_at' => $now->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
            ],
            [
                'id' => 5,
                'name' => 'float',
                'type' => 'float',
                'default' => NULL,
                'group' => 'default',
                'bag' => 'users',
                'created_at' => $now->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
            ],
            [
                'id' => 6,
                'name' => 'integer',
                'type' => 'integer',
                'default' => NULL,
                'group' => 'default',
                'bag' => 'users',
                'created_at' => $now->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
            ],
            [
                'id' => 7,
                'name' => 'string',
                'type' => 'string',
                'default' => NULL,
                'group' => 'default',
                'bag' => 'users',
                'created_at' => $now->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
            ],
        ];

        foreach ($rows as $row) {
            $this->assertDatabaseHas('user_settings_metadata', $row);
        }

        $this->assertDatabaseCount('user_settings', 7 * 3);

        $this->assertDatabaseHas('user_settings', [
            'id' => 1,
            'metadata_id' => 1,
            'settable_type' => DummyModel::class,
            'settable_id' => 1,
            'value' => '',
            'is_enabled' => true,
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ]);
    }

    public function test_creates_new_settings_and_deletes_old_settings(): void
    {
        Carbon::setTestNow($now = now());

        Setting::name('foo')->default('foo_default');
        Setting::name('bar')->boolean()->default(false)->bag('bar_bag');
        Setting::name('baz')->array()->default(['alpha', 'bravo', 'charlie']);

        $this->artisan('settings:migrate')
            ->assertExitCode(0)
            ->run();

        $this->resetDeclarations();

        Setting::name('foo')->default('new_default');
        Setting::name('quz')->boolean()->default(true)->bag('bar_bag')->from('foo');
        Setting::name('cougar')->string()
            ->from('baz')
            ->using(fn($setting) => Arr::first($setting->value, default: $setting->default));

        $this->artisan('settings:migrate')
            ->assertExitCode(0)
            ->run();

        $rows = [
            [
                'id' => 1,
                'name' => 'foo',
                'type' => 'string',
                'default' => 'new_default',
                'bag' => 'users',
            ],
            [
                'id' => 4,
                'name' => 'quz',
                'type' => 'boolean',
                'default' => true,
                'bag' => 'bar_bag',
            ],
            [
                'id' => 5,
                'name' => 'cougar',
                'type' => 'string',
                'default' => null,
                'bag' => 'users',
            ],
        ];

        // Assert the database has the new metadata
        foreach ($rows as $row) {
            $this->assertDatabaseHas('user_settings_metadata', array_merge($row, [
                'group' => 'default',
                'is_enabled' => true,
                'created_at' => $now->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
            ]));
        }

        $this->assertDatabaseMissing('user_settings_metadata', ['name' => 'bar']);
        $this->assertDatabaseMissing('user_settings_metadata', ['name' => 'baz']);

        $this->assertDatabaseHas('user_settings', [
            'metadata_id' => 1,
            'value' => 'foo_default',
        ]);

        $this->assertDatabaseHas('user_settings', [
            'metadata_id' => 4,
            'value' => 'foo_default', // It copies the value, raw.
        ]);

        $this->assertDatabaseHas('user_settings', [
            'metadata_id' => 5,
            'value' => 'alpha',
        ]);

        $this->assertDatabaseMissing('user_settings', ['metadata_id' => 2]);
        $this->assertDatabaseMissing('user_settings', ['metadata_id' => 3]);

        $this->assertDatabaseCount('user_settings', 3 * 3);
    }

    public function test_deletes_old_settings(): void
    {
        Metadata::make()->forceFill([
            'id' => 1,
            'name' => 'foo',
            'type' => 'string',
            'default' => null,
            'bag' => 'users',
            'group' => 'default',
            'is_enabled' => true,
        ])->save();

        SettingModel::make()->forceFill([
            'metadata_id' => 1,
            'settable_id' => 1,
            'settable_type' => 'Dummy',
        ])->save();

        SettingModel::make()->forceFill([
            'metadata_id' => 1,
            'settable_id' => 2,
            'settable_type' => 'Dummy',
        ])->save();

        Setting::name('bar');

        $this->artisan('settings:migrate')
            ->assertExitCode(0)
            ->run();

        $this->assertDatabaseMissing('user_settings_metadata', ['name' => 'foo']);
        $this->assertDatabaseMissing('user_settings', ['metadata_id' => 1]);
        $this->assertDatabaseHas('user_settings_metadata', ['name' => 'bar']);
        $this->assertDatabaseHas('user_settings', ['metadata_id' => 2]);
    }

    public function test_doesnt_migrates_old_setting_to_old_setting_if_not_different(): void
    {
        Setting::name('foo')->default('bar');

        $this->artisan('settings:migrate')
            ->assertExitCode(0)
            ->run();

        $this->resetDeclarations();

        Setting::name('foo')->default('bar')->from('foo')->using(fn ($setting) => 'quz');

        $this->artisan('settings:migrate')
            ->assertExitCode(0)
            ->run();

        $this->assertDatabaseHas('user_settings_metadata', ['name' => 'foo', 'default' => 'bar']);
        $this->assertDatabaseHas('user_settings', ['value' => 'bar']);
        $this->assertDatabaseCount('user_settings', 3);
    }

    public function test_migrates_old_setting_to_old_setting_with_procedure_when_different(): void
    {
        Setting::name('foo')->default('bar');

        $this->artisan('settings:migrate')
            ->assertExitCode(0)
            ->run();

        $this->resetDeclarations();

        Setting::name('foo')->default('cougar')->using(static fn () => 'cougar');

        $this->artisan('settings:migrate')
            ->assertExitCode(0)
            ->run();

        $this->assertDatabaseHas('user_settings_metadata', ['name' => 'foo', 'default' => 'cougar']);
        $this->assertDatabaseHas('user_settings', ['metadata_id' => 1, 'value' => 'cougar']);
        $this->assertDatabaseCount('user_settings', 3);
    }

    public function test_migrates_old_setting_to_new_setting(): void
    {
        Setting::name('foo')->default('bar');

        $this->artisan('settings:migrate')
            ->assertExitCode(0)
            ->run();

        $this->resetDeclarations();

        Setting::name('foo')->default('bar');
        Setting::name('quz')->from('foo');

        $this->artisan('settings:migrate')
            ->assertExitCode(0)
            ->run();

        $this->assertDatabaseHas('user_settings_metadata', ['name' => 'foo']);
        $this->assertDatabaseHas('user_settings_metadata', ['name' => 'quz']);
        $this->assertDatabaseHas('user_settings', ['metadata_id' => 1, 'value' => 'bar']);
        $this->assertDatabaseHas('user_settings', ['metadata_id' => 2, 'value' => 'bar']);
        $this->assertDatabaseCount('user_settings', 6);
    }

    public function test_migrates_old_setting_to_new_setting_with_procedure(): void
    {
        Setting::name('foo')->default('bar');

        $this->artisan('settings:migrate')
            ->assertExitCode(0)
            ->run();

        $this->resetDeclarations();

        Setting::name('foo')->default('bar');
        Setting::name('quz')->from('foo')->using(static fn () => 'cougar');

        $this->artisan('settings:migrate')
            ->assertExitCode(0)
            ->run();

        $this->assertDatabaseHas('user_settings_metadata', ['name' => 'foo']);
        $this->assertDatabaseHas('user_settings_metadata', ['name' => 'quz']);
        $this->assertDatabaseHas('user_settings', ['metadata_id' => 1, 'value' => 'bar']);
        $this->assertDatabaseHas('user_settings', ['metadata_id' => 2, 'value' => 'cougar']);
        $this->assertDatabaseCount('user_settings', 6);
    }

    public function test_exception_when_migration_target_doesnt_exists(): void
    {
        Metadata::make()->forceFill([
            'id' => 1,
            'name' => 'foo',
            'type' => 'string',
            'default' => null,
            'bag' => 'users',
            'group' => 'default',
            'is_enabled' => true,
        ])->save();

        Setting::name('bar')->from('quz');
        Setting::name('cougar')->from('foo');

        $this->artisan('settings:migrate')
            ->expectsOutput('One or more migrations have non-existent targets: quz.')
            ->assertExitCode(1)
            ->run();
    }

    public function test_exception_when_models_use_same_table(): void
    {
        Metadata::make()->forceFill([
            'name' => 'foo',
            'type' => 'string',
            'default' => null,
            'bag' => 'users',
            'group' => 'default',
            'is_enabled' => true,
        ])->save();

        $this->data->models = new Collection([
            new class extends Model {
                protected $table = 'foo';
            },
            new class extends Model {
                protected $table = 'foo';
            },
            new class extends Model {
                protected $table = 'bar';
            },
            new class extends Model {
                protected $table = 'bar';
            }
        ]);

        $this->artisan('settings:migrate')
            ->expectsOutput('2 models are using the same tables: foo, bar.')
            ->assertExitCode(1)
            ->run();
    }

    public function test_doesnt_regenerates_cache_when_no_changes(): void
    {
        config()->set('laraconfig.cache.enable', true);

        Metadata::make()->forceFill([
            'name' => 'foo',
            'type' => 'string',
            'default' => null,
            'bag' => 'users',
            'group' => 'default',
        ])->save();

        Setting::name('foo');

        $this->swap(Factory::class, $cache = Mockery::mock(Factory::class));

        $cache->shouldNotReceive('store');

        $this->artisan('settings:migrate')
            ->assertExitCode(0)
            ->run();
    }

    public function test_invalidates_cache_when_updates_setting(): void
    {
        Setting::name('foo');

        $this->artisan('settings:migrate')
            ->assertExitCode(0)
            ->run();

        $this->resetDeclarations();

        config()->set('laraconfig.cache.enable', true);
        config()->set('laraconfig.cache.store', 'foo');

        $store = cache()->store();

        $store->forever('laraconfig|Tests\Dummies\DummyModel|1', 'foo');

        /** @var \Mockery\MockInterface $cache */
        $cache = $this->swap(Factory::class, Mockery::mock(Factory::class));

        $cache->shouldReceive('store')
            ->with('foo')
            ->andReturn($store);

        Setting::name('foo')->default('bar');

        $this->artisan('settings:migrate')
            ->assertExitCode(0)
            ->execute();

        static::assertNull($store->get('laraconfig|Tests\Dummies\DummyModel|1'));
    }

    public function test_invalidates_cache_when_creates_setting(): void
    {
        Setting::name('foo');

        $this->artisan('settings:migrate')
            ->assertExitCode(0)
            ->run();

        $this->resetDeclarations();

        config()->set('laraconfig.cache.enable', true);
        config()->set('laraconfig.cache.store', 'foo');

        $store = cache()->store();

        $store->forever('laraconfig|Tests\Dummies\DummyModel|1', 'foo');

        /** @var \Mockery\MockInterface $cache */
        $cache = $this->swap(Factory::class, Mockery::mock(Factory::class));

        $cache->shouldReceive('store')
            ->with('foo')
            ->andReturn($store);

        Setting::name('bar')->default('quz');

        $this->artisan('settings:migrate')
            ->assertExitCode(0)
            ->execute();

        static::assertNull($store->get('laraconfig|Tests\Dummies\DummyModel|1'));
    }

    public function test_invalidates_cache_when_deletes_setting(): void
    {
        Setting::name('foo');

        $this->artisan('settings:migrate')
            ->assertExitCode(0)
            ->run();

        $this->resetDeclarations();

        config()->set('laraconfig.cache.enable', true);
        config()->set('laraconfig.cache.store', 'foo');

        $store = cache()->store();

        $store->forever('laraconfig|Tests\Dummies\DummyModel|1', 'foo');

        /** @var \Mockery\MockInterface $cache */
        $cache = $this->swap(Factory::class, Mockery::mock(Factory::class));

        $cache->shouldReceive('store')
            ->with('foo')
            ->andReturn($store);

        $this->artisan('settings:migrate')
            ->assertExitCode(0)
            ->execute();

        static::assertNull($store->get('laraconfig|Tests\Dummies\DummyModel|1'));
    }

    public function test_flushes_cache(): void
    {
        config()->set('laraconfig.cache.enable', true);
        config()->set('laraconfig.cache.store', 'foo');

        Setting::name('foo');

        $this->swap(Factory::class, $cache = Mockery::mock(Factory::class));

        $cache->shouldReceive('store')
            ->with('foo')
            ->andReturn($this->swap(Store::class, $store = Mockery::mock(Store::class)));

        $store->shouldReceive('flush');

        $this->artisan('settings:migrate', ['--flush-cache' => true])
            ->assertExitCode(0)
            ->run();
    }

    public function test_doesnt_flushes_cache_if_not_enabled(): void
    {
        config()->set('laraconfig.cache.enable', false);

        Setting::name('foo');

        $this->swap(Factory::class, $cache = Mockery::mock(Factory::class));

        $cache->shouldNotReceive('store');

        $this->artisan('settings:migrate', ['--flush-cache' => true])
            ->expectsOutput('Cannot flush cache. Laraconfig cache is not enabled.')
            ->assertExitCode(1)
            ->run();
    }

    public function test_confirms_cache_flush_on_production(): void
    {
        config()->set('laraconfig.cache.enable', true);
        config()->set('laraconfig.cache.store', 'foo');

        Setting::name('foo');

        $this->swap(Factory::class, $cache = Mockery::mock(Factory::class));

        $cache->shouldNotReceive('store');

        $this->runWhileOnProduction(function () {
            $this->artisan('settings:migrate', ['--flush-cache' => true])
                ->expectsConfirmation('The cache store foo will be flushed completely. Proceed?')
                ->expectsOutput('Flush of the foo cache has been cancelled.')
                ->assertExitCode(1)
                ->run();
        });
    }

    public function tearDown(): void
    {
        $filesystem = new Filesystem();

        if ($filesystem->exists(base_path('settings/users.php'))) {
            $filesystem->delete(base_path('settings/users.php'));
        }

        parent::tearDown();
    }
}