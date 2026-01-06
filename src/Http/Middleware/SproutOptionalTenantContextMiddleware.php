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
 * Sprout Optional Tenant Context Middleware
 *
 * This piece of middleware has a dual function.
 * It marks routes as being multitenanted if resolving during routing, and it
 * will resolve tenants if resolving during middleware.
 * Unlike {@see \Sprout\Http\Middleware\SproutTenantContextMiddleware}, no
 * exception will be thrown if there's no tenant.
 *
 * @package Core
 */
final class SproutOptionalTenantContextMiddleware
{
    /**
     * The alias for this middleware
     */
    public const ALIAS = 'sprout.tenanted.optional';

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
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Sprout\Exceptions\NoTenantFoundException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Exceptions\MisconfigurationException
     */
    public function handle(Request $request, Closure $next, string ...$options): Response
    {
        [$resolverName, $tenancyName] = ResolutionHelper::parseOptions($options);

        if ($this->sprout->supportsHook(ResolutionHook::Middleware)) {
            ResolutionHelper::handleResolution(
                $request,
                ResolutionHook::Middleware,
                $this->sprout,
                $resolverName,
                $tenancyName,
                false,
                true
            );
        }

        return $next($request);
    }
}
