<?php
declare(strict_types=1);

namespace Sprout\Database\Eloquent\Concerns;

use Sprout\Database\Eloquent\Observers\BelongsToTenantObserver;
use Sprout\Database\Eloquent\Scopes\BelongsToTenantScope;

trait BelongsToTenant
{
    use IsTenantChild;

    public static function bootBelongsToTenant(): void
    {
        // Automatically scope queries
        static::addGlobalScope(new BelongsToTenantScope());

        // Add the observer
        static::observe(new BelongsToTenantObserver());
    }
}
