<?php

namespace Sprout\Contracts;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;

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

    /**
     * Get an identifier from the route
     *
     * Locates a tenant identifier within the provided route and returns it.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Illuminate\Routing\Route              $route
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Illuminate\Http\Request               $request
     *
     * @return string|null
     */
    public function resolveFromRoute(Route $route, Tenancy $tenancy, Request $request): ?string;
}
