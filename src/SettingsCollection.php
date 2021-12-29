<?php

namespace DarkGhostHunter\Laraconfig;

use DarkGhostHunter\Laraconfig\Eloquent\Setting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Traits\EnumeratesValues;
use RuntimeException;

/**
 * Class SettingsCollection
 *
 * @package DarkGhostHunter\Laraconfig
 *
 * @method Setting get(string $name, mixed $default = null)
 */
class SettingsCollection extends Collection
{
    use EnumeratesValues {
        __get as __dynamicGet;
    }
    /**
     * The cache helper instance.
     *
     * We will set it here since we need to keep an eye once this object instance
     * is garbage collected. Once done, a `__destruct()` call will be fired, and
     * that is when we will make the cache store regenerate the settings there.
     *
     * @var \DarkGhostHunter\Laraconfig\SettingsCache|null
     */
    public ?SettingsCache $cache = null;

    /**
     * If the settings should be regenerated on exit.
     *
     * @var bool
     */
    public bool $regeneratesOnExit = false;

    /**
     * Returns all the settings grouped by their group name.
     *
     * @return static|\DarkGhostHunter\Laraconfig\Eloquent\Setting[]
     */
    public function groups(): static
    {
        return $this->groupBy('group');
    }

    /**
     * Returns the value of a setting.
     *
     * @param  string  $name
     * @param  mixed|null  $default
     *
     * @return \Illuminate\Support\Carbon|\Illuminate\Support\Collection|array|string|int|float|bool|null
     */
    public function value(string $name, mixed $default = null): Carbon|Collection|array|string|int|float|bool|null
    {
        $setting = $this->get($name, $default);

        if ($setting instanceof Eloquent\Setting) {
            return $setting->value;
        }

        return $setting;
    }

    /**
     * Checks if the value of a setting is the same as the one issued.
     *
     * @param  string  $name
     * @param  mixed  $value
     *
     * @return bool
     */
    public function is(string $name, mixed $value): bool
    {
        return $this->value($name) === $value;
    }

    /**
     * Sets one or multiple setting values.
     *
     * @param  string|array  $name
     * @param  mixed  $value
     * @param  bool  $force
     *
     * @return void
     */
    public function set(string|array $name, mixed $value = null, bool $force = true): void
    {
        // If the name is not an array, we will make it one to iterate over.
        if (is_string($name)) {
            $name = [$name => $value];
        }

        foreach ($name as $key => $setting) {
            if (! $instance = $this->get($key)) {
                throw new RuntimeException("The setting [$key] doesn't exist.");
            }

            $instance->set($setting, $force);
        }
    }

    /**
     * Sets the default value of a given setting.
     *
     * @param  string  $name
     *
     * @return void
     */
    public function setDefault(string $name): void
    {
        $this->get($name)->setDefault();
    }

    /**
     * Checks if the setting is using a null value.
     *
     * @param  string  $name
     *
     * @return bool
     */
    public function isNull(string $name): bool
    {
        return null === $this->value($name);
    }

    /**
     * Checks if the Setting is enabled.
     *
     * @param  string  $name
     *
     * @return bool
     */
    public function isEnabled(string $name): bool
    {
        return $this->get($name)->is_enabled === true;
    }

    /**
     * Checks if the Setting is disabled.
     *
     * @param  string  $name
     *
     * @return bool
     */
    public function isDisabled(string $name): bool
    {
        return ! $this->isEnabled($name);
    }

    /**
     * Disables a Setting.
     *
     * @param  string  $name
     *
     * @return void
     */
    public function disable(string $name): void
    {
        $this->get($name)->disable();
    }

    /**
     * Enables a Setting.
     *
     * @param  string  $name
     *
     * @return void
     */
    public function enable(string $name): void
    {
        $this->get($name)->enable();
    }

    /**
     * Sets a value into a setting if it exists and it's enabled.
     *
     * @param  string|array  $name
     * @param  mixed  $value
     *
     * @return void
     */
    public function setIfEnabled(string|array $name, mixed $value = null): void
    {
        $this->set($name, $value, false);
    }

    /**
     * Returns only the models from the collection with the specified keys.
     *
     * @param  mixed  $keys
     * @return static
     */
    public function only($keys): static
    {
        if (is_null($keys)) {
            return new static($this->items);
        }

        if ($keys instanceof Enumerable) {
            $keys = $keys->all();
        }

        $keys = is_array($keys) ? $keys : func_get_args();

        $settings = new static(Arr::only($this->items, Arr::wrap($keys)));

        if ($settings->isNotEmpty()) {
            return $settings;
        }

        return parent::only($keys);
    }

    /**
     * Returns all models in the collection except the models with specified keys.
     *
     * @param  mixed  $keys
     * @return static
     */
    public function except($keys): static
    {
        if ($keys instanceof Enumerable) {
            $keys = $keys->all();
        } elseif (! is_array($keys)) {
            $keys = func_get_args();
        }

        $settings = new static(Arr::except($this->items, $keys));

        if ($settings->isNotEmpty()) {
            return $settings;
        }

        return parent::except($keys);
    }

    /**
     * Invalidates the cache of the setting's user.
     *
     * @return void
     */
    public function invalidate(): void
    {
        $this->cache?->invalidate();
    }

    /**
     * Invalidate the settings cache if it has not been done before.
     *
     * @return void
     */
    public function invalidateIfNotInvalidated(): void
    {
        $this->cache?->invalidateIfNotInvalidated();
    }

    /**
     * Saves the collection of settings in the cache.
     *
     * @param  bool  $force
     *
     * @return void
     */
    public function regenerate(bool $force = false): void
    {
        $this->cache?->regenerate($force);
    }

    /**
     * Handle the destruction of the settings collection.
     *
     * @return void
     */
    public function __destruct()
    {
        if ($this->regeneratesOnExit) {
            $this->cache?->setSettings($this)->regenerate();
        }
    }

    /**
     * Dynamically sets a value.
     *
     * @param  string  $name
     * @param  mixed $value
     */
    public function __set(string $name, mixed $value): void
    {
        $this->set($name, $value);
    }

    /**
     * Check if a given property exists.
     *
     * @param  string  $name
     *
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    /**
     * Dynamically access collection proxies.
     *
     * @param  string  $key
     * @return mixed
     *
     * @throws \Exception
     */
    public function __get($key): mixed
    {
        if ($setting = $this->get($key)) {
            return $setting->getAttribute('value');
        }

        return $this->__dynamicget($key);
    }
}
