<?php
declare(strict_types=1);

namespace Sprout\Database\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Scope;

/**
 */
abstract class TenantChildScope implements Scope
{
    /**
     * Extend the query builder with the necessary macros
     *
     * @template ModelClass of \Illuminate\Database\Eloquent\Model
     *
     * @param \Illuminate\Database\Eloquent\Builder<ModelClass> $builder
     *
     * @return void
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withoutTenants', function (Builder $builder) {
            /** @phpstan-ignore-next-line */
            return $builder->withoutGlobalScope($this);
        });
    }
}
