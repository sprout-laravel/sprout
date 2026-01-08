<?php
declare(strict_types=1);

namespace Sprout\Core\Concerns;

use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Contracts\Tenant;

/**
 * @phpstan-require-implements \Sprout\Core\Contracts\TenantAware
 */
trait AwareOfTenant
{
    /**
     * @var \Sprout\Core\Contracts\Tenant|null
     */
    private ?Tenant $tenant;

    /**
     * @var \Sprout\Core\Contracts\Tenancy<*>|null
     */
    private ?Tenancy $tenancy;

    /**
     * Should the tenancy and tenant be refreshed when they change?
     *
     * @return bool
     */
    public function shouldBeRefreshed(): bool
    {
        return true; // @codeCoverageIgnore
    }

    /**
     * Get the tenant if there is one
     *
     * @return \Sprout\Core\Contracts\Tenant|null
     */
    public function getTenant(): ?Tenant
    {
        return $this->tenant ?? null;
    }

    /**
     * Check if there is a tenant
     *
     * @return bool
     *
     * @phpstan-assert-if-true !null $this->tenant
     * @phpstan-assert-if-false null $this->tenant
     */
    public function hasTenant(): bool
    {
        return $this->getTenant() !== null;
    }

    /**
     * Set the tenant
     *
     * @param \Sprout\Core\Contracts\Tenant|null $tenant
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
     * @return \Sprout\Core\Contracts\Tenancy<*>|null
     */
    public function getTenancy(): ?Tenancy
    {
        return $this->tenancy ?? null;
    }

    /**
     * Check if there is a tenancy
     *
     * @return bool
     *
     * @phpstan-assert-if-true !null $this->tenancy
     * @phpstan-assert-if-false null $this->tenancy
     */
    public function hasTenancy(): bool
    {
        return $this->getTenancy() !== null;
    }

    /**
     * Set the tenancy
     *
     * @template TenantClass of \Sprout\Core\Contracts\Tenant
     * @param \Sprout\Core\Contracts\Tenancy<TenantClass>|null $tenancy
     *
     * @return static
     */
    public function setTenancy(?Tenancy $tenancy): static
    {
        $this->tenancy = $tenancy;

        return $this;
    }
}
