<?php
declare(strict_types=1);

namespace Sprout\Concerns;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Facades\URL;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;

/**
 * @package Resolvers
 *
 * @phpstan-require-implements \Sprout\Contracts\IdentityResolverUsesParameters
 */
trait FindsIdentityInRouteParameter
{

    private ?string $pattern = null;

    private string $parameter = '{tenancy}_{resolver}';

    protected function initialiseRouteParameter(?string $pattern = null, ?string $parameter = null): void
    {
        $this->setPattern($pattern);

        if ($parameter !== null) {
            $this->setParameter($parameter);
        }
    }

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

    /**
     * Get the route parameter with braces
     *
     * @param \Sprout\Contracts\Tenancy $tenancy
     *
     * @return string
     */
    public function getRouteParameter(Tenancy $tenancy): string
    {
        return '{' . $this->getRouteParameterName($tenancy) . '}';
    }

    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    /**
     * @return bool
     *
     * @phpstan-assert-if-true string $this->getPattern()
     * @phpstan-assert-if-false null $this->getPattern()
     */
    public function hasPattern(): bool
    {
        return $this->pattern !== null;
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
     * @return array<string, string>
     */
    protected function getParameterPattern(Tenancy $tenancy): array
    {
        if (! $this->hasPattern()) {
            return [];
        }

        return [
            $this->getRouteParameterName($tenancy) => $this->getPattern(),
        ];
    }

    /**
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Illuminate\Routing\RouteRegistrar     $registrar
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     *
     * @return \Illuminate\Routing\RouteRegistrar
     */
    protected function applyParameterPattern(RouteRegistrar $registrar, Tenancy $tenancy): RouteRegistrar
    {
        if ($this->hasPattern()) {
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
     * @phpstan-param Tenant|null                    $tenant
     *
     * @return void
     */
    public function setup(Tenancy $tenancy, ?Tenant $tenant): void
    {
        // Set the default value of the parameter
        URL::defaults(
            [
                $this->getRouteParameterName($tenancy) => $tenant?->getTenantIdentifier(),
            ]
        );
    }
}
