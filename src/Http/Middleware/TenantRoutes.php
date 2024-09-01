<?php
declare(strict_types=1);

namespace Sprout\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Sprout\Managers\IdentityResolverManager;

final class TenantRoutes
{
    /**
     * @var \Sprout\Managers\IdentityResolverManager
     */
    private IdentityResolverManager $resolverManager;

    public function __construct(IdentityResolverManager $resolverManager)
    {
        $this->resolverManager = $resolverManager;
    }

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
        //$tenancy  = $this->tenancyManager->get($tenancyName);

        return $next($request);
    }
}
