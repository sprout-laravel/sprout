<?php
declare(strict_types=1);

namespace Sprout\Database\Eloquent\Concerns;

use Sprout\Contracts\Tenant;
use Sprout\Database\Eloquent\Observers\BelongsToManyTenantsObserver;
use Sprout\Database\Eloquent\Scopes\BelongsToManyTenantsScope;

/**
 * Belongs to many Tenants
 *
 * This trait provides the basic supporting functionality required to automate
 * the relation between an Eloquent model and multiple {@see Tenant},
 * using a belongs to many relationship.
 *
 * @phpstan-ignore trait.unused
 */
trait BelongsToManyTenants
{
    use IsTenantChild;

    /**
     * Boot the trait
     *
     * @return void
     */
    public static function bootBelongsToManyTenants(): void
    {
        static::whenBooted(static function () {
            // Automatically scope queries
            static::addGlobalScope(new BelongsToManyTenantsScope());

            // Add the observer
            static::observe(new BelongsToManyTenantsObserver());
        });
    }
}
