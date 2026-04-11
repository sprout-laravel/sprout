<?php
declare(strict_types=1);

namespace Sprout\Support;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Sprout\Contracts\IdentityResolver;
use Sprout\Contracts\IdentityResolverUsesParameters;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Exceptions\NoTenantFoundException;
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
            $tenancyName    = null;
        } else {
            $resolverName = $tenancyName = null;
        }

        return [$resolverName, $tenancyName];
    }

    /**
     * @param Request        $request
     * @param ResolutionHook $hook
     * @param string|null    $resolverName
     * @param string|null    $tenancyName
     * @param bool           $throw
     *
     * @return bool
     *
     * @throws BindingResolutionException
     * @throws MisconfigurationException
     * @throws NoTenantFoundException
     */
    public static function handleResolution(
        Request        $request,
        ResolutionHook $hook,
        Sprout         $sprout,
        ?string        $resolverName = null,
        ?string        $tenancyName = null,
        bool           $throw = true,
        bool           $optional = false,
    ): bool {
        // Set the current hook
        $sprout->setCurrentHook($hook);

        // If the resolution hook is disabled, throw an exception
        if (! $sprout->supportsHook($hook)) {
            throw MisconfigurationException::unsupportedHook($hook);
        }

        $resolver = $sprout->resolvers()->get($resolverName);
        $tenancy  = $sprout->tenancies()->get($tenancyName);

        /**
         * @var IdentityResolver $resolver
         * @var Tenancy<Tenant>  $tenancy
         */
        if ($tenancy->check() || ! $resolver->canResolve($request, $tenancy, $hook)) {
            return false;
        }

        $sprout->setCurrentTenancy($tenancy);

        /** @var Route|null $route */
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
