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
     * Throw an exception if the model isn't related to the tenant
     *
     * @return string
     */
    public static function throwIfNotRelated(): string
    {
        return 'tenant-relation.strict';
    }

    /**
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     *
     * @return bool
     */
    public static function shouldHydrateTenantRelation(Tenancy $tenancy): bool
    {
        return $tenancy->hasOption(static::hydrateTenantRelation());
    }

    /**
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     *
     * @return bool
     */
    public static function shouldThrowIfNotRelated(Tenancy $tenancy): bool
    {
        return $tenancy->hasOption(static::throwIfNotRelated());
    }
}
