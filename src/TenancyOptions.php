<?php
declare(strict_types=1);

namespace Sprout;

use Sprout\Contracts\Tenancy;

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
     * Check whether a model relates to the tenant before hydrating
     *
     * @return string
     */
    public static function checkForRelationWithTenant(): string
    {
        return 'tenant-relation.check';
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
     * @param \Sprout\Contracts\Tenancy<\Sprout\Contracts\Tenant> $tenancy
     *
     * @return bool
     */
    public static function shouldHydrateTenantRelation(Tenancy $tenancy): bool
    {
        return (bool)$tenancy->option(static::hydrateTenantRelation(), true);
    }

    /**
     * @param \Sprout\Contracts\Tenancy<\Sprout\Contracts\Tenant> $tenancy
     *
     * @return bool
     */
    public static function shouldCheckForRelationWithTenant(Tenancy $tenancy): bool
    {
        return (bool)$tenancy->option(static::checkForRelationWithTenant(), true);
    }

    /**
     * @param \Sprout\Contracts\Tenancy<\Sprout\Contracts\Tenant> $tenancy
     *
     * @return bool
     */
    public static function shouldThrowIfNotRelated(Tenancy $tenancy): bool
    {
        return (bool)$tenancy->option(static::throwIfNotRelated(), false);
    }
}
