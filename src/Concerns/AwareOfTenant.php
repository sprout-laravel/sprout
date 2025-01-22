<?php
declare(strict_types=1);

namespace Sprout\Concerns;

use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;

/**
 * @phpstan-require-implements \Sprout\Contracts\TenantAware
 */
trait AwareOfTenant
{
    /**
     * @var \Sprout\Contracts\Tenant|null
     */
    private ?Tenant $tenant;

    /**
     * @var \Sprout\Contracts\Tenancy<*>|null
     */
    private ?Tenancy $tenancy;

    /**
     * Should the tenancy and tenant be refreshed when they change?
     *
     * @return bool
     */
    public function shouldBeRefreshed(): bool
    {
        return true;
    }

    /**
     * Get the tenant if there is one
     *
     * @return \Sprout\Contracts\Tenant|null
     */
    public function getTenant(): ?Tenant
    {
        return $this->tenant ?? null;
    }

    /**
     * Check if there is a tenant
     *
     * @return bool
     */
    public function hasTenant(): bool
    {
        return $this->getTenant() !== null;
    }

    /**
     * Set the tenant
     *
     * @param \Sprout\Contracts\Tenant|null $tenant
     *
     * @return static
     */
    public function setTenant(?Tenant $tenant): static
    {
        $this->tenant = $tenant;

        return $this;
    }

    /**
     * Get the tenancy if there is one
     *
     * @return \Sprout\Contracts\Tenancy<*>|null
     */
    public function getTenancy(): ?Tenancy
    {
        return $this->tenancy ?? null;
    }

    /**
     * Check if there is a tenancy
     *
     * @return bool
     */
    public function hasTenancy(): bool
    {
        return $this->getTenancy() !== null;
    }

    /**
     * Set the tenancy
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     * @param \Sprout\Contracts\Tenancy<TenantClass>|null $tenancy
     *
     * @return static
     */
    public function setTenancy(?Tenancy $tenancy): static
    {
        $this->tenancy = $tenancy;

        return $this;
    }
}
