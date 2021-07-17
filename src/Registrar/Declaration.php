<?php

namespace DarkGhostHunter\Laraconfig\Registrar;

use Closure;
use DarkGhostHunter\Laraconfig\Eloquent\Metadata;

class Declaration
{
    /**
     * Type of the setting.
     *
     * @internal
     *
     * @var string
     */
    public string $type = Metadata::TYPE_STRING;

    /**
     * The default value, if any.
     *
     * @internal
     *
     * @var mixed
     */
    public mixed $default = null;

    /**
     * If it should be registered as enabled.
     *
     * @internal
     *
     * @var bool
     */
    public bool $enabled = true;

    /**
     * The group the setting belongs to.
     *
     * @internal
     *
     * @var string
     */
    public string $group = 'default';

    /**
     * Sets the Declaration to migrate the value from other old value.
     *
     * @internal
     *
     * @var string|null
     */
    public ?string $from = null;

    /**
     * A procedure to translate the setting original value to another.
     *
     * @internal
     *
     * @var \Closure|null
     */
    public null|Closure $using = null;

    /**
     * Declaration constructor.
     *
     * @param  string  $name
     * @param  string  $bag
     */
    public function __construct(public string $name, public string $bag)
    {
    }

    /**
     * Sets the setting type as 'string'.
     *
     * @return $this
     */
    public function string(): static
    {
        $this->type = Metadata::TYPE_STRING;

        return $this;
    }

    /**
     * Sets the setting type as a boolean.
     *
     * @return $this
     */
    public function boolean(): static
    {
        $this->type = Metadata::TYPE_BOOLEAN;

        return $this;
    }

    /**
     * Sets the setting type as 'integer'.
     *
     * @return $this
     */
    public function integer(): static
    {
        $this->type = Metadata::TYPE_INTEGER;

        return $this;
    }

    /**
     * Sets the setting type as float/decimal.
     *
     * @return $this
     */
    public function float(): static
    {
        $this->type = Metadata::TYPE_FLOAT;

        return $this;
    }

    /**
     * Sets the setting type as an array.
     *
     * @return $this
     */
    public function array(): static
    {
        $this->type = Metadata::TYPE_ARRAY;

        return $this;
    }

    /**
     * Sets the setting type as Datetime (Carbon).
     *
     * @return $this
     */
    public function datetime(): static
    {
        $this->type = Metadata::TYPE_DATETIME;

        return $this;
    }

    /**
     * Sets the setting type as 'collection'.
     *
     * @return $this
     */
    public function collection(): static
    {
        $this->type = Metadata::TYPE_COLLECTION;

        return $this;
    }

    /**
     * Sets the default value
     *
     * @param mixed $value
     *
     * @return $this
     */
    public function default(mixed $value): static
    {
        $this->default = $value;

        return $this;
    }

    /**
     * Sets the setting as disabled by default.
     *
     * @return $this
     */
    public function disabled(bool $enabled = false): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * Sets the group this setting belongs to.
     *
     * @param  string  $name
     *
     * @return $this
     */
    public function group(string $name): static
    {
        $this->group = $name;

        return $this;
    }

    /**
     * Sets the bag for declaration.
     *
     * @param  string  $name
     *
     * @return $this
     */
    public function bag(string $name): static
    {
        $this->bag = $name;

        return $this;
    }

    /**
     * Migrates the value from an old setting.
     *
     * @param  string  $oldSetting
     *
     * @return $this
     */
    public function from(string $oldSetting): static
    {
        $this->from = $oldSetting;

        return $this;
    }

    /**
     * Registers a callback to migrate the old value to the new one.
     *
     * @param  \Closure  $callback
     *
     * @return $this
     */
    public function using(Closure $callback): static
    {
        $this->using = $callback;

        return $this;
    }

    /**
     * Transforms the Declaration to a Metadata Model.
     *
     * @return \DarkGhostHunter\Laraconfig\Eloquent\Metadata
     */
    public function toMetadata(): Metadata
    {
        return (new Metadata)->forceFill([
            'name'          => $this->name,
            'type'          => $this->type,
            'default'       => $this->default,
            'bag'           => $this->bag,
            'group'         => $this->group,
            'is_enabled'    => $this->enabled
        ]);
    }
}
