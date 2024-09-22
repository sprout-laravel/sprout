<?php
declare(strict_types=1);

namespace Sprout\Database\Eloquent\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Database\Eloquent\Contracts\OptionalTenant;
use Sprout\Exceptions\TenantMismatch;
use Sprout\Exceptions\TenantMissing;
use Sprout\TenancyOptions;

/**
 * @template ChildModel of \Illuminate\Database\Eloquent\Model
 * @template TenantModel of \Illuminate\Database\Eloquent\Model&\Sprout\Contracts\Tenant
 */
class BelongsToManyTenantsObserver
{
    /**
     * Check if a model already has a tenant set
     *
     * @param \Illuminate\Database\Eloquent\Model                                $model
     * @param \Illuminate\Database\Eloquent\Relations\BelongsToMany<TenantModel> $relation
     * @param \Sprout\Contracts\Tenancy<TenantModel>                             $tenancy
     *
     * @return bool
     */
    private function doesModelAlreadyHaveATenant(Model $model, BelongsToMany $relation, Tenancy $tenancy): bool
    {
        // If the tenancy is set to perform the check query, and the relation
        // isn't loaded
        if (
            /** @phpstan-ignore-next-line */
            TenancyOptions::shouldCheckForRelationWithTenant($tenancy)
            && ! $model->relationLoaded($relation->getRelationName())
        ) {
            // Load it
            $model->load($relation->getRelationName());
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, TenantModel>|null $relatedModels */
        $relatedModels = $model->getRelation($relation->getRelationName());

        if ($relatedModels === null) {
            return false;
        }

        // If it's not empty, there are already tenants
        return $relatedModels->isNotEmpty();
    }

    /**
     * Check if a model belongs to a different tenant
     *
     * @param \Illuminate\Database\Eloquent\Model                                $model
     * @param \Illuminate\Database\Eloquent\Model&\Sprout\Contracts\Tenant       $tenant
     * @param \Illuminate\Database\Eloquent\Relations\BelongsToMany<TenantModel> $relation
     *
     * @return bool
     */
    private function isTenantMismatched(Model $model, Tenant&Model $tenant, BelongsToMany $relation): bool
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, TenantModel>|null $relatedModels */
        $relatedModels = $model->getRelation($relation->getRelationName());

        // If the tenant model isn't in the loaded relation, or the relation is
        // null, there's a mismatch
        return $relatedModels?->first(function (Tenant&Model $model) use ($tenant) {
                return $model->is($tenant);
            }) === null;
    }

    /**
     * Check if a model is marked as having an optional tenant
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @phpstan-param Model                       $model
     *
     * @return bool
     */
    protected function isModelTenantOptional(Model $model): bool
    {
        return $model instanceof OptionalTenant;
    }

    /**
     * Perform initial checks and return they passed or not
     *
     * @param \Illuminate\Database\Eloquent\Model                                $model
     * @param \Sprout\Contracts\Tenancy<TenantModel>                             $tenancy
     * @param \Illuminate\Database\Eloquent\Relations\BelongsToMany<TenantModel> $relation
     *
     * @return bool
     *
     * @phpstan-param Model                                                      $model
     *
     * @throws \Sprout\Exceptions\TenantMismatch
     * @throws \Sprout\Exceptions\TenantMissing
     */
    private function passesInitialChecks(Model $model, Tenancy $tenancy, BelongsToMany $relation): bool
    {
        // If we don't have a current tenant, we may need to do something
        if (! $tenancy->check()) {
            // The model doesn't require a tenant, so we exit silently
            if ($this->isModelTenantOptional($model)) {
                // We return true so that the model can be created
                return false;
            }

            // If we hit here then there's no tenant, and the model isn't
            // marked as tenant being optional, so we throw an exception
            throw TenantMissing::make($model::class, $tenancy->getName());
        }

        /**
         * @var \Illuminate\Database\Eloquent\Model&\Sprout\Contracts\Tenant $tenant
         * @phpstan-var TenantModel                                          $tenant
         */
        $tenant = $tenancy->tenant();

        // The model already has the tenant foreign key set
        if ($this->doesModelAlreadyHaveATenant($model, $relation, $tenancy)) {
            // You're probably expecting the following to use the
            // Tenant::getTenantKey() method, which would make sense, as that's
            // what it's for, but this should be more flexible
            if ($this->isTenantMismatched($model, $tenant, $relation)) {
                // So, the current foreign key value doesn't match the current
                // tenant, so we'll throw an exception
                throw TenantMismatch::make($model::class, $tenancy->getName());
            }

            // If we hit here, then the foreign key that's set is for the current
            // tenant, so, we can assume that either the relation is already
            // set in the model, or it doesn't need to be.
            // Either way, we're finished here
            return false;
        }

        return true;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model&\Sprout\Database\Eloquent\Concerns\BelongsToTenant $model
     *
     * @return void
     *
     * @phpstan-param Model                                                                          $model
     *
     * @throws \Sprout\Exceptions\TenantMissing
     * @throws \Sprout\Exceptions\TenantMismatch
     */
    public function created(Model $model): void
    {
        /**
         * @var \Illuminate\Database\Eloquent\Relations\BelongsToMany<TenantModel> $relation
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
            return;
        }

        // Attach the tenant
        $relation->attach($tenancy->tenant());
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model&\Sprout\Database\Eloquent\Concerns\BelongsToTenant $model
     *
     * @return void
     *
     * @phpstan-param Model                                                                          $model
     *
     * @throws \Sprout\Exceptions\TenantMissing
     * @throws \Sprout\Exceptions\TenantMismatch
     */
    public function retrieved(Model $model): void
    {
        /**
         * @var \Illuminate\Database\Eloquent\Relations\BelongsToMany<TenantModel> $relation
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
            return;
        }

        /**
         * @var \Illuminate\Database\Eloquent\Model&\Sprout\Contracts\Tenant $tenant
         * @phpstan-var TenantModel                                          $tenant
         */
        $tenant = $tenancy->tenant();

        // Set the relation to contain the tenant
        $model->setRelation($relation->getRelationName(), $tenant->newCollection([$tenant]));
    }
}
