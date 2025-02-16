<?php
declare(strict_types=1);

namespace Sprout\Concerns;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Facades\URL;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Support\PlaceholderHelper;

/**
 * Find Identity in Route Parameter
 *
 * This trait provides both helper methods and default implementations for
 * methods required by the {@see \Sprout\Contracts\IdentityResolverUsesParameters}
 * interface.
 *
 * @package Resolvers
 *
 * @phpstan-require-implements \Sprout\Contracts\IdentityResolverUsesParameters
 */
trait FindsIdentityInRouteParameter
{
    /**
     * The route parameter pattern
     *
     * @var string|null
     */
    private ?string $pattern = null;

    /**
     * The route parameter name
     *
     * @var string
     */
    private string $parameter = '{tenancy}_{resolver}';

    /**
     * Initialise the pattern and parameter name values
     *
     * This method sets the value for {@see self::$pattern}, and optionally
     * {@see self::$parameter} if the value isn't null.
     *
     * @param string|null $pattern
     * @param string|null $parameter
     *
     * @return void
     */
    protected function initialiseRouteParameter(?string $pattern = null, ?string $parameter = null): void
    {
        $this->setPattern($pattern);

        if ($parameter !== null) {
            $this->setParameter($parameter);
        }
    }

    /**
     * Set the route parameter pattern
     *
     * @param string|null $pattern
     *
     * @return void
     */
    public function setPattern(?string $pattern): void
    {
        $this->pattern = $pattern;
    }

    /**
     * Set the route parameter name
     *
     * @param string $parameter
     *
     * @return void
     */
    public function setParameter(string $parameter): void
    {
        $this->parameter = $parameter;
    }

    /**
     * Get the name of the route parameter
     *
     * This method uses the route parameter name stored in {@see self::$parameter},
     * replacing occurrences of <code>{tenancy}</code> with the name of the
     * tenancy, and <code>{resolver}</code> with the name of the resolver.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     *
     * @return string
     */
    public function getRouteParameterName(Tenancy $tenancy): string
    {
        return PlaceholderHelper::replaceForParameter(
            $this->getParameter(),
            [
                'tenancy'  => $tenancy->getName(),
                'resolver' => $this->getName(),
            ],
        );
    }

    /**
     * Get the route parameter placeholder
     *
     * This method returns the route parameter provided by
     * {@see self::getRouteParameterName()}, but wrapped with curly braces for
     * use in route definitions.
     *
     * @param \Sprout\Contracts\Tenancy<\Sprout\Contracts\Tenant> $tenancy
     *
     * @return string
     */
    public function getRouteParameter(Tenancy $tenancy): string
    {
        return '{' . $this->getRouteParameterName($tenancy) . '}';
    }

    /**
     * Get the route parameter pattern
     *
     * @return string|null
     */
    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    /**
     * Check if there is a route parameter pattern set
     *
     * @return bool
     *
     * @phpstan-assert-if-true string $this->getPattern()
     * @phpstan-assert-if-false null $this->getPattern()
     */
    public function hasPattern(): bool
    {
        return $this->getPattern() !== null;
    }

    /**
     * Get the route parameter pattern
     *
     * @return string
     */
    public function getParameter(): string
    {
        return $this->parameter;
    }

    /**
     * Get the route parameter pattern mapping
     *
     * This method returns an array mapping the route parameter name to its
     * pattern, for use in route definitions.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     *
     * @return array<string, string>
     */
    protected function getParameterPatternMapping(Tenancy $tenancy): array
    {
        if (! $this->hasPattern()) {
            return []; // @codeCoverageIgnore
        }

        return [
            $this->getRouteParameterName($tenancy) => $this->getPattern(),
        ];
    }

    /**
     * Apply the route parameter pattern mapping to a route
     *
     * This method applies the route parameter pattern mapping provided by
     * {@see self::getParameterPatternMapping()} to a supplied route registrar,
     * for a supplied tenancy.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Illuminate\Routing\RouteRegistrar     $registrar
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     *
     * @return \Illuminate\Routing\RouteRegistrar
     */
    protected function applyParameterPatternMapping(RouteRegistrar $registrar, Tenancy $tenancy): RouteRegistrar
    {
        if (! $this->hasPattern()) {
            return $registrar;
        }

        return $registrar->where($this->getParameterPatternMapping($tenancy));
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

        return null; // @codeCoverageIgnore
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

    /**
     * Generate a URL for a tenanted route
     *
     * This method wraps Laravel's {@see \route()} helper to allow for
     * identity resolvers that use route parameters.
     * Route parameter names are dynamic and configurable, so hard-coding them
     * is less than ideal.
     *
     * This method is only really useful for identity resolvers that use route
     * parameters, but it's here for backwards compatibility.
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
    public function route(string $name, Tenancy $tenancy, Tenant $tenant, array $parameters = [], bool $absolute = true): string
    {
        $parameter = $this->getRouteParameterName($tenancy);

        if (! isset($parameters[$parameter])) {
            $parameters[$parameter] = $tenant->getTenantIdentifier();
        }

        return route($name, $parameters, $absolute);
    }
}
