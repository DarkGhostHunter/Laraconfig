<?php

namespace DarkGhostHunter\Laraconfig\Facades;

use DarkGhostHunter\Laraconfig\Registrar\SettingRegistrar;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Support\Collection|\DarkGhostHunter\Laraconfig\Eloquent\Setting[] getSettings()
 * @method static \DarkGhostHunter\Laraconfig\Registrar\Declaration name(string $name)
 */
class Setting extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return SettingRegistrar::class;
    }
}