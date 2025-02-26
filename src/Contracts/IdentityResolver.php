<?php

namespace Sprout\Contracts;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Routing\RouteRegistrar;
use Sprout\Support\ResolutionHook;

/**
 * Identity Resolver Contract
 *
 * This contract marks a class as being responsible for resolving a tenant
 * identifier for a given request, as well as configuring the routes where
 * necessary.
 *
 * @provider Resolvers
 */
interface IdentityResolver
{
    /**
     * Get the registered name of the resolver
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get an identifier from the request
     *
     * Locates a tenant identifier within the provided request and returns it.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Illuminate\Http\Request               $request
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     *
     * @return string|null
     */
    public function resolveFromRequest(Request $request, Tenancy $tenancy): ?string;

    /**
     * Create a route group for the resolver
     *
     * Creates and configures a route group with the necessary settings to
     * support identity resolution.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Illuminate\Routing\Router             $router
     * @param \Closure                               $groupRoutes
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     *
     * @return \Illuminate\Routing\RouteRegistrar
     *
     * @deprecated Use {@see self::configureRoute()} instead
     */
    public function routes(Router $router, Closure $groupRoutes, Tenancy $tenancy): RouteRegistrar;

    /**
     * Configure the provided route for the resolver
     *
     * Configures a provided route to work with itself, adding parameters,
     * middleware, and anything else required, besides the default middleware.
     *
     * @param \Illuminate\Routing\RouteRegistrar                  $route
     * @param \Sprout\Contracts\Tenancy<\Sprout\Contracts\Tenant> $tenancy
     *
     * @return void
     */
    public function configureRoute(RouteRegistrar $route, Tenancy $tenancy): void;

    /**
     * Perform setup actions for the tenant
     *
     * When a tenant is marked as the current tenant within a tenancy, this
     * method will be called to perform any necessary setup actions.
     * This method is also called if there is no current tenant, as there may
     * be actions needed.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Contracts\Tenant|null          $tenant
     *
     * @phpstan-param TenantClass|null               $tenant
     *
     * @return void
     */
    public function setup(Tenancy $tenancy, ?Tenant $tenant): void;

    /**
     * Can the resolver run on the request
     *
     * This method allows a resolver to prevent resolution with the request in
     * its current state, whether that means it's too early, or too late.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Illuminate\Http\Request               $request
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Support\ResolutionHook         $hook
     *
     * @return bool
     */
    public function canResolve(Request $request, Tenancy $tenancy, ResolutionHook $hook): bool;

    /**
     * Generate a URL for a tenanted route
     *
     * This method wraps Laravel's {@see \route()} helper to allow for
     * identity resolvers that use route parameters.
     * Route parameter names are dynamic and configurable, so hard-coding them
     * is less than ideal.
     *
     * This method is only really useful for identity resolvers that use route
     * parameters, but, it's here for backwards compatibility.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param string                                 $name
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Contracts\Tenant               $tenant
     * @param array<string, mixed>                   $parameters
     * @param bool                                   $absolute
     *
     * @phpstan-param TenantClass                    $tenant
     *
     * @return string
     */
    public function route(string $name, Tenancy $tenancy, Tenant $tenant, array $parameters = [], bool $absolute = true): string;
}
