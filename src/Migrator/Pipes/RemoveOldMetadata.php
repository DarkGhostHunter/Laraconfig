<?php

namespace DarkGhostHunter\Laraconfig\Migrator\Pipes;

use Closure;
use DarkGhostHunter\Laraconfig\Eloquent\Metadata;
use DarkGhostHunter\Laraconfig\Migrator\Data;
use Illuminate\Console\OutputStyle;
use Illuminate\Database\Eloquent\Collection;

/**
 * @internal
 */
class RemoveOldMetadata
{
    /**
     * RemoveOldMetadata constructor.
     *
     * @param  \Illuminate\Console\OutputStyle  $output
     */
    public function __construct(protected OutputStyle $output)
    {
    }

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
        $count = 0;
        $affected = 0;

        // Find the metadata not present in the manifest.
        foreach ($this->toDelete($data) as $metadata) {
            $affected += $metadata->settings()->delete();
            $metadata->delete();
            $count++;

            $data->invalidateCache = true;
        }

        $this->output->info("Deleted $count metadata settings, with $affected settings deleted.");

        return $next($data);
    }

    /**
     * Returns a collection of Metadata not present in the manifest.
     *
     * @param  \DarkGhostHunter\Laraconfig\Migrator\Data  $data
     *
     * @return \Illuminate\Database\Eloquent\Collection|\DarkGhostHunter\Laraconfig\Eloquent\Metadata[]
     */
    protected function toDelete(Data $data): Collection
    {
        return $data->metadata->reject(static function (Metadata $metadata) use ($data): bool {
            return $data->declarations->has($metadata->name);
        });
    }
}