<?php
declare(strict_types=1);

namespace Sprout\Database\Eloquent\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Exceptions\TenantMismatch;
use Sprout\Exceptions\TenantMissing;
use Sprout\TenancyOptions;

/**
 * @template ChildModel of \Illuminate\Database\Eloquent\Model
 * @template TenantModel of \Illuminate\Database\Eloquent\Model&\Sprout\Contracts\Tenant
 */
class BelongsToTenantObserver
{
    /**
     * Check if a model already has a tenant set
     *
     * @param \Illuminate\Database\Eloquent\Model                                        $model
     * @param \Illuminate\Database\Eloquent\Relations\BelongsTo<ChildModel, TenantModel> $relation
     *
     * @return bool
     */
    private function doesModelAlreadyHaveATenant(Model $model, BelongsTo $relation): bool
    {
        return $model->getAttribute($relation->getForeignKeyName()) !== null;
    }

    /**
     * Check if a model belongs to a different tenant
     *
     * @param \Illuminate\Database\Eloquent\Model                                        $model
     * @param \Illuminate\Database\Eloquent\Model&\Sprout\Contracts\Tenant               $tenant
     * @param \Illuminate\Database\Eloquent\Relations\BelongsTo<ChildModel, TenantModel> $relation
     *
     * @return bool
     */
    private function isTenantMismatched(Model $model, Tenant&Model $tenant, BelongsTo $relation): bool
    {
        return $model->getAttribute($relation->getForeignKeyName()) !== $tenant->getAttribute($relation->getOwnerKeyName());
    }

    /**
     * Perform initial checks and return they passed or not
     *
     * @param \Illuminate\Database\Eloquent\Model&\Sprout\Database\Eloquent\Concerns\BelongsToTenant $model
     * @param \Sprout\Contracts\Tenancy<TenantModel>                                                 $tenancy
     * @param \Illuminate\Database\Eloquent\Relations\BelongsTo<ChildModel, TenantModel>             $relation
     * @param bool                                                                                   $succeedOnMatch
     *
     * @return bool
     *
     * @phpstan-param ChildModel                                                                     $model
     *
     * @throws \Sprout\Exceptions\TenantMismatch
     * @throws \Sprout\Exceptions\TenantMissing
     */
    private function passesInitialChecks(Model $model, Tenancy $tenancy, BelongsTo $relation, bool $succeedOnMatch = false): bool
    {
        // If we don't have a current tenant, we may need to do something
        if (! $tenancy->check()) {
            // The model doesn't require a tenant, so we exit silently
            if ($model::isTenantOptional()) { // @phpstan-ignore-line
                // We return true so that the model can be created
                return false;
            }

            // If we hit here then there's no tenant, and the model isn't
            // marked as tenant being optional, so we throw an exception
            throw TenantMissing::make($tenancy->getName());
        }

        /**
         * @var \Illuminate\Database\Eloquent\Model&\Sprout\Contracts\Tenant $tenant
         * @phpstan-var TenantModel                                          $tenant
         */
        $tenant = $tenancy->tenant();

        // The model already has the tenant foreign key set
        if ($this->doesModelAlreadyHaveATenant($model, $relation)) {
            // You're probably expecting the following to use the
            // Tenant::getTenantKey() method, which would make sense, as that's
            // what it's for, but this should be more flexible
            if ($this->isTenantMismatched($model, $tenant, $relation)) {
                // So, the current foreign key value doesn't match the current
                // tenant, so we'll throw an exception...if we're allowed to
                if (TenancyOptions::shouldThrowIfNotRelated($tenancy)) {
                    throw TenantMismatch::make($model::class, $tenancy->getName());
                }

                // If we hit here, we should continue without doing anything
                // with the tenant
                return false;
            }

            // If we hit here, then the foreign key that's set is for the current
            // tenant, so, we can assume that either the relation is already
            // set in the model, or it doesn't need to be.
            // Either way, we're finished here
            return $succeedOnMatch;
        }

        return true;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model&\Sprout\Database\Eloquent\Concerns\BelongsToTenant $model
     *
     * @return bool
     *
     * @phpstan-param ChildModel                                                                     $model
     *
     * @throws \Sprout\Exceptions\TenantMissing
     * @throws \Sprout\Exceptions\TenantMismatch
     */
    public function creating(Model $model): bool
    {
        /**
         * @var \Illuminate\Database\Eloquent\Relations\BelongsTo<ChildModel, TenantModel> $relation
         * @phpstan-ignore-next-line
         */
        $relation = $model->getTenantRelation();

        /**
         * @var \Sprout\Contracts\Tenancy<TenantModel> $tenancy
         * @phpstan-ignore-next-line
         */
        $tenancy = $model->getTenancy();

        // If the initial checks do not pass
        if (! $this->passesInitialChecks($model, $tenancy, $relation)) {
            // Just exit, an exception will have be thrown
            return true;
        }

        // Associate the model and the tenant
        $relation->associate($tenancy->tenant());

        return true;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model&\Sprout\Database\Eloquent\Concerns\BelongsToTenant $model
     *
     * @return void
     *
     * @phpstan-param ChildModel                                                                     $model
     *
     * @throws \Sprout\Exceptions\TenantMissing
     * @throws \Sprout\Exceptions\TenantMismatch
     */
    public function retrieved(Model $model): void
    {
        /**
         * @var \Illuminate\Database\Eloquent\Relations\BelongsTo<ChildModel, TenantModel> $relation
         * @phpstan-ignore-next-line
         */
        $relation = $model->getTenantRelation();

        /**
         * @var \Sprout\Contracts\Tenancy<TenantModel> $tenancy
         * @phpstan-ignore-next-line
         */
        $tenancy = $model->getTenancy();

        // If the initial checks do not pass
        if (! $this->passesInitialChecks($model, $tenancy, $relation, true)) {
            // Just exit, an exception will have be thrown
            return;
        }

        if (! TenancyOptions::shouldHydrateTenantRelation($tenancy)) {
            return;
        }

        // Populate the relation with the tenant
        $model->setRelation($relation->getRelationName(), $tenancy->tenant());
    }
}
