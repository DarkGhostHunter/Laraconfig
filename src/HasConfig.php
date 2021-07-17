<?php

namespace DarkGhostHunter\Laraconfig;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

use function method_exists;

/**
 * @property-read \DarkGhostHunter\Laraconfig\SettingsCollection<\DarkGhostHunter\Laraconfig\Eloquent\Setting>|\DarkGhostHunter\Laraconfig\Eloquent\Setting[] $settings
 *
 * @method Builder|static whereConfig(string|array $name, mixed $value = null)
 */
trait HasConfig
{
    /**
     * Returns the settings relationship.
     *
     * @return \DarkGhostHunter\Laraconfig\MorphManySettings
     */
    public function settings(): MorphManySettings
    {
        $instance = $this->newRelatedInstance(Eloquent\Setting::class);

        [$type, $id] = $this->getMorphs('settable', null, null);

        $table = $instance->getTable();

        return new MorphManySettings(
            $instance->newQuery(), $this, $table.'.'.$type, $table.'.'.$id, $this->getKeyName()
        );
    }

    /**
     * Boot the current trait.
     *
     * @return void
     */
    protected static function bootHasConfig(): void
    {
        static::addGlobalScope(new Eloquent\Scopes\WhereConfig());

        static::created(
            static function (Model $model): void {
                // If there is no method, or there is and returns true, we will initialize.
                if (!method_exists($model, 'shouldInitializeConfig') || $model->shouldInitializeConfig()) {
                    $model->settings()->initialize();
                }
            }
        );
    }
}
