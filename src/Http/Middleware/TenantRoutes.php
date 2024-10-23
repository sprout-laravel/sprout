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
 * Marks routes are being tenanted.
 */
final class TenantRoutes
{
    public const ALIAS = 'sprout.tenanted';

    /**
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
