<?php
declare(strict_types=1);

namespace Sprout\Core\Database\Eloquent\Concerns;

use Sprout\Core\Database\Eloquent\Observers\BelongsToManyTenantsObserver;
use Sprout\Core\Database\Eloquent\Scopes\BelongsToManyTenantsScope;

/**
 * Belongs to many Tenants
 *
 * This trait provides the basic supporting functionality required to automate
 * the relation between an Eloquent model and multiple {@see \Sprout\Core\Contracts\Tenant},
 * using a belongs to many relationship.
 *
 * @package        Database\Eloquent
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
        // Automatically scope queries
        static::addGlobalScope(new BelongsToManyTenantsScope());

        // Add the observer
        static::observe(new BelongsToManyTenantsObserver());
    }
}
