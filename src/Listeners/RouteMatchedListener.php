<?php
declare(strict_types=1);

namespace Sprout\Listeners;

use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Sprout\Exceptions\NoTenantFound;
use Sprout\Http\Middleware\TenantRoutes;
use Sprout\Managers\IdentityResolverManager;
use Sprout\Managers\TenancyManager;

final class RouteMatchedListener
{
    /**
     * @var \Sprout\Managers\IdentityResolverManager
     */
    private IdentityResolverManager $resolverManager;

    /**
     * @var \Sprout\Managers\TenancyManager
     */
    private TenancyManager $tenancyManager;

    public function __construct(IdentityResolverManager $resolverManager, TenancyManager $tenancyManager)
    {
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

        /**
         * @var \Sprout\Contracts\IdentityResolver                  $resolver
         * @var \Sprout\Contracts\Tenancy<\Sprout\Contracts\Tenant> $tenancy
         */

        $identity = $resolver->resolveFromRoute($event->route, $tenancy, $event->request);

        $tenancy->resolvedVia($resolver);

        if ($identity === null || ! $tenancy->identify($identity)) {
            throw NoTenantFound::make($resolver->getName(), $tenancy->getName());
        }

        // TODO: Handle success
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
