<?php

namespace DarkGhostHunter\Laraconfig\Migrator;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * @internal
 */
class Data
{
    /**
     * Database Metadata.
     *
     * @var \Illuminate\Database\Eloquent\Collection|\DarkGhostHunter\Laraconfig\Eloquent\Metadata[]
     */
    public EloquentCollection $metadata;

    /**
     * Declarations.
     *
     * @var \Illuminate\Support\Collection|\DarkGhostHunter\Laraconfig\Registrar\Declaration[]
     */
    public Collection $declarations;

    /**
     * Models to check for bags.
     *
     * @var \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Model[]
     */
    public Collection $models;

    /**
     * If the cache should be invalidated on settings changes.
     *
     * @var bool
     */
    public bool $invalidateCache = false;

    /**
     * Invalidate the cache through the models instead of the settings.
     *
     * @var bool
     */
    public bool $useModels = false;

    /**
     * Data constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->models = new Collection();
    }
}