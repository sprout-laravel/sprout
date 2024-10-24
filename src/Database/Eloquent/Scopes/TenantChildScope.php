<?php
declare(strict_types=1);

namespace Sprout\Database\Eloquent\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Scope;

/**
 * Tenant Child Scope
 *
 * A base scope utilised to provide a withoutTenants extension method to the
 * Eloquent query builder.
 *
 * @see     \Sprout\Database\Eloquent\Scopes\BelongsToManyTenantsScope
 * @see     \Sprout\Database\Eloquent\Scopes\BelongsToTenantScope
 *
 * @package Database\Eloquent
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
