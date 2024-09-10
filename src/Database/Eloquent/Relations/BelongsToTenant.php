<?php
declare(strict_types=1);

namespace Sprout\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Model;
use Sprout\Contracts\Tenancy;
use Sprout\Support\BaseTenantRelationHandler;

/**
 * @template ParentModel of \Illuminate\Database\Eloquent\Model
 * @template ChildModel of \Sprout\Contracts\Tenant&\Illuminate\Database\Eloquent\Model
 *
 * @extends \Sprout\Support\BaseTenantRelationHandler<ParentModel, ChildModel, \Illuminate\Database\Eloquent\Relations\BelongsTo>
 */
class BelongsToTenant extends BaseTenantRelationHandler
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
        return true;
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
    public function populateRelation(Model $model, Tenancy $tenancy): void
    {
        // If we don't have a tenant, or the relationship is already loaded,
        // we can skip this
        if (! $tenancy->check() || $model->relationLoaded($this->getRelationName())) {
            return;
        }

        /** @var \Illuminate\Database\Eloquent\Relations\BelongsTo<ChildModel, ParentModel> $relation */
        $relation = $this->getRelation($model);

        // If the foreign key is already populated, we can also skip this
        if ($model->getAttribute($relation->getForeignKeyName()) !== null) {
            return;
        }

        // Assuming there's no tenant already set, so we associate it
        $relation->associate($tenancy->tenant());
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
    public function hydrateRelation(Model $model, Tenancy $tenancy): void
    {
        // If we don't have a tenant, or the relationship is already loaded,
        // we can skip this
        if (! $tenancy->check() || $model->relationLoaded($this->getRelationName())) {
            return;
        }

        /** @var \Illuminate\Database\Eloquent\Relations\BelongsTo<ChildModel, ParentModel> $relation */
        $relation = $this->getRelation($model);

        /** @var ChildModel $tenant */
        $tenant = $tenancy->tenant();

        // If the foreign key is already populated, we can also skip this
        if ($model->getAttribute($relation->getForeignKeyName()) !== $tenant->getTenantKey()) {
            return;
        }

        $model->setRelation($this->getRelationName(), $tenant);
    }
}
