<?php
declare(strict_types=1);

namespace Sprout\Core\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\RouteRegistrar;
use Sprout\Core\Concerns\AwareOfApp;
use Sprout\Core\Concerns\AwareOfSprout;
use Sprout\Core\Contracts\IdentityResolver;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Contracts\Tenant;

/**
 * Base Identity Resolver
 *
 * This is an abstract {@see \Sprout\Core\Contracts\IdentityResolver} to provide
 *  a shared implementation of common functionality.
 *
 * @package Core
 */
abstract class BaseIdentityResolver implements IdentityResolver
{
    use AwareOfSprout, AwareOfApp;

    /**
     * @var string
     */
    private string $name;

    /**
     * @var array<\Sprout\Core\Support\ResolutionHook>
     */
    private array $hooks;

    /**
     * Create a new instance
     *
     * @param string                                     $name
     * @param array<\Sprout\Core\Support\ResolutionHook> $hooks
     */
    public function __construct(string $name, array $hooks = [])
    {
        $this->name  = $name;
        $this->hooks = empty($hooks) ? [ResolutionHook::Routing] : $hooks;
    }

    /**
     * Get the registered name of the resolver
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the hooks this resolver uses
     *
     * @return array<\Sprout\Core\Support\ResolutionHook>
     */
    public function getHooks(): array
    {
        return $this->hooks;
    }

    /**
     * Perform setup actions for the tenant
     *
     * When a tenant is marked as the current tenant within a tenancy, this
     * method will be called to perform any necessary setup actions.
     * This method is also called if there is no current tenant, as there may
     * be actions needed.
     *
     * @template TenantClass of \Sprout\Core\Contracts\Tenant
     *
     * @param \Sprout\Core\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Core\Contracts\Tenant|null          $tenant
     *
     * @phpstan-param Tenant|null                         $tenant
     *
     * @return void
     */
    public function setup(Tenancy $tenancy, ?Tenant $tenant): void
    {
        // This is intentionally empty
    }

    /**
     * Can the resolver run on the request
     *
     * This method allows a resolver to prevent resolution with the request in
     * its current state, whether that means it's too early, or too late.
     *
     * @template TenantClass of \Sprout\Core\Contracts\Tenant
     *
     * @param \Illuminate\Http\Request                    $request
     * @param \Sprout\Core\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Core\Support\ResolutionHook         $hook
     *
     * @return bool
     */
    public function canResolve(Request $request, Tenancy $tenancy, ResolutionHook $hook): bool
    {
        return ! $tenancy->wasResolved() && in_array($hook, $this->hooks, true);
    }

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
     * @template TenantClass of \Sprout\Core\Contracts\Tenant
     *
     * @param string                                      $name
     * @param \Sprout\Core\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Core\Contracts\Tenant               $tenant
     * @param array<string, mixed>                        $parameters
     * @param bool                                        $absolute
     *
     * @phpstan-param TenantClass                         $tenant
     *
     * @return string
     */
    public function route(string $name, Tenancy $tenancy, Tenant $tenant, array $parameters = [], bool $absolute = true): string
    {
        return route($name, $parameters, $absolute);
    }

    /**
     * Configure the provided route for the resolver
     *
     * Configures a provided route to work with itself, adding parameters,
     * middleware, and anything else required, besides the default middleware.
     *
     * @param \Illuminate\Routing\RouteRegistrar                            $route
     * @param \Sprout\Core\Contracts\Tenancy<\Sprout\Core\Contracts\Tenant> $tenancy
     *
     * @return void
     */
    public function configureRoute(RouteRegistrar $route, Tenancy $tenancy): void
    {
        // This is intentionally empty
    }
}
