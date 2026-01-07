<?php
declare(strict_types=1);

namespace Sprout\Listeners;

use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Sprout\Http\Middleware\SproutOptionalTenantContextMiddleware;
use Sprout\Http\Middleware\SproutTenantContextMiddleware;
use Sprout\Sprout;
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
     * @var \Sprout\Sprout
     */
    private Sprout $sprout;

    public function __construct(Sprout $sprout)
    {
        $this->sprout = $sprout;
    }

    /**
     * Handle the event
     *
     * @param \Illuminate\Routing\Events\RouteMatched $event
     *
     * @return void
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Exceptions\MisconfigurationException
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
            $this->sprout,
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
     *
     * @codeCoverageIgnore
     */
    private function parseTenantMiddleware(Route $route): ?array
    {
        $middleware = null;
        $found      = false;

        /** @var string $item */
        foreach (Arr::wrap($route->middleware()) as $item) {
            // If it's the normal middleware, we'll get that
            if (
                $item === SproutTenantContextMiddleware::ALIAS
                || Str::startsWith($item, SproutTenantContextMiddleware::ALIAS . ':')
            ) {
                $middleware = Str::trim(Str::after($item, SproutTenantContextMiddleware::ALIAS), ':');
                $found      = true;
                break;
            }

            // If it's the optional middleware, we'll get that
            if (
                $item === SproutOptionalTenantContextMiddleware::ALIAS
                || Str::startsWith($item, SproutOptionalTenantContextMiddleware::ALIAS . ':')
            ) {
                $middleware = Str::trim(Str::after($item, SproutOptionalTenantContextMiddleware::ALIAS), ':');
                $found      = true;
                break;
            }
        }

        if ($found === true) {
            if (empty($middleware)) {
                return [null, null];
            }

            if (Str::contains($middleware, ',')) {
                return explode(',', Str::after($middleware, ':'), 2);
            }

            return [
                $middleware,
                null,
            ];
        }

        return null;
    }
}
