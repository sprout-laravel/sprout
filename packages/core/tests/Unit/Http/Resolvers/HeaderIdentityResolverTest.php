<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Http\Resolvers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Routing\Router;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Http\Middleware\AddTenantHeaderToResponse;
use Sprout\Http\Resolvers\HeaderIdentityResolver;
use Sprout\Sprout;
use Sprout\Support\ResolutionHook;
use Sprout\Support\SettingsRepository;
use Sprout\Tests\Unit\UnitTestCase;

class HeaderIdentityResolverTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.defaults.resolver', 'header');
        });
    }

    protected function defineRoutes($router)
    {
        $router->tenanted(function (Router $router) {
            $router->get('/test-route', static function () {
                return 'test';
            })->name('test-route');
        });
    }

    protected function mockApp(): Application&MockInterface
    {
        return Mockery::mock(Application::class, static function ($mock) {

        });
    }

    protected function getSprout(Application $app): Sprout
    {
        return new Sprout($app, new SettingsRepository());
    }

    #[Test]
    public function providesAccessToExpectedValues(): void
    {
        $resolver = new HeaderIdentityResolver(
            'header',
            '{Tenancy}-Header-Identifier',
            [ResolutionHook::Middleware]
        );

        $this->assertSame('header', $resolver->getName());
        $this->assertSame('{Tenancy}-Header-Identifier', $resolver->getHeaderName());
        $this->assertSame([ResolutionHook::Middleware], $resolver->getHooks());
    }

    #[Test]
    public function hasSensibleDefaults(): void
    {
        $resolver = new HeaderIdentityResolver('header');

        $this->assertSame('header', $resolver->getName());
        $this->assertSame('{Tenancy}-Identifier', $resolver->getHeaderName());
        $this->assertSame([ResolutionHook::Routing], $resolver->getHooks());
    }

    #[Test]
    public function canGenerateTheHeaderNameForATenancy(): void
    {
        $resolver = new HeaderIdentityResolver('header');

        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->once();
        });

        $this->assertSame('My-tenancy-Identifier', $resolver->getRequestHeaderName($tenancy));
    }

    #[Test]
    public function configuresRoute(): void
    {
        $resolver = new HeaderIdentityResolver('header');

        $tenancy = Mockery::mock(Tenancy::class, static function ($mock) {
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->once();
        });

        /** @var \Illuminate\Routing\RouteRegistrar&\Mockery\MockInterface $route */
        $route = Mockery::mock(RouteRegistrar::class, static function (MockInterface $mock) {
            $mock->shouldReceive('middleware')
                 ->with([AddTenantHeaderToResponse::class . ':header,my-tenancy'])
                 ->andReturnSelf()
                 ->once();
        });

        $resolver->configureRoute($route, $tenancy);
    }

    #[Test]
    public function canResolveFromRequest(): void
    {
        $resolver = new HeaderIdentityResolver('header');

        /** @var \Sprout\Contracts\Tenancy&MockInterface $tenancy */
        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->once();
        });

        $request = Mockery::mock(Request::class, static function (MockInterface $mock) {
            $mock->shouldReceive('header')
                 ->with('My-tenancy-Identifier')
                 ->andReturn('my-identifier')
                 ->once();
        });

        $this->assertSame('my-identifier', $resolver->resolveFromRequest($request, $tenancy));
    }

    #[Test]
    public function reportsWhenItCanResolveCorrectly(): void
    {
        $resolver = new HeaderIdentityResolver('header', hooks: [ResolutionHook::Routing]);

        $request = Mockery::mock(Request::class);

        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('wasResolved')->andReturnFalse()->times(3);
        });

        $this->assertFalse($resolver->canResolve($request, $tenancy, ResolutionHook::Booting));
        $this->assertTrue($resolver->canResolve($request, $tenancy, ResolutionHook::Routing));
        $this->assertFalse($resolver->canResolve($request, $tenancy, ResolutionHook::Middleware));
    }

    #[Test]
    public function canGenerateRouteUrls(): void
    {
        $resolver = new HeaderIdentityResolver('header');

        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldNotReceive('getName');
        });

        $tenant1 = Mockery::mock(Tenant::class, static function (MockInterface $mock) {
            $mock->shouldNotReceive('getTenantIdentifier');
        });

        $tenant2 = Mockery::mock(Tenant::class, static function (MockInterface $mock) {
            $mock->shouldNotReceive('getTenantIdentifier');
        });

        $tenant3 = Mockery::mock(Tenant::class, static function (MockInterface $mock) {
            $mock->shouldNotReceive('getTenantIdentifier');
        });

        $this->assertSame('/test-route', $resolver->route('test-route', $tenancy, $tenant1, absolute: false));
        $this->assertSame('/test-route', $resolver->route('test-route', $tenancy, $tenant2, absolute: false));
        $this->assertSame('/test-route', $resolver->route('test-route', $tenancy, $tenant3, absolute: false));
    }
}
