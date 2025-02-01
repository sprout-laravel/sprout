<?php
declare(strict_types=1);

namespace Sprout\Database\Eloquent\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Exceptions\TenantMismatchException;
use Sprout\Exceptions\TenantMissingException;
use Sprout\TenancyOptions;
use function Sprout\sprout;

/**
 * Belongs to Many Tenants Observer
 *
 * This is an observer automatically attached to Eloquent models that relate to
 * tenants using a "belongs to many" relationship, to automate association
 * and hydration of the tenant relation.
 *
 * @template ChildModel of \Illuminate\Database\Eloquent\Model
 * @template TenantModel of \Illuminate\Database\Eloquent\Model&\Sprout\Contracts\Tenant
 *
 * @see     \Sprout\Database\Eloquent\Concerns\BelongsToManyTenants
 *
 * @package Database\Eloquent
 */
class BelongsToManyTenantsObserver
{
    private bool $loadedRelation = false;

    /**
     * Check if a model already has a tenant set
     *
     * @param \Illuminate\Database\Eloquent\Model                                            $model
     * @param \Illuminate\Database\Eloquent\Relations\BelongsToMany<ChildModel, TenantModel> $relation
     * @param \Sprout\Contracts\Tenancy<TenantModel>                                         $tenancy
     *
     * @return bool
     */
    private function doesModelAlreadyHaveATenant(Model $model, BelongsToMany $relation, Tenancy $tenancy): bool
    {
        // If the relation isn't loaded
        if (! $model->relationLoaded($relation->getRelationName())) {
            // Load it
            $model->load($relation->getRelationName());
            $this->loadedRelation = true;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, TenantModel> $relatedModels */
        $relatedModels = $model->getRelation($relation->getRelationName());

        // If it's not empty, there are already tenants
        return $relatedModels->isNotEmpty();
    }

    /**
     * Check if a model belongs to a different tenant
     *
     * @param \Illuminate\Database\Eloquent\Model                                            $model
     * @param \Illuminate\Database\Eloquent\Model&\Sprout\Contracts\Tenant                   $tenant
     * @param \Illuminate\Database\Eloquent\Relations\BelongsToMany<ChildModel, TenantModel> $relation
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
     * Perform initial checks and return they passed or not
     *
     * @param \Illuminate\Database\Eloquent\Model&\Sprout\Database\Eloquent\Concerns\BelongsToManyTenants $model
     * @param \Sprout\Contracts\Tenancy<TenantModel>                                                      $tenancy
     * @param \Illuminate\Database\Eloquent\Relations\BelongsToMany<ChildModel, TenantModel>              $relation
     * @param bool                                                                                        $succeedOnMatch
     *
     * @return bool
     *
     * @phpstan-param ChildModel                                                                          $model
     *
     * @throws \Sprout\Exceptions\TenantMismatchException
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    private function passesInitialChecks(Model $model, Tenancy $tenancy, BelongsToMany $relation, bool $succeedOnMatch = false): bool
    {
        /**
         * If the model has opted to ignore tenant restrictions, we can exit early
         * and return true.
         *
         * @phpstan-ignore-next-line
         */
        if ($model::shouldIgnoreTenantRestrictions()) {
            return true;
        }

        // If we don't have a current tenant, we may need to do something
        if (! $tenancy->check()) {
            // The model doesn't require a tenant, so we exit silently
            if ($model::isTenantOptional()) { // @phpstan-ignore-line
                // We return true so that the model can be created
                return false;
            }

            // If we hit here then there's no tenant, and the model isn't
            // marked as tenant being optional, so we throw an exception
            throw TenantMissingException::make($tenancy->getName());
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
                // tenant, so we'll throw an exception...if we're allowed to
                if (TenancyOptions::shouldThrowIfNotRelated($tenancy)) {
                    throw TenantMismatchException::make($model::class, $tenancy->getName());
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
     * Handle the created event on the model
     *
     * The created event is fired after a model is persisted to the database.
     *
     * @param \Illuminate\Database\Eloquent\Model&\Sprout\Database\Eloquent\Concerns\BelongsToManyTenants $model
     *
     * @return void
     *
     * @phpstan-param ChildModel                                                                          $model
     *
     * @throws \Sprout\Exceptions\TenantMissingException
     * @throws \Sprout\Exceptions\TenantMismatchException
     */
    public function created(Model $model): void
    {
        if (! sprout()->withinContext()) {
            return;
        }

        /**
         * @var \Illuminate\Database\Eloquent\Relations\BelongsToMany<ChildModel, TenantModel> $relation
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
            // Just exit, an exception will have been thrown
            return;
        }

        if ($this->loadedRelation) {
            $model->unsetRelation($relation->getRelationName());
            $this->loadedRelation = false;
        }

        /**
         * @var \Illuminate\Database\Eloquent\Model&\Sprout\Contracts\Tenant $tenant
         * @phpstan-var TenantModel                                          $tenant
         */
        $tenant = $tenancy->tenant();

        // Attach the tenant
        $relation->attach($tenant);

        if (! TenancyOptions::shouldHydrateTenantRelation($tenancy)) {
            return;
        }

        // Set the relation to contain the tenant
        $this->setRelation($model, $relation, $tenant);
    }

    /**
     * Handle the retrieved event on the model
     *
     * The retrieved event is fired after a model is retrieved from
     * persistent storage and hydrated.
     *
     * @param \Illuminate\Database\Eloquent\Model&\Sprout\Database\Eloquent\Concerns\BelongsToManyTenants $model
     *
     * @return void
     *
     * @phpstan-param ChildModel                                                                          $model
     *
     * @throws \Sprout\Exceptions\TenantMissingException
     * @throws \Sprout\Exceptions\TenantMismatchException
     */
    public function retrieved(Model $model): void
    {
        if (! sprout()->withinContext()) {
            return;
        }

        /**
         * @var \Illuminate\Database\Eloquent\Relations\BelongsToMany<ChildModel, TenantModel> $relation
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
            // Just exit, an exception will have been thrown
            return;
        }

        if (! TenancyOptions::shouldHydrateTenantRelation($tenancy)) {
            if ($this->loadedRelation) {
                $model->unsetRelation($relation->getRelationName());
                $this->loadedRelation = false;
            }

            return;
        }

        /**
         * @var \Illuminate\Database\Eloquent\Model&\Sprout\Contracts\Tenant $tenant
         * @phpstan-var TenantModel                                          $tenant
         */
        $tenant = $tenancy->tenant();

        if ($this->loadedRelation) {
            $this->loadedRelation = false;

            return;
        }

        // Set the relation to contain the tenant
        $this->setRelation($model, $relation, $tenant);
    }

    /**
     * Set the hydrate value of a relation
     *
     * @param \Illuminate\Database\Eloquent\Model                                            $model
     * @param \Illuminate\Database\Eloquent\Relations\BelongsToMany<ChildModel, TenantModel> $relation
     * @param \Sprout\Contracts\Tenant                                                       $tenant
     *
     * @phpstan-param ChildModel                                                             $model
     * @phpstan-param TenantModel                                                            $tenant
     *
     * @return void
     */
    private function setRelation(Model $model, BelongsToMany $relation, Tenant $tenant): void
    {
        $model->setRelation($relation->getRelationName(), $tenant->newCollection([$tenant]));
    }
}
