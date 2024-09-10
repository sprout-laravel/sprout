<?php
declare(strict_types=1);

namespace Sprout\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as ParentModel;
use RuntimeException;
use Sprout\Contracts\Tenancy;
use Sprout\Support\BaseTenantRelationHandler;
use Sprout\TenancyOptions;

/**
 * @template ParentModel of \Illuminate\Database\Eloquent\Model
 * @template ChildModel of \Illuminate\Database\Eloquent\Model&\Sprout\Contracts\Tenant
 *
 * @extends \Sprout\Support\BaseTenantRelationHandler<ParentModel, ChildModel, \Illuminate\Database\Eloquent\Relations\HasMany>
 */
class HasManyTenants extends BaseTenantRelationHandler
{
    /**
     * Whether the relation populates before it's saved
     *
     * This method returns true if the relationship requires population
     * before the model is persisted (creating event), or false if after
     * (created event).
     *
     * @return bool
     */
    public function populateBeforePersisting(): bool
    {
        return false;
    }

    /**
     * Populate the relationship to the tenant
     *
     * This method populates the tenant relationship with the current tenant,
     * automatically associating the parent model and the tenant.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Illuminate\Database\Eloquent\Model    $model
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     *
     * @return void
     *
     * @phpstan-param ParentModel                    $model
     */
    public function populateRelation(ParentModel $model, Tenancy $tenancy): void
    {
        // If we don't have a tenant, or the relationship is already loaded,
        // we can skip this
        if (! $tenancy->check() || $model->relationLoaded($this->getRelationName())) {
            return;
        }

        /** @var \Illuminate\Database\Eloquent\Relations\HasMany<ChildModel> $relation */
        $relation = $this->getRelation($model);

        /**
         * @var \Sprout\Contracts\Tenant $tenant
         * @phpstan-var ChildModel       $tenant
         */
        $tenant = $tenancy->tenant();

        // The assumption is that this method is only ever called on 'created',
        // so it's safe to assume there are no other tenants
        $relation->save($tenant);
    }

    /**
     * Hydrate the tenant relationship
     *
     * This method sets the relation on the parent model to be the current
     * tenant if it belongs to it.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Illuminate\Database\Eloquent\Model    $model
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     *
     * @return void
     *
     * @phpstan-param ParentModel                    $model
     */
    public function hydrateRelation(ParentModel $model, Tenancy $tenancy): void
    {
        // If we don't have a tenant, or the relationship is already loaded,
        // we can skip this
        if (! $tenancy->check()) {
            return;
        }

        /**
         * @var \Sprout\Contracts\Tenant $tenant
         * @psalm-var ChildModel         $tenant
         */
        $tenant = $tenancy->tenant();

        if ($model->relationLoaded($this->getRelationName())) {
            /** @var \Illuminate\Database\Eloquent\Collection<int, ChildModel>|null $models */
            $models = $model->getRelation($this->getRelationName());

            if ($models !== null && $models->contains(function (Model $model) use ($tenant) {
                    return $model->is($tenant);
                })) {
                return;
            }
        }

        // If the tenancy is configured to do a hydration check, we'll need to run
        // a query to see if this model IS related to the tenant
        if (TenancyOptions::shouldCheckForRelationWithTenant($tenancy)) {
            // Run the query
            $exists = $model->newQuery()->whereHas($this->getRelationName(), function (Builder $query) use ($tenant) {
                $query->whereKey($tenant->getTenantKey());
            })->exists();

            // The model isn't related to the tenant
            if (! $exists) {
                // If the hydration is set to be strict for the current tenancy,
                // we'll need an exception
                if (TenancyOptions::shouldThrowIfNotRelated($tenancy)) {
                    // TODO: Abstract out to specific exception
                    throw new RuntimeException(
                        'Child model [' . $model::class . '::' . $model->getKey()
                        . '] is not related to the tenant [' . $tenant->getTenantKey()
                        . '] for tenancy [' . $tenancy->getName() . ']'
                    );
                }

                return;
            }
        }

        // If we're hitting here we're either not doing a check, or the check
        // succeeded, so we'll set the relation
        $model->setRelation(
            $this->getRelationName(),
            // Make sure to create a proper collection, just in case there's
            // a special one
            ($models ?? $tenant->newCollection())->add($tenant)
        );
    }
}
