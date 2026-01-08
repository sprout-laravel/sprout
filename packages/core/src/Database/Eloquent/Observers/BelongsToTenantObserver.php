<?php
declare(strict_types=1);

namespace Sprout\Core\Database\Eloquent\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Contracts\Tenant;
use Sprout\Core\Exceptions\TenantMismatchException;
use Sprout\Core\Exceptions\TenantMissingException;
use Sprout\Core\TenancyOptions;
use function Sprout\Core\sprout;

/**
 * Belongs to Tenant Observer
 *
 * This is an observer automatically attached to Eloquent models that relate to
 * a single tenant using a "belongs to" relationship, to automate association
 * and hydration of the tenant relation.
 *
 * @template ChildModel of \Illuminate\Database\Eloquent\Model
 * @template TenantModel of \Illuminate\Database\Eloquent\Model&\Sprout\Core\Contracts\Tenant
 *
 * @see     \Sprout\Core\Database\Eloquent\Concerns\BelongsToTenant
 *
 * @package Database\Eloquent
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
     * @param \Illuminate\Database\Eloquent\Model&\Sprout\Core\Contracts\Tenant          $tenant
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
     * @param \Illuminate\Database\Eloquent\Model&\Sprout\Core\Database\Eloquent\Concerns\BelongsToTenant $model
     * @param \Sprout\Core\Contracts\Tenancy<TenantModel>                                                 $tenancy
     * @param \Illuminate\Database\Eloquent\Relations\BelongsTo<ChildModel, TenantModel>                  $relation
     * @param bool                                                                                        $succeedOnMatch
     *
     * @return bool
     *
     * @phpstan-param ChildModel                                                                          $model
     *
     * @throws \Sprout\Core\Exceptions\TenantMismatchException
     * @throws \Sprout\Core\Exceptions\TenantMissingException
     */
    private function passesInitialChecks(Model $model, Tenancy $tenancy, BelongsTo $relation, bool $succeedOnMatch = false): bool
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
         * @var \Illuminate\Database\Eloquent\Model&\Sprout\Core\Contracts\Tenant $tenant
         * @phpstan-var TenantModel                                               $tenant
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
     * Handle the creating event on the model
     *
     * The creating event is fired right before a model is persisted to the
     * database.
     *
     * @param \Illuminate\Database\Eloquent\Model&\Sprout\Core\Database\Eloquent\Concerns\BelongsToTenant $model
     *
     * @return bool
     *
     * @phpstan-param ChildModel                                                                          $model
     *
     * @throws \Sprout\Core\Exceptions\TenantMissingException
     * @throws \Sprout\Core\Exceptions\TenantMismatchException
     */
    public function creating(Model $model): bool
    {
        if (! sprout()->withinContext()) {
            return true;
        }

        /**
         * @var \Illuminate\Database\Eloquent\Relations\BelongsTo<ChildModel, TenantModel> $relation
         * @phpstan-ignore-next-line
         */
        $relation = $model->getTenantRelation();

        /**
         * @var \Sprout\Core\Contracts\Tenancy<TenantModel> $tenancy
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
     * Handle the retrieved event on the model
     *
     * The retrieved event is fired after a model is retrieved from
     * persistent storage and hydrated.
     *
     * @param \Illuminate\Database\Eloquent\Model&\Sprout\Core\Database\Eloquent\Concerns\BelongsToTenant $model
     *
     * @return void
     *
     * @phpstan-param ChildModel                                                                          $model
     *
     * @throws \Sprout\Core\Exceptions\TenantMissingException
     * @throws \Sprout\Core\Exceptions\TenantMismatchException
     */
    public function retrieved(Model $model): void
    {
        if (! sprout()->withinContext()) {
            return;
        }

        /**
         * @var \Illuminate\Database\Eloquent\Relations\BelongsTo<ChildModel, TenantModel> $relation
         * @phpstan-ignore-next-line
         */
        $relation = $model->getTenantRelation();

        /**
         * @var \Sprout\Core\Contracts\Tenancy<TenantModel> $tenancy
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
