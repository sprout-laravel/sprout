<?php
declare(strict_types=1);

namespace Sprout\Http;

use Closure;
use Illuminate\Routing\Router;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Facades\Route;
use Sprout\Contracts\IdentityResolverUsesParameters;
use Sprout\Exceptions\CompatibilityException;
use Sprout\Http\Middleware\SproutOptionalTenantContextMiddleware;
use Sprout\Http\Middleware\SproutTenantContextMiddleware;
use Sprout\Managers\IdentityResolverManager;
use Sprout\Managers\TenancyManager;

final class RouteCreator
{
    public static function create(Closure $routes, ?string $resolver = null, ?string $tenancy = null, bool $optional = false): RouteRegistrar
    {
        // Get the resolver instance first
        $resolverInstance = app()->make(IdentityResolverManager::class)->get($resolver);

        if ($optional && $resolverInstance instanceof IdentityResolverUsesParameters) {
            throw CompatibilityException::optionalMiddleware($resolverInstance->getName());
        }

        $tenancyInstance = app()->make(TenancyManager::class)->get($tenancy);
        $middleware      = $optional ? SproutOptionalTenantContextMiddleware::ALIAS : SproutTenantContextMiddleware::ALIAS;
        $options         = [$resolverInstance->getName(), $tenancyInstance->getName()];

        return Route::middleware([$middleware . ':' . implode(',', $options)])
                    ->group(function (Router $router) use ($routes, $resolverInstance, $tenancyInstance) {
                        $registrar = new RouteRegistrar($router);

                        $resolverInstance->configureRoute($registrar, $tenancyInstance);

                        $registrar->group($routes);
                    });
    }
}
