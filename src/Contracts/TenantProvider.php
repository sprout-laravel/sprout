<?php

namespace Sprout\Contracts;

/**
 * Tenant Provider Contract
 *
 * This contract marks a class as being a tenant provider, responsible for
 * retrieving instances of {@see Tenant} for given
 * identifiers or keys.
 *
 * @template TenantClass of \Sprout\Contracts\Tenant
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
     * @return Tenant|null
     *
     * @see Tenant::getTenantIdentifier()
     * @see Tenant::getTenantIdentifierName()
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
     * @return Tenant|null
     *
     * @see Tenant::getTenantKey()
     * @see Tenant::getTenantKeyName()
     *
     * @phpstan-return TenantClass|null
     */
    public function retrieveByKey(int|string $key): ?Tenant;

    /**
     * Retrieve a tenant by its resource key
     *
     * Gets an instance of the tenant implementation the provider represents,
     * using a resource key.
     * The tenant class must implement the {@see TenantHasResources}
     * interface for this method to work.
     *
     * @param string $resourceKey
     *
     * @return (Tenant&TenantHasResources)|null
     *
     * @phpstan-return (TenantClass&TenantHasResources)|null
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     *
     * @see Tenant::getTenantKeyName()
     * @see Tenant::getTenantKey()
     */
    public function retrieveByResourceKey(string $resourceKey): (Tenant&TenantHasResources)|null;
}
