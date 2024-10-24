<?php
declare(strict_types=1);

namespace Sprout;

use Sprout\Contracts\Tenancy;

/**
 * Tenancy Options
 *
 * This is a helper class for providing and check for tenancy options.
 *
 * @package Core
 */
class TenancyOptions
{
    /**
     * Tenant relations should be automatically hydrated
     *
     * @return string
     */
    public static function hydrateTenantRelation(): string
    {
        return 'tenant-relation.hydrate';
    }

    /**
     * Throw an exception if the model isn't related to the tenant
     *
     * @return string
     */
    public static function throwIfNotRelated(): string
    {
        return 'tenant-relation.strict';
    }

    /**
     * @param \Sprout\Contracts\Tenancy<*> $tenancy
     *
     * @return bool
     */
    public static function shouldHydrateTenantRelation(Tenancy $tenancy): bool
    {
        return $tenancy->hasOption(static::hydrateTenantRelation());
    }

    /**
     * @param \Sprout\Contracts\Tenancy<*> $tenancy
     *
     * @return bool
     */
    public static function shouldThrowIfNotRelated(Tenancy $tenancy): bool
    {
        return $tenancy->hasOption(static::throwIfNotRelated());
    }
}
