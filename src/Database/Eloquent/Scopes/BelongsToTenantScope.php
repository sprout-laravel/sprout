<?php
declare(strict_types=1);

namespace Sprout\Database\Eloquent\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Sprout\Database\Eloquent\Contracts\OptionalTenant;
use Sprout\Database\Eloquent\TenantChildScope;
use Sprout\Exceptions\TenantMissing;

final class BelongsToTenantScope extends TenantChildScope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @template ModelClass of \Illuminate\Database\Eloquent\Model
     *
     * @param \Illuminate\Database\Eloquent\Builder<ModelClass>                                      $builder
     * @param \Illuminate\Database\Eloquent\Model&\Sprout\Database\Eloquent\Concerns\BelongsToTenant $model
     *
     * @phpstan-param ModelClass                                                                     $model
     *
     * @return void
     *
     * @throws \Sprout\Exceptions\TenantMissing
     */
    public function apply(Builder $builder, Model $model): void
    {
        /** @phpstan-ignore-next-line */
        $tenancy = $model->getTenancy();

        // If there's no current tenant
        if (! $tenancy->check()) {
            // We can exit early because the tenant is optional!
            if ($model instanceof OptionalTenant) {
                return;
            }

            // We should throw an exception because the tenant is missing
            throw TenantMissing::make($model::class, $tenancy->getName());
        }

        // Finally, add the clause so that all queries are scoped to the
        // current tenant
        $builder->where(
        /** @phpstan-ignore-next-line */
            $model->getTenantRelation()->getForeignKeyName(),
            '=',
            $tenancy->key()
        );
    }
}
