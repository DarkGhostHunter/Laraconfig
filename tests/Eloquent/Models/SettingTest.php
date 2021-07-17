<?php

namespace Tests\Eloquent\Models;

use DarkGhostHunter\Laraconfig\Eloquent\Metadata;
use DarkGhostHunter\Laraconfig\Eloquent\Scopes\AddMetadata;
use DarkGhostHunter\Laraconfig\Eloquent\Setting;
use DarkGhostHunter\Laraconfig\SettingsCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\BaseTestCase;

class SettingTest extends BaseTestCase
{
    use RefreshDatabase;

    protected Metadata $metadata;
    protected Setting $setting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->metadata = Metadata::make()->forceFill([
            'name'    => 'foo',
            'type'    => 'string',
            'default' => 'bar',
            'bag'     => 'users',
            'group'   => 'default',
        ]);

        $this->metadata->save();

        $this->setting = Setting::make()->forceFill([
            'settable_id' => 1,
            'settable_type' => 'bar',
            'metadata_id' => 1,
        ]);
    }

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');
    }

    public function test_adds_metadata(): void
    {
        /** @var \DarkGhostHunter\Laraconfig\Eloquent\Setting $setting */
        $setting = Setting::make()
            ->setRawAttributes(['value' => 'quz'])
            ->forceFill([
                'settable_type' => 'foo',
                'settable_id'   => 1,
            ]);

        $setting->metadata()->associate($this->metadata);

        $setting->save();

        $setting = Setting::find($setting->getKey());

        static::assertEquals('foo', $setting->name);
        static::assertEquals('string', $setting->type);
        static::assertEquals('bar', $setting->default);
        static::assertEquals('default', $setting->group);
        static::assertEquals('users', $setting->bag);
    }

    public function test_casts_array(): void
    {
        $this->metadata->forceFill([
            'type' => 'array',
        ])->save();

        $this->setting->save();

        $this->setting = Setting::find(1);

        $this->setting->set(['foo', 'bar']);

        $this->assertDatabaseHas('user_settings', ['id' => 1, 'value' => '["foo","bar"]']);

        static::assertEquals(['foo', 'bar'], Setting::find(1)->value);
    }

    public function test_casts_boolean(): void
    {
        $this->metadata->forceFill([
            'type' => 'boolean',
        ])->save();

        $this->setting->save();

        $this->setting = Setting::find(1);

        $this->setting->set(true);

        $this->assertDatabaseHas('user_settings', ['id' => 1, 'value' => '1']);

        static::assertTrue(Setting::find(1)->value);
    }

    public function test_casts_collection(): void
    {
        $this->metadata->forceFill([
            'type' => 'collection',
        ])->save();

        $this->setting->save();

        $this->setting = Setting::find(1);

        $this->setting->set(['foo', 'bar']);

        $this->assertDatabaseHas('user_settings', ['id' => 1, 'value' => '["foo","bar"]']);

        $setting = Setting::find(1);

        static::assertInstanceOf(Collection::class, $setting->value);
        static::assertEquals(new Collection(['foo', 'bar']), $setting->value);
    }

    public function test_casts_collection_using_collection_object(): void
    {
        $this->metadata->forceFill([
            'type' => 'collection',
        ])->save();

        $this->setting->save();

        $this->setting = Setting::find(1);

        $this->setting->set(new Collection(['foo', 'bar']));

        $this->assertDatabaseHas('user_settings', ['id' => 1, 'value' => '["foo","bar"]']);

        $setting = Setting::find(1);

        static::assertInstanceOf(Collection::class, $setting->value);
        static::assertEquals(new Collection(['foo', 'bar']), $setting->value);
    }

    public function test_casts_datetime(): void
    {
        Carbon::setTestNow($now = now());

        $this->metadata->forceFill([
            'type' => 'datetime',
        ])->save();

        $this->setting->save();

        $this->setting = Setting::find(1);

        $this->setting->set($now->toDateTimeString());

        $this->assertDatabaseHas('user_settings', ['id' => 1, 'value' => $now->toDateTimeString()]);

        $setting = Setting::find(1);

        static::assertInstanceOf(Carbon::class, $setting->value);
        static::assertEquals($now->milli(0), $setting->value);
    }

    public function test_casts_datetime_using_datetime(): void
    {
        Carbon::setTestNow($now = now());

        $this->metadata->forceFill([
            'type' => 'datetime',
        ])->save();

        $this->setting->save();

        $this->setting = Setting::find(1);

        $this->setting->set($now);

        $this->assertDatabaseHas('user_settings', ['id' => 1, 'value' => $now->toDateTimeString()]);

        $setting = Setting::find(1);

        static::assertInstanceOf(Carbon::class, $setting->value);
        static::assertEquals($now->milli(0), $setting->value);
    }

    public function test_casts_float(): void
    {
        $this->metadata->forceFill([
            'type' => 'float',
        ])->save();

        $this->setting->save();

        $this->setting = Setting::find(1);

        $this->setting->set(6548.575);

        $this->assertDatabaseHas('user_settings', ['id' => 1, 'value' => '6548.575']);

        $setting = Setting::find(1);

        static::assertEquals(6548.575, $setting->value);
    }

    public function test_casts_integer(): void
    {
        $this->metadata->forceFill([
            'type' => 'float',
        ])->save();

        $this->setting->save();

        $this->setting = Setting::find(1);

        $this->setting->set(6548);

        $this->assertDatabaseHas('user_settings', ['id' => 1, 'value' => '6548']);

        $setting = Setting::find(1);

        static::assertEquals(6548, $setting->value);
    }

    public function test_casts_string(): void
    {
        $this->metadata->forceFill([
            'type' => 'string',
        ])->save();

        $this->setting->save();

        $this->setting = Setting::find(1);

        $this->setting->set('foobar');

        $this->assertDatabaseHas('user_settings', ['id' => 1, 'value' => 'foobar']);

        $setting = Setting::find(1);

        static::assertEquals('foobar', $setting->value);
    }

    public function test_sets_value_if_enabled(): void
    {
        $this->metadata->forceFill([
            'type' => 'string',
        ])->save();

        $this->setting->fill([
            'value' => 'foo',
            'is_enable' => false
        ])->save();

        $setting = Setting::find(1);

        $setting->set('bar');

        static::assertEquals('bar', $setting->value);
    }

    public function test_enabled_or_disabled(): void
    {
        $this->metadata->forceFill([
            'type' => 'string',
        ])->save();

        $this->setting->fill([
            'value' => 'foo',
        ])->save();

        $setting = Setting::find(1);

        $setting->disable();

        static::assertFalse($setting->isEnabled());
        static::assertTrue($setting->isDisabled());
        $this->assertDatabaseHas('user_settings', ['id' => 1, 'is_enabled' => false]);

        $setting->enable();

        static::assertTrue($setting->isEnabled());
        static::assertFalse($setting->isDisabled());

        $this->assertDatabaseHas('user_settings', ['id' => 1, 'is_enabled' => true]);
    }

    public function test_set_invalidates_cache_of_laraconfig(): void
    {
        $this->setting->fill(['value' => 'foo'])->save();

        $cache = $this->mock(SettingsCache::class);

        $cache->shouldReceive('invalidateIfNotInvalidated')->once();
        $cache->shouldReceive('regenerateOnExit')->once();

        $setting = Setting::find(1);

        $setting->cache = $cache;

        $setting->set('bar');
    }

    public function test_set_invalidates_cache_manually(): void
    {
        config()->set('laraconfig.cache.enable', true);

        cache()->store()->forever('laraconfig|bar|1', 'foo');

        $this->setting->fill(['value' => 'foo'])->save();

        $setting = Setting::find(1);

        $setting->set('bar');

        static::assertNull(cache()->store()->get('laraconfig|bar|1'));
    }

    public function test_adds_metadata_on_select_query(): void
    {
        static::assertEquals(
            'select "user_settings"."id", "user_settings_metadata"."name" as "name", "user_settings_metadata"."type" as "type", "user_settings_metadata"."bag" as "bag", "user_settings_metadata"."default" as "default", "user_settings_metadata"."group" as "group" from "user_settings" inner join "user_settings_metadata" on "user_settings"."metadata_id" = "user_settings_metadata"."id"',
            Setting::query()->select('id')->toSql()
        );
    }

    public function test_adds_metadata_on_select_all_query(): void
    {
        static::assertEquals(
            'select "user_settings".*, "user_settings_metadata"."name" as "name", "user_settings_metadata"."type" as "type", "user_settings_metadata"."bag" as "bag", "user_settings_metadata"."default" as "default", "user_settings_metadata"."group" as "group" from "user_settings" inner join "user_settings_metadata" on "user_settings"."metadata_id" = "user_settings_metadata"."id"',
            Setting::query()->toSql()
        );
    }

    public function test_disables_add_metadata_scope(): void
    {
        static::assertEquals(
            'select * from "user_settings"',
            Setting::query()->withoutGlobalScope(AddMetadata::class)->toSql()
        );
    }
}