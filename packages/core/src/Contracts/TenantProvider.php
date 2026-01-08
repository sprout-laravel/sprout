<?php

namespace Sprout\Core\Contracts;

/**
 * Tenant Provider Contract
 *
 * This contract marks a class as being a tenant provider, responsible for
 * retrieving instances of {@see \Sprout\Core\Contracts\Tenant} for given
 * identifiers or keys.
 *
 * @template TenantClass of \Sprout\Core\Contracts\Tenant
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
     * @return \Sprout\Core\Contracts\Tenant|null
     *
     * @see \Sprout\Core\Contracts\Tenant::getTenantIdentifier()
     * @see \Sprout\Core\Contracts\Tenant::getTenantIdentifierName()
     *
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
     * @return \Sprout\Core\Contracts\Tenant|null
     *
     * @see \Sprout\Core\Contracts\Tenant::getTenantKey()
     * @see \Sprout\Core\Contracts\Tenant::getTenantKeyName()
     *
     * @phpstan-return TenantClass|null
     */
    public function retrieveByKey(int|string $key): ?Tenant;

    /**
     * Retrieve a tenant by its resource key
     *
     * Gets an instance of the tenant implementation the provider represents,
     * using a resource key.
     * The tenant class must implement the {@see \Sprout\Core\Contracts\TenantHasResources}
     * interface for this method to work.
     *
     * @param string $resourceKey
     *
     * @return (\Sprout\Core\Contracts\Tenant&\Sprout\Core\Contracts\TenantHasResources)|null
     *
     * @throws \Sprout\Core\Exceptions\MisconfigurationException
     *
     * @phpstan-return (TenantClass&\Sprout\Core\Contracts\TenantHasResources)|null
     *
     * @see \Sprout\Core\Contracts\Tenant::getTenantKeyName()
     * @see \Sprout\Core\Contracts\Tenant::getTenantKey()
     */
    public function retrieveByResourceKey(string $resourceKey): (Tenant&TenantHasResources)|null;
}
