<?php
declare(strict_types=1);

namespace Sprout\Listeners;

use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Sprout\Http\Middleware\TenantRoutes;
use Sprout\Managers\IdentityResolverManager;
use Sprout\Managers\TenancyManager;
use Sprout\Sprout;
use Sprout\Support\ResolutionHelper;
use Sprout\Support\ResolutionHook;

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

        ResolutionHelper::handleResolution(
            $event->request,
            ResolutionHook::Routing,
            $resolverName,
            $tenancyName,
            false
        );
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
                if (! Str::startsWith($item, TenantRoutes::ALIAS . ':')) {
                    return [null, null];
                }

                if (Str::contains($item, ',')) {
                    return explode(',', Str::after($item, ':'), 2);
                }

                return [
                    Str::after($item, ':'),
                    null,
                ];
            }
        }

        return null;
    }
}
