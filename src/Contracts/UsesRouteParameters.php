<?php

namespace Sprout\Contracts;

/**
 * @package Resolvers
 */
interface UsesRouteParameters
{
    /**
     * Get the name of the route parameter
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     *
     * @return string
     */
    public function getRouteParameterName(Tenancy $tenancy): string;
}
