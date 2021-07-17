<?php

namespace DarkGhostHunter\Laraconfig\Migrator\Pipes;

use Closure;
use DarkGhostHunter\Laraconfig\Eloquent\Metadata;
use DarkGhostHunter\Laraconfig\Eloquent\Setting;
use DarkGhostHunter\Laraconfig\Migrator\Data;
use DarkGhostHunter\Laraconfig\Registrar\Declaration;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Collection;

/**
 * @internal
 */
class UpdateExistingMetadata
{
    /**
     * UpdateExistingMetadata constructor.
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
        // Get the declarations that already exists in the database.
        $updatable = $this->getUpdatableMetadata($data);

        $count = 0;

        if ($updatable->isNotEmpty()) {
            foreach ($updatable as $declaration) {
                $this->updateMetadata($data, $declaration);
            }

            $data->invalidateCache = true;
        }

        $this->output->info("Updated {$updatable->count()} metadata in the database, with $count updated settings.");

        return $next($data);
    }

    /**
     * Returns a collection of metadata that is already present in the.
     *
     * @param  \DarkGhostHunter\Laraconfig\Migrator\Data  $data
     *
     * @return \Illuminate\Support\Collection|\DarkGhostHunter\Laraconfig\Registrar\Declaration[]
     */
    protected function getUpdatableMetadata(Data $data): Collection
    {
        // We will find the declarations that exists in the database, and that are not
        // equal to them. If it doesn't exists, it must be created, and if it's equal
        // to the original metadata, no changes should be made to it.
        return $data->declarations->filter(static function (Declaration $declaration) use ($data): bool {
            /** @var \DarkGhostHunter\Laraconfig\Eloquent\Metadata $metadata */
            if ($metadata = $data->metadata->get($declaration->name)) {
                $placeholder = $declaration->toMetadata();

                return $placeholder->only('name', 'type', 'default', 'is_enabled', 'bag', 'group')
                    !== $metadata->only('name', 'type', 'default', 'is_enabled', 'bag', 'group');
            }

            return false;
        });
    }

    /**
     * Updates each existing metadata from its declaration of the same name.
     *
     * @param  \DarkGhostHunter\Laraconfig\Migrator\Data  $data
     * @param  \DarkGhostHunter\Laraconfig\Registrar\Declaration  $declaration
     *
     * @return int
     */
    protected function updateMetadata(Data $data, Declaration $declaration): int
    {
        /** @var \DarkGhostHunter\Laraconfig\Eloquent\Metadata $metadata */
        $metadata = $data->metadata->get($declaration->name);

        $metadata->forceFill([
            'type'       => $declaration->type,
            'default'    => $declaration->default,
            'bag'        => $declaration->bag,
            'is_enabled' => $declaration->enabled,
            'group'      => $declaration->group,
        ]);

        $metadata->save();

        // If the declaration has a procedure, we will update the settings of each user
        // using that. This is a great place to do it since we're already iterating on
        // the declarations that only need an update.
        if ($declaration->using) {
            return $this->updateSettingValues($declaration, $metadata);
        }

        return 0;
    }

    /**
     * Update each child setting (of each user) using the declaration procedure.
     *
     * @param  \DarkGhostHunter\Laraconfig\Registrar\Declaration  $declaration
     * @param  \DarkGhostHunter\Laraconfig\Eloquent\Metadata  $metadata
     *
     * @return int
     */
    protected function updateSettingValues(Declaration $declaration, Metadata $metadata): int
    {
        // Since we're updating the settings of each user, we will just iterate over
        // each of them one by one and just hit the "update" button on the setting.
        $oldSettings = Setting::query()
            ->where('metadata_id', $metadata->getKey())
            ->lazyById(column: 'user_settings.id');

        $count = 0;

        foreach ($oldSettings as $setting) {
            $setting->update(['value' => ($declaration->using)($setting)]);
            $count++;
        }

        return $count;
    }
}