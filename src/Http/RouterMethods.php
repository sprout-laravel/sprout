<?php
declare(strict_types=1);

namespace Sprout\Http;

use Closure;
use Illuminate\Routing\RouteRegistrar;
use Sprout\Managers\IdentityResolverManager;
use Sprout\Managers\TenancyManager;

class RouterMethods
{
    /**
     * @param Closure     $routes
     * @param string|null $resolver
     * @param string|null $tenancy
     *
     * @return \Illuminate\Routing\RouteRegistrar
     *
     * @noinspection   PhpDocSignatureInspection
     *
     * @phpstan-ignore parameter.notFound,parameter.notFound,parameter.notFound,return.phpDocType
     */
    public function tenanted(): Closure
    {
        return function (Closure $routes, ?string $resolver = null, ?string $tenancy = null): RouteRegistrar {
            return app()->make(IdentityResolverManager::class)
                        ->get($resolver)
                        ->routes(
                            $this, // @phpstan-ignore-line
                            $routes,
                            app()->make(TenancyManager::class)->get($tenancy)
                        );
        };
    }
}
