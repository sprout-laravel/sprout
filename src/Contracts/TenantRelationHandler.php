<?php

namespace Sprout\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @template ParentModel of \Illuminate\Database\Eloquent\Model
 * @template ChildModel of \Illuminate\Database\Eloquent\Model&\Sprout\Contracts\Tenant
 * @template RelationClass of \Illuminate\Database\Eloquent\Relations\Relation
 */
interface TenantRelationHandler
{
    /**
     * Get the model class that belongs to the tenant
     *
     * @return class-string<ParentModel>
     */
    public function getParentModel(): string;

    /**
     * Get the name of the tenant relationship
     *
     * @return string
     */
    public function getRelationName(): string;

    /**
     * Get the tenant relationship
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation<ChildModel>
     *
     * @phpstan-param ParentModel                 $model
     *
     * @phpstan-return RelationClass
     */
    public function getRelation(Model $model): Relation;

    /**
     * Whether the relation populates before it's saved
     *
     * This method returns true if the relationship requires population
     * before the model is persisted (creating event), or false if after
     * (created event).
     *
     * @return bool
     */
    public function populateBeforePersisting(): bool;

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
    public function populateRelation(Model $model, Tenancy $tenancy): void;

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
    public function hydrateRelation(Model $model, Tenancy $tenancy): void;
}
