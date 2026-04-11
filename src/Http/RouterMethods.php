<?php
declare(strict_types=1);

namespace Sprout\Http;

use Closure;
use Illuminate\Routing\Router;
use Illuminate\Routing\RouteRegistrar;

/**
 * Route Methods Mixin
 *
 * This class is used as a mixin to add Sprout specific methods to
 * {@see Router}.
 */
class RouterMethods
{
    /**
     * Create tenanted routes
     *
     * @param Closure     $routes
     * @param string|null $resolver
     * @param string|null $tenancy
     *
     * @return RouteRegistrar
     *
     * @noinspection   PhpDocSignatureInspection
     *
     * @phpstan-ignore parameter.notFound,parameter.notFound,parameter.notFound,return.phpDocType
     */
    public function tenanted(): Closure
    {
        return function (Closure $routes, ?string $resolver = null, ?string $tenancy = null): RouteRegistrar {
            return RouteCreator::create($routes, $resolver, $tenancy);
        };
    }

    /**
     * Create possibly tenanted routes
     *
     * @param Closure     $routes
     * @param string|null $resolver
     * @param string|null $tenancy
     *
     * @return RouteRegistrar
     *
     * @noinspection   PhpDocSignatureInspection
     *
     * @phpstan-ignore parameter.notFound,parameter.notFound,parameter.notFound,return.phpDocType
     */
    public function possiblyTenanted(): Closure
    {
        return function (Closure $routes, ?string $resolver = null, ?string $tenancy = null): RouteRegistrar {
            return RouteCreator::create($routes, $resolver, $tenancy, true);
        };
    }
}
