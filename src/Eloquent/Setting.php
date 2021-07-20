<?php

namespace DarkGhostHunter\Laraconfig\Eloquent;

use DarkGhostHunter\Laraconfig\MorphManySettings;
use DarkGhostHunter\Laraconfig\SettingsCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property-read \Illuminate\Database\Eloquent\Model $user
 *
 * @property-read int $id
 *
 * @property null|array|bool|string|int|float|\Illuminate\Support\Collection|\Illuminate\Support\Carbon $value
 * @property boolean $is_enabled
 *
 * @property-read string $name // Added by the "add-metadata" global scope.
 * @property-read string $type // Added by the "add-metadata" global scope.
 * @property-read \Illuminate\Support\Carbon|\Illuminate\Support\Collection|array|string|int|float|bool|null $default // Added by the "add-metadata" global scope.
 * @property-read string $group // Added by the "add-metadata" global scope.
 * @property-read string $bag // Added by the "add-metadata" global scope.
 *
 * @property-read \DarkGhostHunter\Laraconfig\Eloquent\Metadata $metadata
 */
class Setting extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_settings';

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'value'      => Casts\DynamicCasting::class,
        'default'    => Casts\DynamicCasting::class,
        'is_enabled' => 'boolean',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = ['value', 'is_enabled'];

    /**
     * The attributes that should be visible in serialization.
     *
     * @var array
     */
    protected $visible = ['value', 'name', 'group', 'is_disabled'];

    /**
     * Parent bags used for scoping.
     *
     * @var array|null
     */
    public ?array $parentBags = null;

    /**
     * Settings cache repository.
     *
     * @var \DarkGhostHunter\Laraconfig\SettingsCache|null
     */
    public ?SettingsCache $cache = null;

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot(): void
    {
        parent::boot();

        static::updated(static function (Setting $setting): void {
            // Immediately after saving we will invalidate the cache of the
            // settings, and mark the cache ready to regenerate once there
            // is no more work to be done with the settings themselves.
            $setting->invalidateCache();
        });
    }

    /**
     * Perform any actions required after the model boots.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new Scopes\AddMetadata());
    }

    /**
     * The parent metadata.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function metadata(): BelongsTo
    {
        return $this->belongsTo(Metadata::class, 'metadata_id');
    }

    /**
     * The user this settings belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function user(): MorphTo
    {
        return $this->morphTo('settable');
    }

    /**
     * Fills the settings data from a Metadata model instance.
     *
     * @param  \DarkGhostHunter\Laraconfig\Eloquent\Metadata  $metadata
     *
     * @return $this
     */
    public function fillFromMetadata(Metadata $metadata): static
    {
        return $this->forceFill(
            $metadata->only('name', 'type', 'default', 'group', 'bag')
        )->syncOriginal();
    }

    /**
     * Sets a value into the setting and saves it immediately.
     *
     * @param  mixed  $value
     * @param  bool  $force  When "false", it will be only set if its enabled.
     *
     * @return bool "true" on success, or "false" if it's disabled.
     */
    public function set(mixed $value, bool $force = true): bool
    {
        if ($force || $this->isEnabled()) {
            $this->setAttribute('value', $value)->save();
        }

        return $this->isEnabled();
    }

    /**
     * Sets a value into the setting if it's enabled.
     *
     * @param  mixed  $value
     *
     * @return bool "true" on success, or "false" if it's disabled.
     */
    public function setIfEnabled(mixed $value): bool
    {
        return $this->set($value, false);
    }

    /**
     * Reverts back the setting to its default value.
     *
     * @return void
     */
    public function setDefault(): void
    {
        // We will retrieve the default value if it was not retrieved.
        if (!isset($this->attributes['default'])) {
            // By setting the same attribute as original we can skip saving it.
            // We will also use the Query Builder directly to avoid the value
            // being casted, as we need it raw, and let model be set as is.
            $this->attributes['default'] =
            $this->original['default'] = $this->metadata()->getQuery()->value('default');
        }

        $this->set($this->default);
    }

    /**
     * Enables the setting.
     *
     * @param  bool  $enable
     *
     * @return void
     */
    public function enable(bool $enable = true): void
    {
        $this->update(['is_enabled' => $enable]);
    }

    /**
     * Disables the setting.
     *
     * @return void
     */
    public function disable(): void
    {
        $this->enable(false);
    }

    /**
     * Check if the current setting is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->is_enabled === true;
    }

    /**
     * Check if the current settings is disabled.
     *
     * @return bool
     */
    public function isDisabled(): bool
    {
        return !$this->isEnabled();
    }

    /**
     * Forcefully invalidates the cache from this setting.
     *
     * @return void
     */
    public function invalidateCache(): void
    {
        // If an instance of the Settings Cache helper exists, we will use that.
        if ($this->cache) {
            // Invalidate the cache immediately, as is no longer representative.
            $this->cache->invalidateIfNotInvalidated();
            // Mark the cache to be regenerated once is destructed.
            $this->cache->regenerateOnExit();
        } elseif (config('laraconfig.cache.enable', false)) {
            [$morph, $id] = $this->getMorphs('settable', null, null);

            cache()
                ->store(config('laraconfig.cache.store'))
                ->forget(
                    MorphManySettings::generateKeyForModel(
                        config('laraconfig.cache.prefix'),
                        $this->getAttribute($morph),
                        $this->getAttribute($id)
                    )
                );
        }
    }
}
