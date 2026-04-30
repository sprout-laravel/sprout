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
 * @see     BelongsToManyTenantsScope
 * @see     BelongsToTenantScope
 *
 * @template ModelClass of \Illuminate\Database\Eloquent\Model
 *
 * @implements Scope<ModelClass>
 */
abstract class TenantChildScope implements Scope
{
    /**
     * @var array<string, string>
     */
    protected array $extensions = [];

    /**
     * Extend the query builder with the necessary macros
     *
     * @param Builder<ModelClass> $builder
     *
     * @return void
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withoutTenants', function (Builder $builder) {
            /** @phpstan-ignore-next-line */
            return $builder->withoutGlobalScope($this);
        });

        foreach ($this->extensions as $macro => $method) {
            $builder->macro($macro, $this->$method(...)); // @codeCoverageIgnore
        }
    }
}
