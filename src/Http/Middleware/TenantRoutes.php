<?php
declare(strict_types=1);

namespace Sprout\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Sprout\Exceptions\NoTenantFound;
use Sprout\Managers\IdentityResolverManager;
use Sprout\Managers\TenancyManager;

/**
 * Tenant Routes Middleware
 *
 * Marks routes are being tenanted.
 */
final class TenantRoutes
{
    public const ALIAS = 'sprout.tenanted';

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

        $resolver = $this->resolverManager->get($resolverName);
        $tenancy  = $this->tenancyManager->get($tenancyName);

        /**
         * @var \Sprout\Contracts\IdentityResolver                  $resolver
         * @var \Sprout\Contracts\Tenancy<\Sprout\Contracts\Tenant> $tenancy
         */

        if (! $tenancy->check()) {
            throw NoTenantFound::make($resolver->getName(), $tenancy->getName());
        }

        // TODO: Decide whether to do anything with the following conditions
        //if (! $tenancy->wasResolved()) {
        //}
        //
        //if ($tenancy->resolver() !== $resolver) {
        //}

        return $next($request);
    }
}
