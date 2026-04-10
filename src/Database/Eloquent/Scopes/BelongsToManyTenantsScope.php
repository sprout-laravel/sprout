<?php
declare(strict_types=1);

namespace Sprout\Core\Database\Eloquent\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Exceptions\TenantMissingException;
use function Sprout\Core\sprout;

/**
 * Belongs to many Tenants Scope
 *
 * This is a scope that is automatically attached to Eloquent models that relate
 * to tenants using a "belongs to many" relationship.
 *
 * It automatically adds the necessary clauses to queries to help avoid data
 * leaking between tenants in a "Shared Database, Shared Schema" setup.
 *
 * @see     \Sprout\Core\Database\Eloquent\Concerns\BelongsToManyTenants
 *
 * @package Database\Eloquent
 */
final class BelongsToManyTenantsScope extends TenantChildScope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @template ModelClass of \Illuminate\Database\Eloquent\Model
     *
     * @param \Illuminate\Database\Eloquent\Builder<ModelClass>                                           $builder
     * @param \Illuminate\Database\Eloquent\Model&\Sprout\Core\Database\Eloquent\Concerns\BelongsToTenant $model
     *
     * @phpstan-param ModelClass                                                                          $model
     *
     * @return void
     *
     * @throws \Sprout\Core\Exceptions\TenantMissingException
     * @throws \Sprout\Core\Exceptions\TenantRelationException
     */
    public function apply(Builder $builder, Model $model): void
    {
        /**
         * This has to be here otherwise the 'BelongsToTenant'
         * trait referenced in the below docblock causes PHPStan to error.
         * HILARIOUSLY, if I remove that trait from the docblock, PHPStan
         * will add a bunch more errors because a lot of the following code
         * relies on methods added by that trait.
         *
         * @phpstan-ignore-next-line
         */
        /**
         * This has to be here because it errors if it's in the method docblock,
         * though I've no idea why.
         *
         * @var ModelClass&\Sprout\Core\Database\Eloquent\Concerns\BelongsToTenant $model
         */
        if ($model::shouldIgnoreTenantRestrictions() || ! sprout()->withinContext()) {
            return;
        }

        /** @var \Sprout\Core\Contracts\Tenancy<*> $tenancy */
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
            /** @var string $relationName */
            $relationName = $model->getTenantRelationName();
            // If the tenant is optional, we wrap the clause with an OR for those
            // that have no tenant
            $builder->where(function (Builder $query) use ($relationName, $tenancy, $model) {
                $this->applyTenantClause($query, $model, $tenancy);
                $query->orDoesntHave($relationName);
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
     * @template ModelClass of \Illuminate\Database\Eloquent\Model
     *
     * @param \Illuminate\Database\Eloquent\Builder<ModelClass>                                           $builder
     * @param \Illuminate\Database\Eloquent\Model&\Sprout\Core\Database\Eloquent\Concerns\BelongsToTenant $model
     * @param \Sprout\Core\Contracts\Tenancy<*>                                                           $tenancy
     *
     * @phpstan-param ModelClass                                                                          $model
     *
     * @return void
     * @throws \Sprout\Core\Exceptions\TenantRelationException
     */
    protected function applyTenantClause(Builder $builder, Model $model, Tenancy $tenancy): void
    {
        /**
         * This has to be here because it errors if it's in the method docblock,
         * though I've no idea why.
         *
         * @var ModelClass&\Sprout\Core\Database\Eloquent\Concerns\BelongsToTenant $model
         */

        /** @var string $relationName */
        $relationName = $model->getTenantRelationName();

        $builder->whereHas(
            $relationName,
            function (Builder $builder) use ($tenancy) {
                $builder->whereKey($tenancy->key());
            },
        );
    }
}
