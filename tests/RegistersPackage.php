<?php

namespace Tests;

use DarkGhostHunter\Laraconfig\LaraconfigServiceProvider;
use DarkGhostHunter\Laraconfig\Facades\Setting;

trait RegistersPackage
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     *
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaraconfigServiceProvider::class,
        ];
    }

    /**
     * Override application aliases.
     *
     * @param  \Illuminate\Foundation\Application  $app
     *
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [
            'Setting' => Setting::class,
        ];
    }
}