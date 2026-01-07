<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Http\Resolvers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Facades\URL;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Http\Resolvers\PathIdentityResolver;
use Sprout\Sprout;
use Sprout\Support\ResolutionHook;
use Sprout\Support\SettingsRepository;
use Sprout\Tests\Unit\UnitTestCase;

class PathIdentityResolverTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.defaults.resolver', 'path');
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
        $resolver = new PathIdentityResolver(
            'path',
            6,
            '.*',
            'yeah-boi-{tenancy}',
            [ResolutionHook::Middleware]
        );

        $this->assertSame('path', $resolver->getName());
        $this->assertSame(6, $resolver->getSegment());
        $this->assertSame('.*', $resolver->getPattern());
        $this->assertSame('yeah-boi-{tenancy}', $resolver->getParameter());
        $this->assertSame([ResolutionHook::Middleware], $resolver->getHooks());
        $this->assertTrue($resolver->hasPattern());
    }

    #[Test]
    public function hasSensibleDefaults(): void
    {
        $resolver = new PathIdentityResolver('path');

        $this->assertSame('path', $resolver->getName());
        $this->assertSame(1, $resolver->getSegment());
        $this->assertNull($resolver->getPattern());
        $this->assertSame('{tenancy}_{resolver}', $resolver->getParameter());
        $this->assertSame([ResolutionHook::Routing], $resolver->getHooks());
        $this->assertFalse($resolver->hasPattern());
    }

    #[Test]
    public function canGenerateTheRoutePrefixForATenancy(): void
    {
        $resolver = new PathIdentityResolver('path');

        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->times(3);
            $mock->shouldReceive('check')->andReturn(true)->once();
            $mock->shouldReceive('identifier')->andReturn('my-identifier');
        });

        $this->assertSame('{my_tenancy_path}', $resolver->getRoutePrefix($tenancy));
        $this->assertSame('my-identifier', $resolver->getTenantRoutePrefix($tenancy));
    }

    #[Test]
    public function createsRouteGroup(): void
    {
        $resolver = new PathIdentityResolver('path');

        $tenancy = Mockery::mock(Tenancy::class, static function ($mock) {
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->twice();
        });

        $routes = static fn () => false;

        /** @var \Illuminate\Routing\Router&\Mockery\MockInterface $router */
        $router = Mockery::mock(Router::class, static function (MockInterface $mock) use ($routes) {
            $mock->shouldReceive('middleware')
                 ->with(['sprout.tenanted:path,my-tenancy'])
                 ->andReturn(
                     Mockery::mock(RouteRegistrar::class, static function (MockInterface $mock) use ($routes) {
                         $mock->shouldReceive('prefix')
                              ->with('{my_tenancy_path}')
                              ->andReturnSelf()
                              ->once();

                         $mock->shouldReceive('group')
                              ->with($routes)
                              ->andReturnSelf()
                              ->once();

                         $mock->shouldNotReceive('where');
                     })
                 )
                 ->once();
        });

        $resolver->routes($router, $routes, $tenancy);
    }

    #[Test]
    public function createsRouteGroupWithPattern(): void
    {
        $resolver = new PathIdentityResolver('path', pattern: '.*');

        $tenancy = Mockery::mock(Tenancy::class, static function ($mock) {
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->times(3);
        });

        $routes = static fn () => false;

        /** @var \Illuminate\Routing\Router&\Mockery\MockInterface $router */
        $router = Mockery::mock(Router::class, static function (MockInterface $mock) use ($routes) {
            $mock->shouldReceive('middleware')
                 ->with(['sprout.tenanted:path,my-tenancy'])
                 ->andReturn(
                     Mockery::mock(RouteRegistrar::class, static function (MockInterface $mock) use ($routes) {
                         $mock->shouldReceive('prefix')
                              ->with('{my_tenancy_path}')
                              ->andReturnSelf()
                              ->once();

                         $mock->shouldReceive('group')
                              ->with($routes)
                              ->andReturnSelf()
                              ->once();

                         $mock->shouldReceive('where')
                              ->with(['my_tenancy_path' => '.*'])
                              ->andReturnSelf()
                              ->once();
                     })
                 )
                 ->once();
        });

        $resolver->routes($router, $routes, $tenancy);
    }

    #[Test]
    public function performsSetUp(): void
    {
        $resolver = new PathIdentityResolver('path');

        $tenant = Mockery::mock(Tenant::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getTenantIdentifier')->andReturn('my-identifier')->once();
        });

        /** @var \Sprout\Contracts\Tenancy&MockInterface $tenancy */
        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->times(3);
            $mock->shouldReceive('check')->andReturn(true)->once();
            $mock->shouldReceive('identifier')->andReturn('my-identifier')->once();
        });

        $app = $this->mockApp();

        $sprout = $this->getSprout($app);

        $resolver->setApp($app)->setSprout($sprout);

        URL::shouldReceive('defaults')
           ->with(['my_tenancy_path' => 'my-identifier'])
           ->once();

        $resolver->setup($tenancy, $tenant);

        $this->assertSame('my-identifier', $sprout->settings()->getUrlPath());
    }

    #[Test]
    public function performsSetUpWithoutSettingsIfNoTenant(): void
    {
        $resolver = new PathIdentityResolver('path');

        /** @var \Sprout\Contracts\Tenancy&MockInterface $tenancy */
        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->once();
        });

        $app = $this->mockApp();

        $sprout = $this->getSprout($app);

        $resolver->setApp($app)->setSprout($sprout);

        URL::shouldReceive('defaults')
           ->with(['my_tenancy_path' => null])
           ->once();

        $resolver->setup($tenancy, null);

        $this->assertNull($sprout->settings()->getUrlPath());
    }

    #[Test]
    public function canResolveFromRequest(): void
    {
        $resolver = new PathIdentityResolver('path', 11);

        /** @var \Sprout\Contracts\Tenancy&MockInterface $tenancy */
        $tenancy = Mockery::mock(Tenancy::class);

        $request = Mockery::mock(Request::class, static function (MockInterface $mock) {
            $mock->shouldReceive('segment')
                 ->with(11)
                 ->andReturn('my-identifier')
                 ->once();
        });

        $this->assertSame('my-identifier', $resolver->resolveFromRequest($request, $tenancy));
    }

    #[Test]
    public function canResolveFromRoute(): void
    {
        $resolver = new PathIdentityResolver('path', 11);

        /** @var \Sprout\Contracts\Tenancy&MockInterface $tenancy */
        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->once();
        });

        $request = Mockery::mock(Request::class);

        $route = Mockery::mock(Route::class, static function (MockInterface $mock) {
            $mock->shouldReceive('hasParameter')
                 ->with('my_tenancy_path')
                 ->andReturn(true)
                 ->once();

            $mock->shouldReceive('parameter')
                 ->with('my_tenancy_path')
                 ->andReturn('my-identifier')
                 ->once();
        });

        $this->assertSame('my-identifier', $resolver->resolveFromRoute($route, $tenancy, $request));
    }

    #[Test]
    public function reportsWhenItCanResolveCorrectly(): void
    {
        $resolver = new PathIdentityResolver('path', hooks: [ResolutionHook::Routing]);

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
        $resolver = new PathIdentityResolver('path');

        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getName')->andReturn('tenants')->times(3);
        });

        $tenant1 = Mockery::mock(Tenant::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getTenantIdentifier')->andReturn('my-identifier-1')->once();
        });

        $tenant2 = Mockery::mock(Tenant::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getTenantIdentifier')->andReturn('my-identifier-2')->once();
        });

        $tenant3 = Mockery::mock(Tenant::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getTenantIdentifier')->andReturn('my-identifier-3')->once();
        });

        $this->assertSame('/my-identifier-1/test-route', $resolver->route('test-route', $tenancy, $tenant1, absolute: false));
        $this->assertSame('/my-identifier-2/test-route', $resolver->route('test-route', $tenancy, $tenant2, absolute: false));
        $this->assertSame('/my-identifier-3/test-route', $resolver->route('test-route', $tenancy, $tenant3, absolute: false));
    }
}
