<?php

namespace DarkGhostHunter\Laraconfig\Eloquent\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class FilterBags implements Scope
{
    /**
     * FilterBags constructor.
     *
     * @param  array  $bags
     */
    public function __construct(protected array $bags)
    {
    }

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
        $builder->whereHas('metadata', function (Builder $query): void {
            $query->whereIn('bag', $this->bags);
        });
    }
}
