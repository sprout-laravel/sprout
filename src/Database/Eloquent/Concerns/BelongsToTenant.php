<?php
declare(strict_types=1);

namespace Sprout\Database\Eloquent\Concerns;

use Sprout\Database\Eloquent\Observers\BelongsToTenantObserver;
use Sprout\Database\Eloquent\Scopes\BelongsToTenantScope;

/**
 * Belongs to many Tenants
 *
 * This trait provides the basic supporting functionality required to automate
 * the relation between an Eloquent model and a {@see \Sprout\Contracts\Tenant},
 * using a belongs to relationship.
 *
 * @package Database\Eloquent
 */
trait BelongsToTenant
{
    use IsTenantChild;

    /**
     * Boot the trait
     *
     * @return void
     */
    public static function bootBelongsToTenant(): void
    {
        // Automatically scope queries
        static::addGlobalScope(new BelongsToTenantScope());

        // Add the observer
        static::observe(new BelongsToTenantObserver());
    }
}
