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
use Sprout\Exceptions\TenantMissing;
use Sprout\Http\Middleware\TenantRoutes;
use Sprout\Overrides\CookieOverride;
use Sprout\Overrides\SessionOverride;
use Sprout\Support\BaseIdentityResolver;
use Sprout\Support\Settings;
use function Sprout\settings;

/**
 * The Subdomain Identity Resolver
 *
 * This class is responsible for resolving tenant identities from the current
 * request using a subdomain.
 *
 * @package Http\Resolvers
 */
final class SubdomainIdentityResolver extends BaseIdentityResolver implements IdentityResolverUsesParameters
{
    use FindsIdentityInRouteParameter {
        setup as parameterSetup;
    }

    /**
     * The parent domain
     *
     * @var string
     */
    private string $domain;

    /**
     * Create a new instance
     *
     * @param string                                $name
     * @param string                                $domain
     * @param string|null                           $pattern
     * @param string|null                           $parameter
     * @param array<\Sprout\Support\ResolutionHook> $hooks
     */
    public function __construct(string $name, string $domain, ?string $pattern = null, ?string $parameter = null, array $hooks = [])
    {
        parent::__construct($name, $hooks);

        $this->domain = $domain;

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
        $requestDomain = $request->getHost();

        if (($position = strpos($requestDomain, '.' . $this->domain)) !== false) {
            return substr($requestDomain, 0, $position);
        }

        return null;
    }

    /**
     * Get the domain the subdomains belong to
     *
     * @return string
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Get the domain name with parameter for the route definition
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     *
     * @return string
     */
    public function getRouteDomain(Tenancy $tenancy): string
    {
        return $this->getRouteParameter($tenancy) . '.' . $this->domain;
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
            $router->domain($this->getRouteDomain($tenancy))
                   ->middleware([TenantRoutes::ALIAS . ':' . $this->getName() . ',' . $tenancy->getName()]),
            $tenancy
        )->group($groupRoutes);
    }

    /**
     * Get the route domain with the parameter replaced with the tenant identifier
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     *
     * @return string
     *
     * @throws \Sprout\Exceptions\TenantMissing
     */
    public function getTenantRouteDomain(Tenancy $tenancy): string
    {
        if (! $tenancy->check()) {
            throw TenantMissing::make($tenancy->getName()); // @codeCoverageIgnore
        }

        /** @var string $identifier */
        $identifier = $tenancy->identifier();

        return str_replace(
            '{' . $this->getRouteParameterName($tenancy) . '}',
            $identifier,
            $this->getRouteDomain($tenancy)
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
     * @throws \Sprout\Exceptions\TenantMissing
     */
    public function setup(Tenancy $tenancy, ?Tenant $tenant): void
    {
        // Call the parent implementation in case there's something there
        parent::setup($tenancy, $tenant);

        // Call the trait setup so that parameter has a default value
        $this->parameterSetup($tenancy, $tenant);

        if ($tenant !== null) {
            settings()->setUrlDomain($this->getTenantRouteDomain($tenancy));
        }
    }
}
