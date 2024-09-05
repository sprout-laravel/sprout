<?php
declare(strict_types=1);

namespace Sprout\Listeners;

use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Sprout\Contracts\UsesRouteParameters;
use Sprout\Exceptions\NoTenantFound;
use Sprout\Http\Middleware\TenantRoutes;
use Sprout\Managers\IdentityResolverManager;
use Sprout\Managers\TenancyManager;
use Sprout\Sprout;

final class IdentifyTenantOnRouting
{
    /**
     * @var \Sprout\Sprout
     */
    private Sprout $sprout;

    /**
     * @var \Sprout\Managers\IdentityResolverManager
     */
    private IdentityResolverManager $resolverManager;

    /**
     * @var \Sprout\Managers\TenancyManager
     */
    private TenancyManager $tenancyManager;

    public function __construct(Sprout $sprout, IdentityResolverManager $resolverManager, TenancyManager $tenancyManager)
    {
        $this->sprout          = $sprout;
        $this->resolverManager = $resolverManager;
        $this->tenancyManager  = $tenancyManager;
    }

    /**
     * @param \Illuminate\Routing\Events\RouteMatched $event
     *
     * @return void
     *
     * @throws \Sprout\Exceptions\NoTenantFound
     */
    public function handle(RouteMatched $event): void
    {
        $options = $this->parseTenantMiddleware($event->route);

        if ($options === null) {
            return;
        }

        [$resolverName, $tenancyName] = $options;

        $resolver = $this->resolverManager->get($resolverName);
        $tenancy  = $this->tenancyManager->get($tenancyName);

        $this->sprout->setCurrentTenancy($tenancy);

        /**
         * @var \Sprout\Contracts\IdentityResolver                  $resolver
         * @var \Sprout\Contracts\Tenancy<\Sprout\Contracts\Tenant> $tenancy
         */

        $identity = null;

        // Is the resolver using a parameter, and is the parameter present?
        if (
            $resolver instanceof UsesRouteParameters
            && $event->route->hasParameter($resolver->getRouteParameterName($tenancy))
        ) {
            // Use the route to resolve the identity from the parameter
            $identity = $resolver->resolveFromRoute($event->route, $tenancy, $event->request);
            $event->route->forgetParameter($resolver->getRouteParameterName($tenancy));
        } else {
            // If we reach here, either the resolver doesn't use parameters, or
            // the parameter isn't present in the URL, so we'll default to
            // using the request
            $identity = $resolver->resolveFromRequest($event->request, $tenancy);
        }

        // Make sure the tenancy knows which resolver resolved it
        $tenancy->resolvedVia($resolver);

        if ($identity === null || ! $tenancy->identify($identity)) {
            throw NoTenantFound::make($resolver->getName(), $tenancy->getName());
        }

        return;
    }

    /**
     * @param \Illuminate\Routing\Route $route
     *
     * @return array<int, string|null>|null
     */
    private function parseTenantMiddleware(Route $route): ?array
    {
        foreach (Arr::wrap($route->middleware()) as $item) {
            if ($item === TenantRoutes::ALIAS || Str::startsWith($item, TenantRoutes::ALIAS . ':')) {
                return array_merge(
                    [null, null],
                    explode(',', Str::after($item, ':'))
                );
            }
        }

        return null;
    }
}
