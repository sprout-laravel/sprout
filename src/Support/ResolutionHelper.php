<?php
declare(strict_types=1);

namespace Sprout\Support;

use Illuminate\Http\Request;
use Sprout\Contracts\IdentityResolverUsesParameters;
use Sprout\Exceptions\NoTenantFound;
use Sprout\Sprout;

class ResolutionHelper
{
    /**
     * @param \Illuminate\Http\Request       $request
     * @param \Sprout\Support\ResolutionHook $hook
     * @param string|null                    $resolverName
     * @param string|null                    $tenancyName
     *
     * @return bool
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Exceptions\NoTenantFound
     */
    public static function handleResolution(Request $request, ResolutionHook $hook, ?string $resolverName = null, ?string $tenancyName = null, bool $throw = true): bool
    {
        $sprout   = app()->make(Sprout::class);
        $resolver = $sprout->resolvers()->get($resolverName);
        $tenancy  = $sprout->tenancies()->get($tenancyName);

        /**
         * @var \Sprout\Contracts\IdentityResolver                  $resolver
         * @var \Sprout\Contracts\Tenancy<\Sprout\Contracts\Tenant> $tenancy
         */

        if ($tenancy->check() || ! $resolver->canResolve($request, $tenancy, $hook)) {
            return false;
        }

        $sprout->setCurrentTenancy($tenancy);

        /** @var \Illuminate\Routing\Route|null $route */
        $route = $request->route();

        // Is the resolver using a parameter, and is the parameter present?
        if (
            $resolver instanceof IdentityResolverUsesParameters
            && $route !== null
            && $route->hasParameter($resolver->getRouteParameterName($tenancy))
        ) {
            // Use the route to resolve the identity from the parameter
            $identity = $resolver->resolveFromRoute($route, $tenancy, $request);
            $route->forgetParameter($resolver->getRouteParameterName($tenancy));
        } else {
            // If we reach here, either the resolver doesn't use parameters, or
            // the parameter isn't present in the URL, so we'll default to
            // using the request
            $identity = $resolver->resolveFromRequest($request, $tenancy);
        }

        // Make sure the tenancy knows which resolver resolved it
        $tenancy->resolvedVia($resolver)->resolvedAt($hook);

        if ($identity === null || $tenancy->identify($identity) === false) {
            if ($throw) {
                throw NoTenantFound::make($resolver->getName(), $tenancy->getName());
            }

            return false;
        }

        return true;
    }
}
