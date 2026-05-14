<?php
declare(strict_types=1);

namespace Sprout\Database\Eloquent\Concerns;

use Illuminate\Foundation\Application;
use Sprout\Database\Eloquent\Observers\BelongsToManyTenantsObserver;
use Sprout\Database\Eloquent\Scopes\BelongsToManyTenantsScope;

/**
 * Belongs to many Tenants
 *
 * This trait provides the basic supporting functionality required to automate
 * the relation between an Eloquent model and multiple {@see \Sprout\Contracts\Tenant},
 * using a belongs to many relationship.
 *
 * @package Database\Eloquent
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
        $run = static function () {
            // Automatically scope queries
            static::addGlobalScope(new BelongsToManyTenantsScope());

            // Add the observer
            static::observe(new BelongsToManyTenantsObserver());
        };

        if (version_compare(Application::VERSION, '13.0.0', '>=')) {
            static::whenBooted($run);
        } else {
            $run();
        }
    }
}
