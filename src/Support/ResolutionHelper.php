<?php
declare(strict_types=1);

namespace Sprout\Support;

use Illuminate\Http\Request;
use Sprout\Contracts\IdentityResolverUsesParameters;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Exceptions\NoTenantFound;
use Sprout\Sprout;

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
     * @param \Illuminate\Http\Request       $request
     * @param \Sprout\Support\ResolutionHook $hook
     * @param string|null                    $resolverName
     * @param string|null                    $tenancyName
     * @param bool                           $throw
     *
     * @return bool
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Exceptions\MisconfigurationException
     * @throws \Sprout\Exceptions\NoTenantFound
     */
    public static function handleResolution(Request $request, ResolutionHook $hook, ?string $resolverName = null, ?string $tenancyName = null, bool $throw = true): bool
    {
        /** @var \Sprout\Sprout $sprout */
        $sprout = app(Sprout::class);

        // If the resolution hook is disabled, throw an exception
        if (! $sprout->supportsHook($hook)) {
            throw MisconfigurationException::unsupportedHook($hook);
        }

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
