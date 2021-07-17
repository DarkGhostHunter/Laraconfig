<?php

namespace DarkGhostHunter\Laraconfig\Migrator\Pipes;

use Closure;
use DarkGhostHunter\Laraconfig\Migrator\Data;
use RuntimeException;

/**
 * @internal
 */
class EnsureFromTargetsExist
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
        if (count($absent = $this->nonExistentTargets($data))) {
            throw new RuntimeException(
                'One or more migrations have non-existent targets: '.implode(', ', $absent).'.'
            );
        }

        return $next($data);
    }

    /**
     * Returns the targets of migrated settings that don't exists in the database.
     *
     * @return string[]
     */
    protected function nonExistentTargets(Data $data): array
    {
        $absent = [];

        // Check if each migration target is contained in the database, or is declared as new.
        // OMFG this look like spaghetti code. What it does is relatively simple: we check if
        // the migration target exists in the manifest or it already exists in the database.
        foreach ($data->declarations as $declaration) {
            if ($declaration->from
                && !$data->declarations->has($declaration->from)
                && !$data->metadata->has($declaration->from)) {
                $absent[] = $declaration->from;
            }
        }

        return $absent;
    }
}