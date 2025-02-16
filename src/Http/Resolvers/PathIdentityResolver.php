<?php
declare(strict_types=1);

namespace Sprout\Http\Resolvers;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Routing\RouteRegistrar;
use Sprout\Concerns\FindsIdentityInRouteParameter;
use Sprout\Contracts\IdentityResolverUsesParameters;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Exceptions\TenantMissingException;
use Sprout\Http\Middleware\TenantRoutes;
use Sprout\Support\BaseIdentityResolver;

/**
 * Path Identity Resolver
 *
 * This class is responsible for resolving tenant identities from the current
 * request using the path.
 *
 * @package Http\Resolvers
 */
final class PathIdentityResolver extends BaseIdentityResolver implements IdentityResolverUsesParameters
{
    use FindsIdentityInRouteParameter {
        setup as parameterSetup;
    }

    /**
     * The path segment containing the identifier
     *
     * @var int
     */
    private int $segment = 1;

    /**
     * Create a new instance
     *
     * @param string                                $name
     * @param int|null                              $segment
     * @param string|null                           $pattern
     * @param string|null                           $parameter
     * @param array<\Sprout\Support\ResolutionHook> $hooks
     */
    public function __construct(string $name, ?int $segment = null, ?string $pattern = null, ?string $parameter = null, array $hooks = [])
    {
        parent::__construct($name, $hooks);

        if ($segment !== null) {
            $this->segment = max(1, $segment);
        }

        $this->initialiseRouteParameter($pattern, $parameter);
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
        return $request->segment($this->getSegment());
    }

    /**
     * Get the path segment
     *
     * @return int
     */
    public function getSegment(): int
    {
        return $this->segment;
    }

    /**
     * Create a route group for the resolver
     *
     * Creates and configures a route group with the necessary settings to
     * support identity resolution.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Illuminate\Routing\Router             $router
     * @param \Closure                               $groupRoutes
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     *
     * @return \Illuminate\Routing\RouteRegistrar
     */
    public function routes(Router $router, Closure $groupRoutes, Tenancy $tenancy): RouteRegistrar
    {
        return $this->applyParameterPatternMapping(
            $router->middleware([TenantRoutes::ALIAS . ':' . $this->getName() . ',' . $tenancy->getName()])
                   ->prefix($this->getRoutePrefix($tenancy)),
            $tenancy
        )->group($groupRoutes);
    }

    /**
     * Get the route prefix including the tenant parameter
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     *
     * @return string
     */
    public function getRoutePrefix(Tenancy $tenancy): string
    {
        return $this->getRouteParameter($tenancy);
    }

    /**
     * Get the route prefix with the parameter replaced with the tenant identifier
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     *
     * @return string
     *
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    public function getTenantRoutePrefix(Tenancy $tenancy): string
    {
        if (! $tenancy->check()) {
            throw TenantMissingException::make($tenancy->getName()); // @codeCoverageIgnore
        }

        /** @var string $identifier */
        $identifier = $tenancy->identifier();

        return str_replace(
            '{' . $this->getRouteParameterName($tenancy) . '}',
            $identifier,
            $this->getRoutePrefix($tenancy)
        );
    }

    /**
     * Perform setup actions for the tenant
     *
     * When a tenant is marked as the current tenant within a tenancy, this
     * method will be called to perform any necessary setup actions.
     * This method is also called if there is no current tenant, as there may
     * be actions needed.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Contracts\Tenant|null          $tenant
     *
     * @phpstan-param TenantClass|null               $tenant
     *
     * @return void
     *
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    public function setup(Tenancy $tenancy, ?Tenant $tenant): void
    {
        // Call the parent implementation in case there's something there
        parent::setup($tenancy, $tenant);

        // Call the trait setup so that parameter has a default value
        $this->parameterSetup($tenancy, $tenant);

        if ($tenant !== null) {
            $this->getSprout()->settings()->setUrlPath($this->getTenantRoutePrefix($tenancy));
        }
    }
}
