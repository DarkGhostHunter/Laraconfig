<?php

namespace DarkGhostHunter\Laraconfig\Migrator\Pipes;

use Closure;
use DarkGhostHunter\Laraconfig\Eloquent\Metadata;
use DarkGhostHunter\Laraconfig\Eloquent\Setting;
use DarkGhostHunter\Laraconfig\Migrator\Data;
use DarkGhostHunter\Laraconfig\Registrar\Declaration;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @internal
 */
class CreateNewMetadata
{
    /**
     * CreateNewMetadata constructor.
     *
     * @param  \Illuminate\Console\OutputStyle  $output
     * @param  \Illuminate\Support\Carbon  $now
     */
    public function __construct(protected OutputStyle $output, protected Carbon $now)
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
        // Get all the declarations that don't exist in the database.
        $toPersist = $this->declarationsToPersist($data);

        $count = 0;

        // Create declarations not present in the metadata
        if ($toPersist->isNotEmpty()) {
            foreach ($toPersist as $declaration) {

                // First, persist the declaration as metadata in the database.
                $metadata = $this->createMetadata($declaration);

                // Then, fill the settings for each user using the same metadata bag.
                $count += $this->fillSettingsFromMetadata($declaration, $metadata, $data->models, $data);

                $data->metadata->put($declaration->name, $metadata);
            }

            $data->invalidateCache = true;
        }

        $this->output->info("Added {$toPersist->count()} new settings, with $count new setting rows.");

        return $next($data);
    }

    /**
     * Creates the metadata from the declaration.
     *
     * @param  \DarkGhostHunter\Laraconfig\Registrar\Declaration  $declaration
     *
     * @return \DarkGhostHunter\Laraconfig\Eloquent\Metadata
     */
    protected function createMetadata(Declaration $declaration): Metadata
    {
        return tap($declaration->toMetadata())->save();
    }

    /**
     * Returns a collection of declarations that don't exist in the database.
     *
     * @param  \DarkGhostHunter\Laraconfig\Migrator\Data  $data
     *
     * @return \Illuminate\Support\Collection
     */
    protected function declarationsToPersist(Data $data): Collection
    {
        return $data->declarations->reject(static function (Declaration $declaration) use ($data): bool {
            return $data->metadata->has($declaration->name);
        });
    }

    /**
     * Fill the settings of the newly created Metadata.
     *
     * @param  \DarkGhostHunter\Laraconfig\Registrar\Declaration  $declaration
     * @param  \DarkGhostHunter\Laraconfig\Eloquent\Metadata  $metadata
     * @param  \Illuminate\Support\Collection  $models
     * @param  \DarkGhostHunter\Laraconfig\Migrator\Data  $data
     *
     * @return int
     */
    protected function fillSettingsFromMetadata(
        Declaration $declaration,
        Metadata $metadata,
        Collection $models,
        Data $data
    ): int
    {
        // If the new metadata is not using "from", we will just create the settings
        // for each user with just simply one query, leaving the hard work to the
        // database engine instead of using this script.
        if (!$declaration->from) {
            return $this->fillSettings($metadata, $models);
        }

        // If we're just using a "from", and NOT a procedure, we will copy-paste the
        // value of the old settings and just change the parent metadata id of each
        // row for the one of the new metadata.
        if (!$declaration->using) {
            return $this->copySettings($metadata, $data->metadata->get($declaration->from));
        }

        // If we're using "from" with a procedure, we will create a new Setting with
        // the value of the old setting we're lazily querying, which will take time
        // but it will be safer than playing weird queries on the database itself.
        return $this->migrateSettings(
            $declaration, $metadata, $data->metadata->get($declaration->from)
        );
    }

    /**
     * Fill the settings for each of the models using settings.
     *
     * @param  \DarkGhostHunter\Laraconfig\Eloquent\Metadata  $metadata
     * @param  \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Model[]  $models
     *
     * @return int
     */
    protected function fillSettings(Metadata $metadata, Collection $models): int
    {
        $affected = 0;

        // We will run an SQL query to create the settings for each user by
        // simply inserting them by each user. We will also point the ID of
        // both the Metadata parent and user, along with the default value.
        foreach ($models as $model) {
            $affected += Setting::query()->insertUsing(
                ['metadata_id', 'settable_id', 'settable_type', 'value', 'created_at', 'updated_at'],
                $model->newQuery()
                    ->select([
                    DB::raw("'{$metadata->getKey()}' as metadata_id"),
                    DB::raw("{$model->getKeyName()} as settable_id"),
                    DB::raw("'".addcslashes($model->getMorphClass(), '\\') ."' as settable_type"),
                    DB::raw("'{$metadata->getRawOriginal('default', 'NULL')}' as value"),
                    DB::raw("'{$this->now->toDateTimeString()}' as created_at"),
                    DB::raw("'{$this->now->toDateTimeString()}' as updated_at"),
                ])->getQuery()
            );
        }

        return $affected;
    }

    /**
     * Copy the settings for each of the models from the old setting.
     *
     * @param  \DarkGhostHunter\Laraconfig\Eloquent\Metadata  $new
     * @param  \DarkGhostHunter\Laraconfig\Eloquent\Metadata  $old
     *
     * @return int
     */
    protected function copySettings(Metadata $new, Metadata $old): int
    {
        // We will simply query all the settings that reference the old metadata
        // and "clone" each model set, but using the new metadata id.
        return Setting::query()->insertUsing(
            ['metadata_id', 'settable_id', 'settable_type', 'value', 'created_at', 'updated_at'],
            Setting::query()->where('metadata_id', $old->getKey())
                ->select([
                    DB::raw("'{$new->getKey()}' as metadata_id"),
                    'settable_id',
                    'settable_type',
                    'value', // Here we will just instruct to copy the value raw to the new setting.
                    DB::raw("'{$this->now->toDateTimeString()}' as created_at"),
                    DB::raw("'{$this->now->toDateTimeString()}' as updated_at"),
                ])->getQuery()
        );
    }

    /**
     * Feeds each old setting to a procedure that saves the new setting value.
     *
     * @param  \DarkGhostHunter\Laraconfig\Registrar\Declaration  $declaration
     * @param  \DarkGhostHunter\Laraconfig\Eloquent\Metadata  $new
     * @param  \DarkGhostHunter\Laraconfig\Eloquent\Metadata  $old
     *
     * @return int
     */
    protected function migrateSettings(Declaration $declaration, Metadata $new, Metadata $old): int
    {
        $affected = 0;

        /** @var \DarkGhostHunter\Laraconfig\Eloquent\Setting $setting */
        foreach (Setting::query()->where('metadata_id', $old->getKey())->lazyById() as $setting) {
            Setting::query()
                ->insert([
                    'metadata_id' => $new->getKey(),
                    'settable_type' => $setting->getAttribute('settable_type'),
                    'settable_id' => $setting->getAttribute('settable_id'),
                    'is_enabled' => $setting->is_enabled,
                    'value' => ($declaration->using)($setting),
                    'created_at' => $this->now->toDateTimeString(),
                    'updated_at' => $this->now->toDateTimeString(),
                ]);

            $affected++;
        }

        return $affected;
    }
}
