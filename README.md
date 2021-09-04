![Xavier von Erlach - Unsplash #ooR1jY2yFr4](https://images.unsplash.com/photo-1570221622224-3bb8f08f166c?ixlib=rb-1.2.1&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1200&h=400&q=80)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/darkghosthunter/laraconfig.svg)](https://packagist.org/packages/darkghosthunter/laraconfig) [![License](https://poser.pugx.org/darkghosthunter/laraconfig/license)](https://packagist.org/packages/darkghosthunter/laraconfig) ![](https://img.shields.io/packagist/php-v/darkghosthunter/laraconfig.svg) ![](https://github.com/DarkGhostHunter/Laraconfig/workflows/PHP%20Composer/badge.svg) [![Coverage Status](https://coveralls.io/repos/github/DarkGhostHunter/Laraconfig/badge.svg?branch=master)](https://coveralls.io/github/DarkGhostHunter/Laraconfig?branch=master) [![Laravel Octane Compatible](https://img.shields.io/badge/Laravel%20Octane-Compatible-success?style=flat&logo=laravel)](https://github.com/laravel/octane)

# Laraconfig

Per-user settings repository system for Laravel.

This package allows users to have settings that can be queried, changed and even updated, effortlessly and quickly.

```php
User::find(1)->settings->set('color', 'red');
```

## Requirements

- Laravel 8.x
- PHP 8.0 or later

## How it works

Laraconfig works extending Laravel relations, and includes a migration system to easily manage them. 

Each Setting is just a value, and references a parent "metadata" that contains the information like the type and name, while being linked to a user.

Since Laraconfig uses the Eloquent ORM behind the scenes, getting a one or all settings is totally transparent to the developer.

## Quickstart

You can install the package via composer.

    composer require darkghosthunter/laraconfig

First, publish and run the migrations. These will add two tables called `user_settings` and `user_settings_metadata`. One holds the values per user, the other the metadata of the setting, respectively.

    php artisan vendor:publish --provider="DarkGhostHunter\Laraconfig\LaraconfigServiceProvider" --tag="migrations"
    php artisan migrate

> The migration uses a morph column to connect to the User. You can change it before migrating.

Second, add the `HasConfig` trait to the User models you want to have settings.

```php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use DarkGhostHunter\Laraconfig\HasConfig;

class User extends Authenticatable
{
    use HasConfig;
    
    // ...
}
```

Finally, use the `settings:publish` artisan command. This will create a `settings` folder in the root of your project and a `users.php` file.

    php artisan settings:publish

Now, let's create some settings.

## Settings Manifest

Laraconfig makes managing user settings globally using a _manifest_ of sorts, the `settings/users.php` file. You will see a sample setting already written.

```php
use DarkGhostHunter\Laraconfig\Facades\Setting;

Setting::name('color')->string();
```

### Creating a setting

To create a setting, use the `Setting` facade. You can start with setting the name, which must be unique, and then declare the type.

```php
use DarkGhostHunter\Laraconfig\Facades\Setting;

Setting::name('dark_mode')->boolean();
```

Laraconfig is compatible with 7 types of settings, mirroring their PHP native types, along the Collection and Datetime (Carbon) objects. 

* `array()`
* `boolean()`
* `collection()`
* `datetime()`
* `float()`
* `integer()`
* `string()`

> Arrays and Collections are serialized in the database as JSON. 

### Default value

All settings have a default value of `null`, but you can use the `default()` method to set a different initial value.

```php
use DarkGhostHunter\Laraconfig\Facades\Setting;

Setting::name('color')->string()->default('black');
```

> You can later revert the value back to the default using [`setDefault()`](#defaulting-a-setting).

### Enabled or Disabled

By default, all settings are [enabled by default](#disablingenabling-settings), but you can change this using `disabled()`.

```php
Setting::name('color')->disabled();
```

> Enabled or disable is presentational; a disabled setting can still be updated. You can programmatically set a value using [`setIfEnabled()`](#disablingenabling-settings).

### Group settings

You can set a group name to a setting. This can be handy when you want to display settings in the frontend in an ordered manner by [separating them in groups](https://laravel.com/docs/collections#method-groupby).

```php
Setting::name('color')->group('theme');
```

### Bag

When Laraconfig migrates the new settings, these are created to all models. You can filter a given set of settings through "bags". 

By default, all settings are created under the `users` bag, but you can change the default bag for anything using the `bag()` method.

```php
Setting::name('color')->group('theme')->bag('style');

Setting::name('notify_email')->boolean()->default(true)->bag('notifications');
Setting::name('notify_sms')->boolean()->default(false)->bag('notifications');
```

Later, in your model, you can filter the bags you want to work with using [`filterBags()`](#setting-bags) in your model.

## Migrating settings

Once you're done creating your settings, you should use `settings:migrate` to let Laraconfig add the settings metadata to your database.

    php artisan settings:migrate

Behind the scenes, Laraconfig will look into your Models for those using the `HasConfig` trait, and populate the settings accordingly using the information on the manifest.

> Migration run only _forward_. There is no way to revert a migration once done. On production, removing settings needs confirmation.

### Adding new settings

Simply create a new setting and run `settings:migrate`. Existing settings won't be created again, as Laraconfig will check their existence before doing it.

```php
use DarkGhostHunter\Laraconfig\Facades\Setting;

Setting::name('color')->string()->default('black');

// This new setting will be created 
Setting::name('notifications')->boolean()->default(true);
```

### Removing old settings

To remove old settings, simply remove their declaration and run `settings:migrate`. Laraconfig compares the settings declared to the ones created in the database, and removes those that no longer exist in the manifest at the end of the migration execution.

```php
use DarkGhostHunter\Laraconfig\Facades\Setting;

// Commenting this line will remove the "color" setting on migration.
// Setting::name('color')->string()->default('black');

// This new setting will be created 
Setting::name('notifications')->boolean()->default(true);
```

> Since this procedure can be dangerous, **confirmation** will be needed on production environments.

### Upgrading settings

You don't need to get directly into the database to update a setting. Instead, just change the setting properties directly in the manifest. Laraconfig will update the metadata accordingly.

Let's say we have a "color" setting we wish to update from a string to an array of colors, with a default and a group.

```php
Setting::name('color')->string()->bag('theme');

// This is the new declaration.
// Setting::name('color')
//    ->array()
//    ->default(['black'])
//    ->group('theme');
```

Laraconfig will detect the new changes, and update the metadata keeping the users value intact.

```php
// This is the old declaration.
// Setting::name('color')->string()->bag('theme');

Setting::name('color')
    ->array()
    ->default(['black'])
    ->group('theme');
```

> Updating only occurs if the setting is different from before at migration time.

Once done, we can migrate the old setting to the new one using `settings:migrate`. Users will keep the same setting value they had, but... What if we want to also change the value for each user? We can use the `using()` method to feed each user setting to a callback that will return the new value.

```php
Setting::name('color')
    ->array()
    ->default('black')
    ->group('theme')
    ->using(fn ($old) => $old->value ?? 'black'); // If the value is null, set it as "black".
```

> The `using()` method only runs if the setting is different from before at migration time.

Behind the scenes, Laraconfig will look for the "color" setting, update the metadata, and then use a [`lazy()` query](https://laravel.com/docs/queries#streaming-results-lazily) to update the value with the callback.

> Consider migrating directly on the database if you have hundreds of thousands of records, as this procedure is safer but slower than a direct SQL statement.

### Migrating to a new setting

On other occasions, you may want to migrate a setting to a completely new one. In both cases you can use `from()` to get the old setting value to migrate from, and `using()` if you want to also update the value of each user.

Taking the same example above, we will migrate the "color" setting to a simple "dark theme" setting.

```php
// This old declaration will be deleted after the migration ends.
// Setting::name('color')->string()->bag('theme');

// This is a new setting.
Setting::name('dark')
    ->boolean()
    ->default(false)
    ->group('theme')
    ->from('color')
    ->using(static fn ($old) => $old->value === 'black'); // If it's black, then it's dark.
```

> The `from` and `using` are executed only if the old setting exists at migration time.

Behind the scenes, Laraconfig creates the new "theme" setting first, and then looks for the old "color" setting in the database to translate the old values to the new ones. Since the old setting is not present in the manifest, it will be deleted from the database.

## Managing Settings

Laraconfig handles settings like any [Eloquent Morph-Many Relationship](https://laravel.com/docs/eloquent-relationships#one-to-many-polymorphic-relations), but supercharged. 

Just simply use the `settings` property on your model. This property is like your normal [Eloquent Collection](https://laravel.com/docs/eloquent-collections), so you have access to all its tools.

```php
$user = User::find(1);

echo "Your color is: {$user->settings->get('color')}.";
```

> Using `settings` is preferred, as it will load the settings only once.

### Initializing

By default, the `HasConfig` trait will create a new bag of Settings in the database after a User is successfully created through the Eloquent ORM, so you don't have to create any setting.

In case you want to handle initialization manually, you can use the `shouldInitializeConfig()` method and return `false`, which can be useful when programmatically initializing the settings.

```php
// app/Models/User.php

/**
 * Check if the user should initialize settings automatically after creation.
 * 
 * @return bool
 */
protected function shouldInitializeConfig(): bool
{
    // Don't initialize the settings if the user is not verified from the start.
    // We will initialize them only once the email is properly verified.
    return null !== $this->email_verified_at;
}
```

Since the user in the example above won't be initialized, we have to do it manually using `initialize()`.

```php
// Initialize if not initialized before.
$user->config()->initialize();

// Forcefully initialize, even if already initialized.
$user->config()->initialize(true);
```

#### Checking settings initialization

You can check if a user configuration has been initialized or not using `isInitialized()`.

```php
if ($user->config()->isInitialized()) {
    return 'You have a config!';
}
```

### Retrieving settings

You can easily get a value of a setting using the name, which makes everything into a single beautiful _oneliner_.

```php
return "Your favorite color is {$user->settings->color}";
```

Since this only supports alphanumeric and underscore characters, you can use `value()`.

```php
return "Your favorite color is {$user->settings->value('color')}";
```

You can also get the underlying Setting model using `get()`. If the setting doesn't exist, it will return `null`.

```php
$setting = $user->settings->get('theme');

echo "You're using the [$setting->value] theme.";
```

Since the `settings` is a [collection](https://laravel.com/docs/eloquent-collections), you have access to all the goodies, like iteration:

```php
foreach ($user->settings as $setting) {
    echo "The [$setting->name] has the [$setting->value] value.";
}
```

You can also use the `only()` method to return a collection of settings by their name, or `except()` to retrieve all the settings except those issued.

```php
$user->settings->only('colors', 'is_dark');

$user->settings->except('dark_mode');
```

#### Grouping settings

Since the list of settings is a collection, you can use `groups()` method to group them by the name of the group they belong.

```php
$user->settings->groups(); // or ->groupBy('group')
```

> Note that Settings are grouped into the `default` group by default (no pun intended).

### Setting a value

Setting a value can be easily done by issuing the name of the setting and the value.

```php
$user->settings->color = 'red';
```

Since this only supports settings with names made of alphanumeric and underscores, you can also set a value using the `set()` method by issuing the name of the setting.

```php
$user->settings->set('color-default', 'red');
```

Or, you can go the purist mode directly in the model itself.

```php
$setting = $user->settings->get('color');

$setting->value = 'red';
$setting->save();
```

You can also set multiple settings using an array when using `set()` in one go, which is useful when dealing with the [array returned by a validation](https://laravel.com/docs/validation#quick-writing-the-validation-logic).

```php
$user->settings->set([
    'color' => 'red',
    'dark_mode' => false,
]);
```

When [using the cache](#cache), any change invalidates the cache immediately and queues up a regeneration before the collection is garbage collected.

> That being said, updating the settings directly into the database **doesn't regenerate the cache**.

### Defaulting a Setting

You can turn the setting back to the default value using `setDefault()` on both the setting instance or using the `settings` property. 

```php
$setting = $user->settings->get('color');

$setting->setDefault();

$user->settings->setDefault('color');
```

> If the setting has no default value, `null` will be used.

### Check if null

Check if a `null` value is set using `isNull()` with the name of the setting.

```php
if ($user->settings->isNull('color')) {
    return 'The color setting is not set.';
}
```

### Disabling/Enabling settings

For presentational purposes, all settings are enabled by default. You can enable or disable settings with the `enable()` and `disable()`, respectively. To check if the setting is enabled, use the `isEnabled()` method.

```php
$user->settings->enable('color');

$user->settings->disable('color');
```

> A disabled setting can be still set. If you want to set a value only if it's enabled, use `setIfEnabled()`.
> 
> ```php
> $user->settings->setIfEnabled('color', 'red');
> ```

## Setting Bags

Laraconfig uses one single bag called `default`. If you have declared in the manifest [different sets of bags](#bag), you can make a model to use only a particular set of bags with the `filterBags()` method, that should return the bag name (or names).

```php
// app/Models/User.php
i
```

The above will apply a filter to the query when retrieving settings from the database. This makes easy to swap bags when a user has a different role or property, or programmatically.

> **All** settings are created for all models with `HasConfig` trait, regardless of the bags used by the model. 

#### Disabling the bag filter scope

Laraconfig applies a query filter to exclude the settings not in the model bag. While this eases the development, sometimes you will want to work with the full set of settings available.

There are two ways to disable the bag filter. The first one is relatively easy: simply use the `withoutGlobalScope()` at query time, which will allow to query all the settings available to the user.

```php
use DarkGhostHunter\Laraconfig\Eloquent\Scopes\FilterBags;

$allSettings = $user->settings()->withoutGlobalScope(FilterBags::class)->get();
```

If you want a more _permanent_ solution, just simply return an empty array or `null` when using the `filterBags()` method in you model, which will disable the scope completely.

```php
/**
 * Returns the bags this model uses for settings.
 *
 * @return array|string
 */
public function filterBags(): array|string|null
{
    return null;
}
```

## Cache

Hitting the database each request to retrieve the user settings can be detrimental if you expect to happen a lot. To avoid this, you can activate a cache which will be regenerated each time a setting changes.

The cache implementation avoids data-races. It will regenerate the cache only for the last data changed, so if two or more processes try to save something into the cache, only the fresher data will be persisted.

### Enabling the cache

You can easily enable the cache using the `LARACONFIG_CACHE` environment variable set to `true`, and use a non-default cache store (like Redis) with `LARACONFIG_STORE`.

```dotenv
LARACONFIG_CACHE=true
LARACONFIG_STORE=redis
```

> Alternatively, check the `laraconfig.php` file to customize the cache TTL and prefix.

#### Managing the cache

You can forcefully regenerate the cache of a single user using `regenerate()`. This basically saves the settings present  and saves them into the cache.

```php
$user->settings->regenerate();
```

You can also invalidate the cached settings using `invalidate()`, which just deletes the entry from the cache.

```php
$user->settings->invalidate();
```

Finally, you can have a little peace of mind by setting `regeneratesOnExit` to `true`, which will regenerate the cache when the settings are garbage collected by the PHP process.

```php
$user->settings->regeneratesOnExit = true;
```

> You can disable automatic regeneration on the config file.

#### Regenerating the Cache on migration

If the [Cache is activated](#cache), the migration will invalidate the setting cache for each user after it completes.

Depending on the Cache system, forgetting each cache key can be detrimental. Instead, you can use the `--flush-cache` command to flush the cache store used by Laraconfig, instead of deleting each key one by one.

    php artisan settings:migrate --flush-cache

> Since this will delete all the data of the cache, is recommended to use an exclusive cache store for Laraconfig, like a separate Redis database.

## Validation

Settings values are _casted_, but not validated. You should validate in your app every value that you plan to store in a setting.

```php
use App\Models\User;
use Illuminate\Http\Request;

public function store(Request $request, User $user)
{
    $settings = $request->validate([
        'age' => 'required|numeric|min:14|max:100',
        'color' => 'required|string|in:red,green,blue'
    ]);
    
    $user->settings->setIfEnabled($settings);
    
    // ...
}
```

## Testing

Eventually you will land into the problem of creating settings and metadata for each user created. You can easily create Metadata directly into the database _before_ creating a user, unless you have disabled [initialization](#initializing).

```php
public function test_user_has_settings(): void
{
    Metadata::forceCreate([
        'name'    => 'foo',
        'type'    => 'string',
        'default' => 'bar',
        'bag'     => 'users',
        'group'   => 'default',
    ]);
    
    $user = User::create([
        // ...
    ]);
        
    // ...
}
```

## Security

If you discover any security related issues, please email darkghosthunter@gmail.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
