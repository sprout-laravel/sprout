<?php
declare(strict_types=1);

namespace Sprout\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sprout\Core\Sprout;
use Sprout\Core\Support\ResolutionHelper;
use Sprout\Core\Support\ResolutionHook;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprout Optional Tenant Context Middleware
 *
 * This piece of middleware has a dual function.
 * It marks routes as being multitenanted if resolving during routing, and it
 * will resolve tenants if resolving during middleware.
 * Unlike {@see \Sprout\Core\Http\Middleware\SproutTenantContextMiddleware}, no
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
     * @var \Sprout\Core\Sprout
     */
    private Sprout $sprout;

    /**
     * Create a new instance of the middleware
     *
     * @param \Sprout\Core\Sprout $sprout
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
     * @throws \Sprout\Core\Exceptions\NoTenantFoundException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Core\Exceptions\MisconfigurationException
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

        /** @phpstan-ignore return.type */
        return $next($request);
    }
}
