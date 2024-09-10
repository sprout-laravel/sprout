<?php
declare(strict_types=1);

namespace Sprout\Database\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class TenantChildScope implements Scope
{

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @template ModelClass of \Illuminate\Database\Eloquent\Model
     *
     * @param \Illuminate\Database\Eloquent\Builder<ModelClass> $builder
     * @param \Illuminate\Database\Eloquent\Model               $model
     *
     * @phpstan-param ModelClass                                $model
     *
     * @return void
     */
    public function apply(Builder $builder, Model $model): void
    {
        // TODO: Implement apply() method.
    }
}