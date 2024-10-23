<?php
declare(strict_types=1);

namespace Sprout\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Sprout\Http\Resolvers\HeaderIdentityResolver;
use Sprout\Sprout;
use Sprout\Support\ResolutionHelper;
use Sprout\Support\ResolutionHook;

final class AddTenantHeaderToResponse
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

        /** @var \Illuminate\Http\Response $response */
        $response = $next($request);

        /** @var \Sprout\Contracts\Tenancy<*> $tenancy */
        $tenancy = $this->sprout->tenancies()->get($tenancyName);

        if (! $tenancy->check()) {
            return $response;
        }

        $resolver = $tenancy->resolver();

        if (! ($resolver instanceof HeaderIdentityResolver) || $resolver->getName() !== $resolverName) {
            return $response;
        }

        return $response->withHeaders([
            $resolver->getRequestHeaderName($tenancy) => $tenancy->identifier()
        ]);
    }
}
