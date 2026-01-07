<?php
declare(strict_types=1);

namespace Sprout\Http\Resolvers;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Routing\RouteRegistrar;
use Sprout\Contracts\Tenancy;
use Sprout\Http\Middleware\AddTenantHeaderToResponse;
use Sprout\Http\Middleware\SproutTenantContextMiddleware;
use Sprout\Support\BaseIdentityResolver;
use Sprout\Support\PlaceholderHelper;

/**
 * Header Identity Resolver
 *
 * This class is responsible for resolving tenant identities from the current
 * request using headers.
 *
 * @package Http\Resolvers
 */
final class HeaderIdentityResolver extends BaseIdentityResolver
{
    /**
     * The header name
     *
     * @var string
     */
    private string $header;

    /**
     * Create a new instance
     *
     * @param string                                $name
     * @param string|null                           $header
     * @param array<\Sprout\Support\ResolutionHook> $hooks
     */
    public function __construct(string $name, ?string $header = null, array $hooks = [])
    {
        parent::__construct($name, $hooks);

        $this->header = $header ?? '{Tenancy}-Identifier';
    }

    /**
     * Get the name of the header
     *
     * @return string
     */
    public function getHeaderName(): string
    {
        return $this->header;
    }

    /**
     * Get the header name with replacements
     *
     * This method returns the name of the header returned by
     * {@see self::getHeaderName()}, except it replaces <code>{tenancy}</code>
     * and <code>{resolver}</code> with the name of the tenancy, and resolver,
     * respectively.
     *
     * You can use an uppercase character for the first character, <code>{Tenancy}</code>
     * and <code>{Resolver}</code>, and it'll be run through {@see \ucfirst()}.
     *
     * @param \Sprout\Contracts\Tenancy<*> $tenancy
     *
     * @return string
     */
    public function getRequestHeaderName(Tenancy $tenancy): string
    {
        return PlaceholderHelper::replace(
            $this->getHeaderName(),
            [
                'tenancy'  => $tenancy->getName(),
                'resolver' => $this->getName(),
            ]
        );
    }

    /**
     * Get an identifier from the request
     *
     * Locates a tenant identifier within the provided request and returns it.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Illuminate\Http\Request               $request
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     *
     * @return string|null
     */
    public function resolveFromRequest(Request $request, Tenancy $tenancy): ?string
    {
        return $request->header($this->getRequestHeaderName($tenancy));
    }

    /**
     * Configure the provided route for the resolver
     *
     * Configures a provided route to work with itself, adding parameters,
     * middleware, and anything else required, besides the default middleware.
     *
     * @param \Illuminate\Routing\RouteRegistrar                  $route
     * @param \Sprout\Contracts\Tenancy<\Sprout\Contracts\Tenant> $tenancy
     *
     * @return void
     */
    public function configureRoute(RouteRegistrar $route, Tenancy $tenancy): void
    {
        $route->middleware([
            AddTenantHeaderToResponse::class . ':' . $this->getName() . ',' . $tenancy->getName(),
        ]);
    }
}
