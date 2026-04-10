<?php

namespace Sprout\Core\Contracts;

/**
 * Service Override
 *
 * This contract marks a class as being responsible for handling the overriding
 * of a core Laravel service, such as cookies, sessions, or the database.
 *
 * @package Overrides
 */
interface ServiceOverride
{
    /**
     * Create a new instance of the service override
     *
     * @param string               $service
     * @param array<string, mixed> $config
     */
    public function __construct(string $service, array $config);

    /**
     * Set up the service override
     *
     * This method should perform any necessary setup actions for the service
     * override.
     * It is called when a new tenant is marked as the current tenant.
     *
     * @template TenantClass of \Sprout\Core\Contracts\Tenant
     *
     * @param \Sprout\Core\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Core\Contracts\Tenant               $tenant
     *
     * @phpstan-param TenantClass                         $tenant
     *
     * @return void
     */
    public function setup(Tenancy $tenancy, Tenant $tenant): void;

    /**
     * Clean up the service override
     *
     * This method should perform any necessary setup actions for the service
     * override.
     * It is called when the current tenant is unset, either to be replaced
     * by another tenant, or none.
     *
     * It will be called before {@see self::setup()}, but only if the previous
     * tenant was not null.
     *
     * @template TenantClass of \Sprout\Core\Contracts\Tenant
     *
     * @param \Sprout\Core\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Core\Contracts\Tenant               $tenant
     *
     * @phpstan-param TenantClass                         $tenant
     *
     * @return void
     */
    public function cleanup(Tenancy $tenancy, Tenant $tenant): void;
}
