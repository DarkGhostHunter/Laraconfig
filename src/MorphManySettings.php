<?php

namespace DarkGhostHunter\Laraconfig;

use DarkGhostHunter\Laraconfig\Eloquent\Metadata;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder
 * @mixin \DarkGhostHunter\Laraconfig\Eloquent\Setting
 *
 * @method Model|HasConfig getParent()
 */
class MorphManySettings extends MorphMany
{
    /**
     * Bags of the model.
     *
     * @var array
     */
    protected array $bags;

    /**
     * The settings cache helper.
     *
     * @var \DarkGhostHunter\Laraconfig\SettingsCache|null
     */
    protected ?SettingsCache $cache = null;

    /**
     * MorphManySettings constructor.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  string  $type
     * @param  string  $id
     * @param  string  $localKey
     */
    public function __construct(Builder $query, Model $parent, string $type, string $id, string $localKey)
    {
        $this->windUp($parent);

        parent::__construct($query, $parent, $type, $id, $localKey);
    }

    /**
     * Prepares the relation instance to be handled.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     *
     * @return void
     */
    public function windUp(Model $parent): void
    {
        $config = app('config');

        // We'll enable the cache for the settings only if is enabled in the
        // application config. This object will handle the cache easily and
        // will receive the instruction from the collection to regenerate.
        if ($config->get('laraconfig.cache.enable', false)) {
            $this->cache = SettingsCache::make($config, app(Factory::class), $parent);
        }

        // And filter the bags if the model has stated them.
        $this->bags = Arr::wrap(
            method_exists($parent, 'filterBags') ? $parent->filterBags() : $config->get('laraconfig.default', 'users')
        );
    }

    /**
     * Get the relationship query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Builder  $parentQuery
     * @param  array|mixed  $columns
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*']): Builder
    {
        // We will add the global scope only when checking for existence.
        // This should appear on SELECT instead of other queries.
        return parent::getRelationExistenceQuery($query, $parentQuery, $columns)
            ->whereHas('metadata', function (Builder $query): void {
                $query->withGlobalScope(Eloquent\Scopes\FilterBags::class, new Eloquent\Scopes\FilterBags($this->bags));
            });
    }

    /**
     * Generates the key for the model to save into the cache.
     *
     * @param  string  $prefix
     * @param  string  $morphClass
     * @param  string|int  $key
     *
     * @return string
     */
    public static function generateKeyForModel(string $prefix, string $morphClass, string|int $key): string
    {
        return implode('|', [trim($prefix, '|'), $morphClass, $key]);
    }

    /**
     * Initializes the Settings Repository for a given user.
     *
     * @param  bool  $force
     *
     * @return void
     */
    public function initialize(bool $force = false): void
    {
        if (!$force && $this->isInitialized()) {
            return;
        }

        // Pre-emptively delete all dangling settings from the user.
        $query = tap($this->getParent()->settings()->newQuery())->delete();

        // Invalidate the cache immediately.
        $this->cache?->invalidate();

        // Add the collection to the relation, avoiding retrieving them again later.
        $this->getParent()->setRelation('settings', $settings = new SettingsCollection());

        foreach (Metadata::query()->lazyById(column: 'id') as $metadatum) {
            $setting = $query->make()->forceFill([
                'metadata_id' => $metadatum->getKey(),
                'value' => $metadatum->default
            ]);

            $setting->saveQuietly();

            // We will hide the settings not part of the model bags.
            if (in_array($metadatum->bag, $this->bags, true)) {
                $setting->bags = $this->bags;
                $settings->push($setting->fillFromMetadata($metadatum));
            }
        }

        $settings->cache = $this->cache;
    }

    /**
     * Checks if the user settings has been initialized.
     *
     * @return bool
     */
    public function isInitialized(): bool
    {
        return $this->getParent()->settings()->count() === Metadata::query()->count();
    }

    /**
     * Adds a cache instance to the setting models, if there is one.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $settings
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function prepareCollection(EloquentCollection $settings): EloquentCollection
    {
        return $settings->keyBy(function (Eloquent\Setting $setting): string {
            $setting->cache = $this->cache;
            $setting->parentBags = $this->bags;

            return $setting->name;
        });
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults(): SettingsCollection
    {
        // If the developer loads the relation, before that we will check if
        // the cache is enabled. If that's the case, we will try first to
        // retrieve them from the cache store before hitting the table.
        $collection = new SettingsCollection(
            $this->prepareCollection($this->cache?->retrieve() ?? parent::getResults())->all()
        );

        if ($this->cache) {
            $collection->cache = $this->cache;
            $this->cache->setSettings($collection);
        }

        return $collection;
    }

    /**
     * Returns all the bags being used by the model.
     *
     * @return array
     */
    public function bags(): array
    {
        return $this->bags;
    }
}
