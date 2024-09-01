<?php

namespace Sprout\Contracts;

/**
 * Tenant Provider Contract
 *
 * This contract marks a class as being a tenant provider, responsible for
 * retrieving instances of {@see \Sprout\Contracts\Tenant} for given
 * identifiers or keys.
 *
 * @template TenantClass of \Sprout\Contracts\Tenant
 *
 * @package Core
 */
interface TenantProvider
{
    /**
     * Get the registered name of the provider
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Retrieve a tenant by its identifier
     *
     * Gets an instance of the tenant implementation the provider represents,
     * using an identifier.
     *
     * @param string $identifier
     *
     * @return \Sprout\Contracts\Tenant|null
     *
     * @see \Sprout\Contracts\Tenant::getTenantIdentifier()
     * @see \Sprout\Contracts\Tenant::getTenantIdentifierName()
     *
     * @psalm-return TenantClass|null
     * @phpstan-return TenantClass|null
     */
    public function retrieveByIdentifier(string $identifier): ?Tenant;

    /**
     * Retrieve a tenant by its key
     *
     * Gets an instance of the tenant implementation the provider represents,
     * using a key.
     *
     * @param int|string $key
     *
     * @return \Sprout\Contracts\Tenant|null
     *
     * @see \Sprout\Contracts\Tenant::getTenantKey()
     * @see \Sprout\Contracts\Tenant::getTenantKeyName()
     *
     * @psalm-return TenantClass|null
     * @phpstan-return TenantClass|null
     */
    public function retrieveByKey(int|string $key): ?Tenant;
}
