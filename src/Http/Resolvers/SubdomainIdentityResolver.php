<?php
declare(strict_types=1);

namespace Sprout\Http\Resolvers;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Routing\RouteRegistrar;
use Sprout\Concerns\FindsIdentityInRouteParameter;
use Sprout\Contracts\IdentityResolver;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\UsesRouteParameters;

final class SubdomainIdentityResolver implements IdentityResolver, UsesRouteParameters
{
    use FindsIdentityInRouteParameter;

    /**
     * @var string
     */
    private string $name;

    private string $domain;

    private string $pattern;

    private string $parameter;

    public function __construct(string $name, string $domain, ?string $pattern = null, ?string $parameter = null)
    {
        $this->name      = $name;
        $this->domain    = $domain;
        $this->pattern   = $pattern ?? '.*';
        $this->parameter = $parameter ?? '{tenancy}_{resolver}';
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
     * Get the name of the route parameter
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     *
     * @return string
     */
    public function getRouteParameterName(Tenancy $tenancy): string
    {
        return str_replace(
            ['{tenancy}', '{resolver}'],
            [$tenancy->getName(), $this->getName()],
            $this->parameter
        );
    }

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
    public function resolve(Request $request, Tenancy $tenancy): ?string
    {
        $route = $request->route();

        if ($route instanceof Route) {
            return $this->resolveFromRoute($route, $tenancy);
        }

        $requestDomain = $request->getHost();

        if (($position = strpos($requestDomain, '.' . $this->domain)) !== false) {
            return substr($requestDomain, 0, $position);
        }

        return null;
    }

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
     */
    public function routes(Router $router, Closure $groupRoutes, Tenancy $tenancy): RouteRegistrar
    {
        return $router->domain(
            '{' . $this->getRouteParameterName($tenancy) . '}'
            . '.'
            . $this->domain
        )->where(
            [
                $this->getRouteParameterName($tenancy) => $this->pattern,
            ]
        )->middleware([])->group($groupRoutes);
    }
}
