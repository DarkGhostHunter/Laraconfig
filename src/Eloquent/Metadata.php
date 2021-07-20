<?php

namespace DarkGhostHunter\Laraconfig\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 *
 * @property-read string $name
 * @property-read string $type
 * @property-read mixed $default
 * @property-read string $bag
 * @property-read string $group
 * @property-read bool $is_enabled
 *
 * @property-read \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Support\Carbon $created_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\DarkGhostHunter\Laraconfig\Eloquent\Setting[] $settings
 *
 * @internal
 */
class Metadata extends Model
{
    /* Just a bunch of constant to check the type of the declaration */
    public const TYPE_ARRAY = 'array';
    public const TYPE_COLLECTION = 'collection';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_STRING = 'string';
    public const TYPE_INTEGER = 'integer';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_FLOAT = 'float';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_settings_metadata';

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'default' => Casts\DynamicCasting::class,
        'is_enabled' => 'boolean'
    ];

    /**
     * The settings this metadata has.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany|\DarkGhostHunter\Laraconfig\Eloquent\Setting
     */
    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class, 'metadata_id');
    }
}
