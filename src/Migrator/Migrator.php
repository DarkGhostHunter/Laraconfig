<?php

namespace DarkGhostHunter\Laraconfig\Migrator;

use Illuminate\Pipeline\Pipeline;

/**
 * @internal
 */
class Migrator extends Pipeline
{
    /**
     * The array of class pipes.
     *
     * @var array
     */
    protected $pipes = [
        Pipes\FlushCache::class,
        Pipes\LoadDeclarations::class,
        Pipes\LoadMetadata::class,
        Pipes\FindModelsWithSettings::class,
        Pipes\EnsureSomethingToMigrate::class,
        Pipes\EnsureFromTargetsExist::class,
        Pipes\ConfirmSettingsToDelete::class,
        Pipes\ConfirmSettingsRefresh::class,
        Pipes\UpdateExistingMetadata::class,
        Pipes\CreateNewMetadata::class,
        Pipes\RemoveOldMetadata::class,
        Pipes\InvalidateCache::class,
    ];
}