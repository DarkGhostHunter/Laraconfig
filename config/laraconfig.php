<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Settings Bag
    |--------------------------------------------------------------------------
    |
    | Here you set the default Settings Bag to use across the application. If
    | you're using only one user, you will mostly need only the default bag.
    | You can declare additional Settings Bags to use programmatically.
    |
    */

    'default' => 'users',

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | For some apps, retrieving and updating the list of settings can be slow.
    | To avoid this, you can use a cache to store the settings. Laraset will
    | invalidate the settings cache of the user when a change is detected.
    |
    */

    'cache' => [
        'enable' => env('LARACONFIG_CACHE', false),
        'store' => env('LARACONFIG_STORE'),
        'duration' => 60 * 60 * 3, // Store the settings for 3 hours
        'prefix' => 'laraconfig',
        'automatic' => true, // Regenerate the cache before garbage collection.
    ]
];