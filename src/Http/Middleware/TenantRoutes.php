<?php
declare(strict_types=1);

namespace Sprout\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sprout\Sprout;
use Sprout\Support\ResolutionHelper;
use Sprout\Support\ResolutionHook;
use Symfony\Component\HttpFoundation\Response;

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
     * @var \Sprout\Sprout
     */
    private Sprout $sprout;

    /**
     * Create a new instance of the middleware
     *
     * @param \Sprout\Sprout $sprout
     */
    public function __construct(Sprout $sprout)
    {
        $this->sprout = $sprout;
    }

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
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Exceptions\MisconfigurationException
     */
    public function handle(Request $request, Closure $next, string ...$options): Response
    {
        if ($this->sprout->supportsHook(ResolutionHook::Middleware)) {
            [$resolverName, $tenancyName] = ResolutionHelper::parseOptions($options);

            ResolutionHelper::handleResolution(
                $request,
                ResolutionHook::Middleware,
                $resolverName,
                $tenancyName,
            );
        }

        return $next($request);
    }
}
