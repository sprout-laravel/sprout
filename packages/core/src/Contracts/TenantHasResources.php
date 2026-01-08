<?php

namespace Sprout\Core\Contracts;

/**
 * Tenant Has Resources Contract
 *
 * This contract marks an implementation of {@see \Sprout\Core\Contracts\Tenant} as
 * having their own tenant-specific resources.
 */
interface TenantHasResources
{
    /**
     * Get the resource key used to identify the tenants resources
     *
     * @return string
     */
    public function getTenantResourceKey(): string;

    /**
     * Gets the name of the resource key used to identify the tenants resources
     *
     * @return string
     */
    public function getTenantResourceKeyName(): string;
}
