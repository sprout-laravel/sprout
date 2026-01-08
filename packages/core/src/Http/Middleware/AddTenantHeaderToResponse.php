<?php
declare(strict_types=1);

namespace Sprout\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sprout\Core\Http\Resolvers\HeaderIdentityResolver;
use Sprout\Core\Sprout;
use Sprout\Core\Support\ResolutionHelper;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add Tenant Header to Response
 *
 * This piece of middleware is responsible for adding the tenant identifier
 * header to responses when using the header-based identity resolver.
 *
 * @package Http\Resolvers
 */
final class AddTenantHeaderToResponse
{
    /**
     * @var \Sprout\Core\Sprout
     */
    private Sprout $sprout;

    /**
     * Create new instance
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
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Core\Exceptions\MisconfigurationException
     */
    public function handle(Request $request, Closure $next, string ...$options): Response
    {
        [$resolverName, $tenancyName] = ResolutionHelper::parseOptions($options);

        /** @var \Illuminate\Http\Response $response */
        $response = $next($request);

        /** @var \Sprout\Core\Contracts\Tenancy<*> $tenancy */
        $tenancy = $this->sprout->tenancies()->get($tenancyName);

        if (! $tenancy->check()) {
            return $response;
        }

        $resolver = $tenancy->resolver();

        if (! ($resolver instanceof HeaderIdentityResolver) || $resolver->getName() !== $resolverName) {
            return $response;
        }

        return $response->withHeaders([
            $resolver->getRequestHeaderName($tenancy) => $tenancy->identifier(),
        ]);
    }
}
