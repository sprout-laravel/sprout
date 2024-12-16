<?php
declare(strict_types=1);

namespace Sprout\Database\Eloquent\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
     * @template ModelClass of \Illuminate\Database\Eloquent\Model
     *
     * @param \Illuminate\Database\Eloquent\Builder<ModelClass>                                      $builder
     * @param \Illuminate\Database\Eloquent\Model&\Sprout\Database\Eloquent\Concerns\BelongsToTenant $model
     *
     * @phpstan-param Model                                                                          $model
     *
     * @return void
     *
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (! sprout()->withinContext()) {
            return;
        }

        /** @phpstan-ignore-next-line */
        $tenancy = $model->getTenancy();

        // If there's no current tenant
        if (! $tenancy->check()) {
            // We can exit early because the tenant is optional!
            if ($model::isTenantOptional()) { // @phpstan-ignore-line
                return;
            }

            // We should throw an exception because the tenant is missing
            throw TenantMissingException::make($tenancy->getName());
        }

        // Finally, add the clause so that all queries are scoped to the
        // current tenant
        $builder->whereHas(
        /** @phpstan-ignore-next-line */
            $model->getTenantRelationName(),
            function (Builder $builder) use ($tenancy) {
                $builder->whereKey($tenancy->key());
            }
        );
    }
}
