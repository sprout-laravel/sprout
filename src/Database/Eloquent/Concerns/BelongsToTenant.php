<?php
declare(strict_types=1);

namespace Sprout\Core\Database\Eloquent\Concerns;

use Sprout\Core\Database\Eloquent\Observers\BelongsToTenantObserver;
use Sprout\Core\Database\Eloquent\Scopes\BelongsToTenantScope;

/**
 * Belongs to many Tenants
 *
 * This trait provides the basic supporting functionality required to automate
 * the relation between an Eloquent model and a {@see \Sprout\Core\Contracts\Tenant},
 * using a belongs to relationship.
 *
 * @package        Database\Eloquent
 *
 * @phpstan-ignore trait.unused
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
