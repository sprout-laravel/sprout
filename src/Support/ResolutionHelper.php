<?php
declare(strict_types=1);

namespace Sprout\Core\Support;

use Illuminate\Http\Request;
use Sprout\Core\Contracts\IdentityResolverUsesParameters;
use Sprout\Core\Exceptions\MisconfigurationException;
use Sprout\Core\Exceptions\NoTenantFoundException;
use Sprout\Core\Sprout;

class ResolutionHelper
{
    /**
     * @param array<string|null> $options
     *
     * @return array<string|null>
     */
    public static function parseOptions(array $options): array
    {
        if (count($options) === 2) {
            [$resolverName, $tenancyName] = $options;
        } else if (count($options) === 1) {
            [$resolverName] = $options;
            $tenancyName = null;
        } else {
            $resolverName = $tenancyName = null;
        }

        return [$resolverName, $tenancyName];
    }

    /**
     * @param \Illuminate\Http\Request            $request
     * @param \Sprout\Core\Support\ResolutionHook $hook
     * @param string|null                         $resolverName
     * @param string|null                         $tenancyName
     * @param bool                                $throw
     *
     * @return bool
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Core\Exceptions\MisconfigurationException
     * @throws \Sprout\Core\Exceptions\NoTenantFoundException
     */
    public static function handleResolution(
        Request        $request,
        ResolutionHook $hook,
        Sprout         $sprout,
        ?string        $resolverName = null,
        ?string        $tenancyName = null,
        bool           $throw = true,
        bool           $optional = false
    ): bool
    {
        // Set the current hook
        $sprout->setCurrentHook($hook);

        // If the resolution hook is disabled, throw an exception
        if (! $sprout->supportsHook($hook)) {
            throw MisconfigurationException::unsupportedHook($hook);
        }

        $resolver = $sprout->resolvers()->get($resolverName);
        $tenancy  = $sprout->tenancies()->get($tenancyName);

        /**
         * @var \Sprout\Core\Contracts\IdentityResolver                       $resolver
         * @var \Sprout\Core\Contracts\Tenancy<\Sprout\Core\Contracts\Tenant> $tenancy
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

        // If there's no identity, and this is an optional resolution, we will
        // just return early
        if ($identity === null && $optional) {
            return false;
        }

        // Make sure the tenancy is aware of the resolver that was used to
        // resolve its tenant
        $tenancy->resolvedVia($resolver)->resolvedAt($hook);

        if ($identity === null || $tenancy->identify($identity) === false) {
            if ($throw) {
                throw NoTenantFoundException::make($resolver->getName(), $tenancy->getName());
            }

            return false;
        }

        return true;
    }
}
