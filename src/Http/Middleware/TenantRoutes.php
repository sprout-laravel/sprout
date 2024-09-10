<?php
declare(strict_types=1);

namespace Sprout\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Sprout\Contracts\IdentityResolverTerminates;
use Sprout\Exceptions\NoTenantFound;
use Sprout\Managers\IdentityResolverManager;
use Sprout\Managers\TenancyManager;
use Sprout\Sprout;
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
     * @var \Sprout\Sprout
     */
    private Sprout $sprout;

    public function __construct(Sprout $sprout)
    {
        $this->sprout = $sprout;
    }

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
        if (count($options) === 2) {
            [$resolverName, $tenancyName] = $options;
        } else if (count($options) === 1) {
            [$resolverName] = $options;
            $tenancyName = null;
        } else {
            $resolverName = $tenancyName = null;
        }

        ResolutionHelper::handleResolution(
            $request,
            ResolutionHook::Middleware,
            $resolverName,
            $tenancyName
        );

        // TODO: Decide whether to do anything with the following conditions
        //if (! $tenancy->wasResolved()) {
        //}
        //
        //if ($tenancy->resolver() !== $resolver) {
        //}

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($this->sprout->hasCurrentTenancy()) {
            /** @var \Sprout\Contracts\Tenancy<\Sprout\Contracts\Tenant> $tenancy */
            $tenancy = $this->sprout->getCurrentTenancy();

            if ($tenancy->wasResolved()) {
                $resolver = $tenancy->resolver();

                if ($resolver instanceof IdentityResolverTerminates) {
                    $resolver->terminate($tenancy, $response);
                }
            }
        }
    }
}
