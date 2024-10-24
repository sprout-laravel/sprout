<?php
declare(strict_types=1);

namespace Sprout\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Sprout\Support\ResolutionHelper;
use Sprout\Support\ResolutionHook;

/**
 * Tenant Routes Middleware
 *
 * This piece of middleware has a dual function.
 * It marks routes as being multitenanted if resolving during routing, and it
 * will resolve tenants if resolving during middleware.
 *
 * @package Core
 */
final class TenantRoutes
{
    /**
     * The alias for this middleware
     */
    public const ALIAS = 'sprout.tenanted';

    /**
     * Handle the request
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     * @param string                   ...$options
     *
     * @return \Illuminate\Http\Response
     *
     * @throws \Sprout\Exceptions\NoTenantFound
     */
    public function handle(Request $request, Closure $next, string ...$options): Response
    {
        [$resolverName, $tenancyName] = ResolutionHelper::parseOptions($options);

        ResolutionHelper::handleResolution(
            $request,
            ResolutionHook::Middleware,
            $resolverName,
            $tenancyName,
        );

        return $next($request);
    }
}
