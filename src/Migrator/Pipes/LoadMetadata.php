<?php

namespace DarkGhostHunter\Laraconfig\Migrator\Pipes;

use Closure;
use DarkGhostHunter\Laraconfig\Eloquent\Metadata;
use DarkGhostHunter\Laraconfig\Migrator\Data;

/**
 * @internal
 */
class LoadMetadata
{
    /**
     * Handles the Settings migration.
     *
     * @param  \DarkGhostHunter\Laraconfig\Migrator\Data  $data
     * @param  \Closure  $next
     *
     * @return mixed
     */
    public function handle(Data $data, Closure $next): mixed
    {
        $data->metadata = Metadata::all()->keyBy(static fn(Metadata $metadata): string => $metadata->name);

        return $next($data);
    }
}