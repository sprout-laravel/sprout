<?php
declare(strict_types=1);

namespace Sprout\Concerns;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteRegistrar;
use Sprout\Contracts\Tenancy;

/**
 * @package Resolvers
 *
 * @phpstan-require-implements \Sprout\Contracts\UsesRouteParameters
 */
trait FindsIdentityInRouteParameter
{

    private ?string $pattern = null;

    private string $parameter = '{tenancy}_{resolver}';

    public function setPattern(?string $pattern): void
    {
        $this->pattern = $pattern;
    }

    public function setParameter(string $parameter): void
    {
        $this->parameter = $parameter;
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
            $this->getParameter()
        );
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getParameter(): string
    {
        return $this->parameter;
    }

    /**
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     *
     * @return array
     */
    protected function getParameterPattern(Tenancy $tenancy): array
    {
        return [
            $this->getRouteParameterName($tenancy) => $this->getPattern(),
        ];
    }

    /**
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Illuminate\Routing\RouteRegistrar $registrar
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     *
     * @return \Illuminate\Routing\RouteRegistrar
     */
    protected function applyParameterPattern(RouteRegistrar $registrar, Tenancy $tenancy): RouteRegistrar
    {
        if ($this->pattern === null) {
            return $registrar;
        }

        return $registrar->where($this->getParameterPattern($tenancy));
    }

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
    public function resolveFromRoute(Route $route, Tenancy $tenancy, Request $request): ?string
    {
        $identifier = $route->parameter($this->getRouteParameterName($tenancy));

        if (is_string($identifier)) {
            return $identifier;
        }

        return null;
    }
}
