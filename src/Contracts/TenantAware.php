<?php

namespace Sprout\Core\Contracts;

/**
 *
 */
interface TenantAware
{
    /**
     * Should the tenancy and tenant be refreshed when they change?
     *
     * @return bool
     */
    public function shouldBeRefreshed(): bool;

    /**
     * Get the tenant if there is one
     *
     * @return \Sprout\Core\Contracts\Tenant|null
     */
    public function getTenant(): ?Tenant;

    /**
     * Check if there is a tenant
     *
     * @return bool
     */
    public function hasTenant(): bool;

    /**
     * Set the tenant
     *
     * @param \Sprout\Core\Contracts\Tenant|null $tenant
     *
     * @return static
     */
    public function setTenant(?Tenant $tenant): static;

    /**
     * Get the tenancy if there is one
     *
     * @return \Sprout\Core\Contracts\Tenancy<*>|null
     */
    public function getTenancy(): ?Tenancy;

    /**
     * Check if there is a tenancy
     *
     * @return bool
     */
    public function hasTenancy(): bool;

    /**
     * Set the tenancy
     *
     * @template TenantClass of \Sprout\Core\Contracts\Tenant
     * @param \Sprout\Core\Contracts\Tenancy<TenantClass>|null $tenancy
     *
     * @return static
     */
    public function setTenancy(?Tenancy $tenancy): static;
}
