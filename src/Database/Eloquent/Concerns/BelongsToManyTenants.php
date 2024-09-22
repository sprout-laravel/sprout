<?php
declare(strict_types=1);

namespace Sprout\Database\Eloquent\Concerns;

use Sprout\Database\Eloquent\Observers\BelongsToManyTenantsObserver;
use Sprout\Database\Eloquent\Scopes\BelongsToManyTenantsScope;

trait BelongsToManyTenants
{
    use IsTenantChild;

    public static function bootBelongsToManyTenants(): void
    {
        // Automatically scope queries
        static::addGlobalScope(new BelongsToManyTenantsScope());

        // Add the observer
        static::observe(new BelongsToManyTenantsObserver());
    }
}
