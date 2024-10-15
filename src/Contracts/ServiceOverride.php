<?php

namespace Sprout\Contracts;

/**
 * Service Override
 *
 * This contract marks a class as being responsible for handling the overriding
 * of a core Laravel service, such as cookies, sessions, or the database.
 */
interface ServiceOverride
{
    /**
     * Set up the service override
     *
     * This method should perform any necessary setup actions for the service
     * override.
     * It is called when a new tenant is marked as the current tenant.
     *
     * @param \Sprout\Contracts\Tenancy<*> $tenancy
     * @param \Sprout\Contracts\Tenant     $tenant
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
     * @param \Sprout\Contracts\Tenancy<*> $tenancy
     * @param \Sprout\Contracts\Tenant     $tenant
     *
     * @return void
     */
    public function cleanup(Tenancy $tenancy, Tenant $tenant): void;
}
