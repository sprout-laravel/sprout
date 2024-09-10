<?php
declare(strict_types=1);

namespace Sprout\Http\Resolvers;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Router;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Facades\Cookie;
use Sprout\Contracts\IdentityResolverTerminates;
use Sprout\Contracts\Tenancy;
use Sprout\Http\Middleware\TenantRoutes;
use Sprout\Support\BaseIdentityResolver;

final class CookieIdentityResolver extends BaseIdentityResolver implements IdentityResolverTerminates
{
    private string $cookie;

    /**
     * @var array<string, mixed>
     */
    private array $options;

    /**
     * @param string                                $name
     * @param string|null                           $cookie
     * @param array<string, mixed>                  $options
     * @param array<\Sprout\Support\ResolutionHook> $hooks
     */
    public function __construct(string $name, ?string $cookie = null, array $options = [], array $hooks = [])
    {
        parent::__construct($name, $hooks);

        $this->cookie  = $cookie ?? '{Tenancy}-Identifier';
        $this->options = $options;
    }

    public function getCookie(): string
    {
        return $this->cookie;
    }

    /**
     * @param \Sprout\Contracts\Tenancy<\Sprout\Contracts\Tenant> $tenancy
     *
     * @return string
     */
    public function getRequestCookieName(Tenancy $tenancy): string
    {
        return str_replace(
            ['{tenancy}', '{resolver}', '{Tenancy}', '{Resolver}'],
            [$tenancy->getName(), $this->getName(), ucfirst($tenancy->getName()), ucfirst($this->getName())],
            $this->getCookie()
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
        /**
         * This is unfortunately here because of the ludicrous return type
         *
         * @var string|null $cookie
         */
        $cookie = $request->cookie($this->getRequestCookieName($tenancy));

        return $cookie;
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
        return $router->middleware([TenantRoutes::ALIAS . ':' . $this->getName() . ',' . $tenancy->getName()])
                      ->group($groupRoutes);
    }

    /**
     * @param \Sprout\Contracts\Tenancy<\Sprout\Contracts\Tenant> $tenancy
     * @param \Illuminate\Http\Response                           $response
     *
     * @return void
     */
    public function terminate(Tenancy $tenancy, Response $response): void
    {
        if ($tenancy->check()) {
            /**
             * @var array{name:string, value:string} $details
             */
            $details = $this->getCookieDetails(
                [
                    'name'  => $this->getRequestCookieName($tenancy),
                    'value' => $tenancy->identifier(),
                ]
            );

            $response->withCookie(Cookie::make(...$details));
        }
    }

    /**
     * @param array<string, mixed> $details
     *
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    private function getCookieDetails(array $details): array
    {
        if (isset($this->options['minutes'])) {
            $details['minutes'] = $this->options['minutes'];
        }

        if (isset($this->options['path'])) {
            $details['path'] = $this->options['path'];
        }

        if (isset($this->options['domain'])) {
            $details['domain'] = $this->options['domain'];
        }

        if (isset($this->options['secure'])) {
            $details['secure'] = $this->options['secure'];
        }

        if (isset($this->options['httpOnly'])) {
            $details['httpOnly'] = $this->options['httpOnly'];
        }

        if (isset($this->options['sameSite'])) {
            $details['sameSite'] = $this->options['sameSite'];
        }

        return $details;
    }
}
