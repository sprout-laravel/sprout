<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Http\Resolvers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Http\Middleware\AddTenantHeaderToResponse;
use Sprout\Http\Resolvers\HeaderIdentityResolver;
use Sprout\Support\ResolutionHook;
use Sprout\Tests\Unit\UnitTestCase;
use function Sprout\resolver;
use function Sprout\tenancy;

class HeaderIdentityResolverTest extends UnitTestCase
{
    protected function withCustomHeaderName(Application $app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.resolvers.header.header', 'Custom-Header-Name');
        });
    }

    protected function defineRoutes($router)
    {
        $router->tenanted(function () {
            Route::get('/tenant', function () {
            })->name('tenant-route');
        }, 'header');
    }

    protected function withCustomOptions(Application $app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.resolvers.header.options', [
                'httpOnly' => true,
                'secure'   => true,
                'sameSite' => true,
            ]);
        });
    }

    protected function withCustomHeaderNamePattern(Application $app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.resolvers.header.header', '{Tenancy}-{tenancy}-{Resolver}-{resolver}');
        });
    }

    #[Test]
    public function isRegisteredAndCanBeAccessed(): void
    {
        $resolver = resolver('header');

        $this->assertInstanceOf(HeaderIdentityResolver::class, $resolver);
        $this->assertSame('{Tenancy}-Identifier', $resolver->getHeaderName());
        $this->assertSame([ResolutionHook::Routing], $resolver->getHooks());
    }

    #[Test]
    public function replacesPlaceholdersInHeaderName(): void
    {
        $resolver = resolver('header');
        $tenancy  = tenancy();

        $this->assertInstanceOf(HeaderIdentityResolver::class, $resolver);
        $this->assertSame('{Tenancy}-Identifier', $resolver->getHeaderName());
        $this->assertSame(ucfirst($tenancy->getName()) . '-Identifier', $resolver->getRequestHeaderName($tenancy));
    }

    #[Test, DefineEnvironment('withCustomHeaderName')]
    public function acceptsCustomHeaderName(): void
    {
        $resolver = resolver('header');

        $this->assertInstanceOf(HeaderIdentityResolver::class, $resolver);
        $this->assertNotSame('{Tenancy}-Identifier', $resolver->getHeaderName());
        $this->assertSame('Custom-Header-Name', $resolver->getHeaderName());
        $this->assertSame([ResolutionHook::Routing], $resolver->getHooks());
    }

    #[Test, DefineEnvironment('withCustomHeaderName')]
    public function replacesAllPlaceholders(): void
    {
        $resolver = resolver('header');

        $this->assertInstanceOf(HeaderIdentityResolver::class, $resolver);
        $this->assertNotSame('{Tenancy}-Identifier', $resolver->getHeaderName());
        $this->assertSame('Custom-Header-Name', $resolver->getHeaderName());
        $this->assertSame([ResolutionHook::Routing], $resolver->getHooks());
    }

    #[Test, DefineEnvironment('withCustomHeaderNamePattern')]
    public function acceptsCustomOptions(): void
    {
        $resolver = resolver('header');
        $tenancy  = tenancy();

        $this->assertInstanceOf(HeaderIdentityResolver::class, $resolver);
        $this->assertSame('{Tenancy}-{tenancy}-{Resolver}-{resolver}', $resolver->getHeaderName());
        $this->assertSame(
            ucfirst($tenancy->getName()) . '-' . $tenancy->getName() . '-' . ucfirst($resolver->getName()) . '-' . $resolver->getName(),
            $resolver->getRequestHeaderName($tenancy)
        );
        $this->assertSame([ResolutionHook::Routing], $resolver->getHooks());
    }

    #[Test]
    public function addsTenantHeaderResponseMiddlewareToRoutes(): void
    {
        $resolver = resolver('header');
        $tenancy  = tenancy();
        $routes   = app(Router::class)->getRoutes();

        $this->assertTrue($routes->hasNamedRoute('tenant-route'));

        $middleware = $routes->getByName('tenant-route')->middleware();

        $this->assertContains(AddTenantHeaderToResponse::class . ':' . $resolver->getName() . ',' . $tenancy->getName(), $middleware);
    }
}
