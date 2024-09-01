<?php

namespace Sprout\Contracts;

/**
 * Tenancy Contract
 *
 * This contract represents a tenancy, an object responsible for managing the
 * state of the current tenancy, i.e. the current {@see \Sprout\Contracts\Tenant}.
 *
 * @template TenantClass of \Sprout\Contracts\Tenant
 *
 * @package Core
 */
interface Tenancy
{
    /**
     * Get the registered name of the tenancy
     *
     * @return string
     */
    public function getName(): string;

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
    public function check(): bool;

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
    public function tenant(): ?Tenant;

    /**
     * Get the tenants key
     *
     * Get the tenant key for the current tenant if there is one.
     *
     * @return int|string|null
     *@see \Sprout\Contracts\Tenant::getTenantKey()
     *
     */
    public function key(): int|string|null;

    /**
     * Get the tenants' identifier
     *
     * Get the tenant identifier for the current tenant if there is one.
     *
     * @return string|null
     *@see \Sprout\Contracts\Tenant::getTenantIdentifier()
     *
     */
    public function identifier(): ?string;
}
