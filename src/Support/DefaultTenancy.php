<?php
declare(strict_types=1);

namespace Sprout\Support;

use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantProvider;

/**
 * @template TenantClass of \Sprout\Contracts\Tenant
 *
 * @implements \Sprout\Contracts\Tenancy<TenantClass>
 */
final class DefaultTenancy implements Tenancy
{
    private string $name;

    /**
     * @var \Sprout\Contracts\TenantProvider<TenantClass>
     */
    private TenantProvider $provider;

    /**
     * @var \Sprout\Contracts\Tenant|null
     *
     * @psalm-var TenantClass|null
     * @phpstan-var TenantClass|null
     */
    private ?Tenant $tenant = null;

    /**
     * @param string                                        $name
     * @param \Sprout\Contracts\TenantProvider<TenantClass> $provider
     */
    public function __construct(string $name, TenantProvider $provider)
    {
        $this->name     = $name;
        $this->provider = $provider;
    }

    /**
     * Get the registered name of the tenancy
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Check if there's a current tenant
     *
     * Checks to see if a current tenant is set.
     * Implementations should not attempt to load a tenant if one is not
     * present, but should perform a simple check for the present of a
     * tenant.
     *
     * @return bool
     *
     * @psalm-mutation-free
     */
    public function check(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Get the current tenant
     *
     * Gets the current set tenant if one is present.
     * Implementations may attempt to load a tenant if one isn't present, though
     * this is not required.
     *
     * @return \Sprout\Contracts\Tenant|null
     *
     * @psalm-return TenantClass|null
     * @phpstan-return TenantClass|null
     */
    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }
}
