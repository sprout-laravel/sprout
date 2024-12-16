<?php
declare(strict_types=1);

namespace Sprout\Listeners;

use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Sprout\Http\Middleware\TenantRoutes;
use Sprout\Support\ResolutionHelper;
use Sprout\Support\ResolutionHook;

/**
 * Identify Tenant on Routing
 *
 * This class is an event listener for {@see \Illuminate\Routing\Events\RouteMatched}
 * that handles tenant identification if it's enabled.
 *
 * @package Core
 */
final class IdentifyTenantOnRouting
{
    /**
     * Handle the event
     *
     * @param \Illuminate\Routing\Events\RouteMatched $event
     *
     * @return void
     *
     * @throws \Sprout\Exceptions\NoTenantFoundException
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
     * Parse the route middleware stack to find the marker middleware
     *
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
