<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Http;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Http\Middleware\SproutOptionalTenantContextMiddleware;
use Sprout\Tests\Unit\UnitTestCase;

class RouterMethodsTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.defaults.resolver', 'header');
        });
    }

    protected function defineRoutes($router): void
    {
        $router->possiblyTenanted(function (Router $router) {
            $router->get('/possibly-tenanted-test', static fn () => 'ok')
                   ->name('possibly-tenanted-test');
        }, 'header');
    }

    #[Test]
    public function possiblyTenantedRegistersTheOptionalMiddleware(): void
    {
        $route = Route::getRoutes()->getByName('possibly-tenanted-test');

        $this->assertNotNull($route);

        $this->assertContains(
            SproutOptionalTenantContextMiddleware::ALIAS . ':header,tenants',
            $route->middleware(),
        );
    }
}
