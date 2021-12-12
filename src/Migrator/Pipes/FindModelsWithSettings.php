<?php

namespace DarkGhostHunter\Laraconfig\Migrator\Pipes;

use Closure;
use DarkGhostHunter\Laraconfig\HasConfig;
use DarkGhostHunter\Laraconfig\Migrator\Data;
use Generator;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

/**
 * @internal
 */
class FindModelsWithSettings
{
    /**
     * FindModelsWithSettings constructor.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @param  \Illuminate\Filesystem\Filesystem  $filesystem
     *
     * @return void
     */
    public function __construct(protected Application $app, protected Filesystem $filesystem)
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
        // If the developer has already issued a collection of models, we will
        // use that list. Otherwise, we will find them manually.
        if ($data->models->isEmpty()) {
            $data->models = new Collection(iterator_to_array($this->findModelsWithSettings()));
        }

        // If we find two or more models using the SAME table, we will bail out.
        // If we continue we will create settings for the same models twice or
        // more, which will hinder performance and may introduce bugs later.
        $duplicated = $data->models->map->getTable()->duplicates();

        if ($duplicated->isNotEmpty()) {
            throw new RuntimeException("{$duplicated->count()} models are using the same tables: {$duplicated->implode(', ')}.");
        }

        return $next($data);
    }

    /**
     * Finds all models from the project.
     *
     * @return \Generator
     */
    protected function findModelsWithSettings(): Generator
    {
        $namespace = $this->app->getNamespace();

        if ($this->filesystem->exists($this->app->path('Models'))) {
            $files = $this->filesystem->allFiles($this->app->path('Models'));
        }

        // If the developer is not using the "Models", we will try the root.
        if (empty($files)) {
            $files = $this->filesystem->files($this->app->path());
        }

        foreach ($files as $file) {
            $className = (string) Str::of($file->getPathname())
                ->after($this->app->basePath())
                ->trim('\\')
                ->trim('/')
                ->ltrim('app\\')
                ->rtrim('.php')
                ->replace(DIRECTORY_SEPARATOR, '\\')
                ->ltrim('\\')
                ->start('\\'.$namespace);

            try {
                $reflection = new ReflectionClass($className);
            } catch (ReflectionException) {
                continue;
            }

            // Should be part of the Eloquent ORM Model class.
            if (! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            // We will exclude all models that are not instantiable, like abstracts,
            // as the developer may be using an abstract "User" class, extended by
            // other classes like "Admin", "Moderator", etc, avoiding duplicates.
            if (! $reflection->isInstantiable()) {
                continue;
            }

            // Should have the HasConfig trait, or have a trait that uses it.
            if (! in_array(HasConfig::class, trait_uses_recursive($className), true)) {
                continue;
            }

            yield new $className;
        }
    }
}
