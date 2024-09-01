<?php
declare(strict_types=1);

namespace Sprout\Concerns;

use Illuminate\Routing\Route;
use Sprout\Contracts\Tenancy;

/**
 * @package Resolvers
 *
 * @phpstan-require-implements \Sprout\Contracts\UsesRouteParameters
 * @psalm-require-implements \Sprout\Contracts\UsesRouteParameters
 */
trait FindsIdentityInRouteParameter
{
    /**
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Illuminate\Routing\Route              $route
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     *
     * @return string|null
     */
    protected function resolveFromRoute(Route $route, Tenancy $tenancy): ?string
    {
        $identifier = $route->parameter($this->getRouteParameterName($tenancy));

        if (is_string($identifier)) {
            return $identifier;
        }

        return null;
    }
}
