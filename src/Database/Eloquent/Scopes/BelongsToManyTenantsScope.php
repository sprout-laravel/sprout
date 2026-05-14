<?php
declare(strict_types=1);

namespace Sprout\Database\Eloquent\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Sprout\Contracts\Tenancy;
use Sprout\Exceptions\TenantMissingException;
use function Sprout\sprout;

/**
 * Belongs to many Tenants Scope
 *
 * This is a scope that is automatically attached to Eloquent models that relate
 * to tenants using a "belongs to many" relationship.
 *
 * It automatically adds the necessary clauses to queries to help avoid data
 * leaking between tenants in a "Shared Database, Shared Schema" setup.
 *
 * @see     \Sprout\Database\Eloquent\Concerns\BelongsToManyTenants
 *
 * @package Database\Eloquent
 */
final class BelongsToManyTenantsScope extends TenantChildScope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder<covariant \Illuminate\Database\Eloquent\Model> $builder
     * @param \Illuminate\Database\Eloquent\Model&\Sprout\Database\Eloquent\Concerns\IsTenantChild $model
     *
     * @return void
     *
     * @throws \Sprout\Exceptions\TenantMissingException
     * @throws \Sprout\Exceptions\TenantRelationException
     */
    public function apply(Builder $builder, Model $model): void
    {
        if ($model::shouldIgnoreTenantRestrictions() || ! sprout()->withinContext()) {
            return;
        }

        $tenancy = $model->getTenancy();

        // If there's no current tenant
        if (! $tenancy->check()) {
            // We can exit early because the tenant is optional!
            if ($model::isTenantOptional()) {
                return;
            }

            // We should throw an exception because the tenant is missing
            throw TenantMissingException::make($tenancy->getName());
        }

        // Finally, add the clause so that all queries are scoped to the
        // current tenant.
        if ($model::isTenantOptional()) {
            // If the tenant is optional, we wrap the clause with an OR for those
            // that have no tenant
            $builder->where(function (Builder $query) use ($tenancy, $model) {
                $this->applyTenantClause($query, $model, $tenancy);
                $query->orDoesntHave($model->getTenantRelationName());
            });
        } else {
            // And if not, we just add the clause
            $this->applyTenantClause($builder, $model, $tenancy);
        }
    }

    /**
     * Add the actual tenant clause to the query
     *
     * This is abstracted out to avoid duplication in the above apply method.
     *
     * @param \Illuminate\Database\Eloquent\Builder<covariant \Illuminate\Database\Eloquent\Model> $builder
     * @param \Illuminate\Database\Eloquent\Model&\Sprout\Database\Eloquent\Concerns\IsTenantChild $model
     * @param \Sprout\Contracts\Tenancy<*>                                                         $tenancy
     *
     * @return void
     * @throws \Sprout\Exceptions\TenantRelationException
     */
    protected function applyTenantClause(Builder $builder, Model $model, Tenancy $tenancy): void
    {
        $builder->whereHas(
            $model->getTenantRelationName(),
            function (Builder $builder) use ($tenancy) {
                $builder->whereKey($tenancy->key());
            },
        );
    }
}
