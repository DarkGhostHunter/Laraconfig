<?php

namespace DarkGhostHunter\Laraconfig\Eloquent\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class WhereConfig implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     *
     * @return void
     */
    public function apply(Builder $builder, Model $model): void
    {
        //
    }

    /**
     * Extend the query builder with the needed functions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     *
     * @return void
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('whereConfig', [static::class, 'whereConfig']);
    }

    /**
     * Filters the user by the config value.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  string|array  $name
     * @param  mixed|null  $value
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function whereConfig(Builder $builder, string|array $name, mixed $value = null): Builder
    {
        if (is_array($name)) {
            foreach ($name as $key => $item) {
                static::whereConfig($builder, $key, $item);
            }

            return $builder;
        }

        return $builder->whereHas('settings', static function (Builder $builder) use ($name, $value): void {
            $builder
                ->withoutGlobalScope(AddMetadata::class)
                ->where(
                    static function (Builder $builder) use ($name, $value): void {
                        $builder
                            ->where('value', $value)
                            ->whereHas('metadata', static function (Builder $builder) use ($name): void {
                                $builder->where('name', $name);
                            });
            });
        });
    }
}
