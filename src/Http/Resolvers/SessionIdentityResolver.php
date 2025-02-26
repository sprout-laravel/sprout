<?php
declare(strict_types=1);

namespace Sprout\Http\Resolvers;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Routing\RouteRegistrar;
use Sprout\Contracts\Tenancy;
use Sprout\Exceptions\CompatibilityException;
use Sprout\Http\Middleware\SproutTenantContextMiddleware;
use Sprout\Support\BaseIdentityResolver;
use Sprout\Support\PlaceholderHelper;
use Sprout\Support\ResolutionHook;
use Sprout\TenancyOptions;

/**
 * Session Identity Resolver
 *
 * This class is responsible for resolving tenant identities from the current
 * request using the session.
 *
 * @package Http\Resolvers
 */
final class SessionIdentityResolver extends BaseIdentityResolver
{
    /**
     * The name of the session
     *
     * @var string
     */
    private string $session;

    /**
     * Create a new instance
     *
     * @param string      $name
     * @param string|null $session
     */
    public function __construct(string $name, ?string $session = null)
    {
        parent::__construct($name, [ResolutionHook::Middleware]);

        $this->session = $session ?? 'multitenancy.{tenancy}';
    }

    /**
     * Get the name of the session
     *
     * @return string
     */
    public function getSessionName(): string
    {
        return $this->session;
    }

    /**
     * Get the session name with replacements
     *
     * This method returns the name of the header returned by
     * {@see self::getSessionName()}, except it replaces <code>{tenancy}</code>
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
    public function getRequestSessionName(Tenancy $tenancy): string
    {
        return PlaceholderHelper::replace(
            $this->getSessionName(),
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
     *
     * @throws \Sprout\Exceptions\CompatibilityException
     */
    public function resolveFromRequest(Request $request, Tenancy $tenancy): ?string
    {
        if (TenancyOptions::shouldEnableOverride($tenancy, 'session')) {
            throw CompatibilityException::make('resolver', $this->getName(), 'service override', 'session');
        }

        /**
         * This is unfortunately here because of the ludicrous return type
         *
         * @var string|null $identity
         */
        $identity = $request->session()->get($this->getRequestSessionName($tenancy));

        return $identity;
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
     *
     * @deprecated Use {@see self::configureRoute()} instead
     */
    public function routes(Router $router, Closure $groupRoutes, Tenancy $tenancy): RouteRegistrar
    {
        return $router->middleware([SproutTenantContextMiddleware::ALIAS . ':' . $this->getName() . ',' . $tenancy->getName()])
                      ->group($groupRoutes);
    }

    /**
     * Can the resolver run on the request
     *
     * This method allows a resolver to prevent resolution with the request in
     * its current state, whether that means it's too early, or too late.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Illuminate\Http\Request               $request
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Support\ResolutionHook         $hook
     *
     * @return bool
     */
    public function canResolve(Request $request, Tenancy $tenancy, ResolutionHook $hook): bool
    {
        return ! $tenancy->wasResolved() && $request->hasSession() && $hook === ResolutionHook::Middleware;
    }
}
