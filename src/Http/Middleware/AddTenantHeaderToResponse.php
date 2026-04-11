<?php
declare(strict_types=1);

namespace Sprout\Http\Middleware;

use Closure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Http\Resolvers\HeaderIdentityResolver;
use Sprout\Sprout;
use Sprout\Support\ResolutionHelper;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add Tenant Header to Response
 *
 * This piece of middleware is responsible for adding the tenant identifier
 * header to responses when using the header-based identity resolver.
 */
final class AddTenantHeaderToResponse
{
    /**
     * @var Sprout
     */
    private Sprout $sprout;

    /**
     * Create new instance
     *
     * @param Sprout $sprout
     */
    public function __construct(Sprout $sprout)
    {
        $this->sprout = $sprout;
    }

    /**
     * Handle the request
     *
     * @param Request $request
     * @param Closure $next
     * @param string  ...$options
     *
     * @return Response
     *
     * @throws BindingResolutionException
     * @throws MisconfigurationException
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
            $resolver->getRequestHeaderName($tenancy) => $tenancy->identifier(),
        ]);
    }
}
