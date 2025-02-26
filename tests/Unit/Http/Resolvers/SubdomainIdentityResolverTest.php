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
use Sprout\Http\Resolvers\SubdomainIdentityResolver;
use Sprout\Sprout;
use Sprout\Support\ResolutionHook;
use Sprout\Support\SettingsRepository;
use Sprout\Tests\Unit\UnitTestCase;

class SubdomainIdentityResolverTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.defaults.resolver', 'subdomain');
            $config->set('multitenancy.resolvers.subdomain.domain', 'localhost');
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
        $resolver = new SubdomainIdentityResolver(
            'subdomain',
            'my-app.com',
            '.*',
            'yeah-boi-{tenancy}',
            [ResolutionHook::Middleware]
        );

        $this->assertSame('subdomain', $resolver->getName());
        $this->assertSame('my-app.com', $resolver->getDomain());
        $this->assertSame('.*', $resolver->getPattern());
        $this->assertSame('yeah-boi-{tenancy}', $resolver->getParameter());
        $this->assertSame([ResolutionHook::Middleware], $resolver->getHooks());
        $this->assertTrue($resolver->hasPattern());
    }

    #[Test]
    public function hasSensibleDefaults(): void
    {
        $resolver = new SubdomainIdentityResolver('subdomain', 'my-app.com');

        $this->assertSame('subdomain', $resolver->getName());
        $this->assertSame('my-app.com', $resolver->getDomain());
        $this->assertNull($resolver->getPattern());
        $this->assertSame('{tenancy}_{resolver}', $resolver->getParameter());
        $this->assertSame([ResolutionHook::Routing], $resolver->getHooks());
        $this->assertFalse($resolver->hasPattern());
    }

    #[Test]
    public function canGenerateTheDomainForATenant(): void
    {
        $resolver = new SubdomainIdentityResolver('subdomain', 'my-app.com');

        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->times(3);
            $mock->shouldReceive('check')->andReturn(true)->once();
            $mock->shouldReceive('identifier')->andReturn('my-identifier');
        });

        $this->assertSame('{my_tenancy_subdomain}.my-app.com', $resolver->getRouteDomain($tenancy));
        $this->assertSame('my-identifier.my-app.com', $resolver->getTenantRouteDomain($tenancy));
    }

    #[Test]
    public function createsRouteGroup(): void
    {
        $resolver = new SubdomainIdentityResolver('subdomain', 'my-app.com');

        $tenancy = Mockery::mock(Tenancy::class, static function ($mock) {
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->twice();
        });

        $routes = static fn () => false;

        /** @var \Illuminate\Routing\Router&\Mockery\MockInterface $router */
        $router = Mockery::mock(Router::class, static function (MockInterface $mock) use ($routes) {
            $mock->shouldReceive('domain')
                 ->with('{my_tenancy_subdomain}.my-app.com')
                 ->andReturn(
                     Mockery::mock(RouteRegistrar::class, static function (MockInterface $mock) use ($routes) {
                         $mock->shouldReceive('middleware')
                              ->with(['sprout.tenanted:subdomain,my-tenancy'])
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
        $resolver = new SubdomainIdentityResolver('subdomain', 'my-app.com', pattern: '.*');

        $tenancy = Mockery::mock(Tenancy::class, static function ($mock) {
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->times(3);
        });

        $routes = static fn () => false;

        /** @var \Illuminate\Routing\Router&\Mockery\MockInterface $router */
        $router = Mockery::mock(Router::class, static function (MockInterface $mock) use ($routes) {
            $mock->shouldReceive('domain')
                 ->with('{my_tenancy_subdomain}.my-app.com')
                 ->andReturn(
                     Mockery::mock(RouteRegistrar::class, static function (MockInterface $mock) use ($routes) {
                         $mock->shouldReceive('middleware')
                              ->with(['sprout.tenanted:subdomain,my-tenancy'])
                              ->andReturnSelf()
                              ->once();

                         $mock->shouldReceive('group')
                              ->with($routes)
                              ->andReturnSelf()
                              ->once();

                         $mock->shouldReceive('where')
                              ->with(['my_tenancy_subdomain' => '.*'])
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
        $resolver = new SubdomainIdentityResolver('subdomain', 'my-app.com');

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
           ->with(['my_tenancy_subdomain' => 'my-identifier'])
           ->once();

        $resolver->setup($tenancy, $tenant);

        $this->assertSame('my-identifier.my-app.com', $sprout->settings()->getUrlDomain());
    }

    #[Test]
    public function performsSetUpWithoutSettingsIfNoTenant(): void
    {
        $resolver = new SubdomainIdentityResolver('subdomain', 'my-app.com');

        /** @var \Sprout\Contracts\Tenancy&MockInterface $tenancy */
        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->once();
        });

        $app = $this->mockApp();

        $sprout = $this->getSprout($app);

        $resolver->setApp($app)->setSprout($sprout);

        URL::shouldReceive('defaults')
           ->with(['my_tenancy_subdomain' => null])
           ->once();

        $resolver->setup($tenancy, null);

        $this->assertNull($sprout->settings()->getUrlDomain());
    }

    #[Test]
    public function canResolveFromRequest(): void
    {
        $resolver = new SubdomainIdentityResolver('subdomain', 'my-app.com');

        /** @var \Sprout\Contracts\Tenancy&MockInterface $tenancy */
        $tenancy = Mockery::mock(Tenancy::class);

        $request = Mockery::mock(Request::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getHost')
                 ->andReturn('my-identifier.my-app.com')
                 ->once();
        });

        $this->assertSame('my-identifier', $resolver->resolveFromRequest($request, $tenancy));
    }

    #[Test]
    public function returnsNullIfTheMainDomainIsWrong(): void
    {
        $resolver = new SubdomainIdentityResolver('subdomain', 'my-app.com');

        /** @var \Sprout\Contracts\Tenancy&MockInterface $tenancy */
        $tenancy = Mockery::mock(Tenancy::class);

        $request = Mockery::mock(Request::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getHost')
                 ->andReturn('my-identifier.my-other-app.com')
                 ->once();
        });

        $this->assertNull($resolver->resolveFromRequest($request, $tenancy));
    }

    #[Test]
    public function returnsNullIfThereIsNoSubdomain(): void
    {
        $resolver = new SubdomainIdentityResolver('subdomain', 'my-app.com');

        /** @var \Sprout\Contracts\Tenancy&MockInterface $tenancy */
        $tenancy = Mockery::mock(Tenancy::class);

        $request = Mockery::mock(Request::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getHost')
                 ->andReturn('my-app.com')
                 ->once();
        });

        $this->assertNull($resolver->resolveFromRequest($request, $tenancy));
    }

    #[Test]
    public function canResolveFromRoute(): void
    {
        $resolver = new SubdomainIdentityResolver('subdomain', 'my-app.com');

        /** @var \Sprout\Contracts\Tenancy&MockInterface $tenancy */
        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->twice();
        });

        $request = Mockery::mock(Request::class);

        $route = Mockery::mock(Route::class, static function (MockInterface $mock) {
            $mock->shouldReceive('hasParameter')
                ->with('my_tenancy_subdomain')
                ->andReturn(true);

            $mock->shouldReceive('parameter')
                 ->with('my_tenancy_subdomain')
                 ->andReturn('my-identifier')
                 ->once();
        });

        $this->assertSame('my-identifier', $resolver->resolveFromRoute($route, $tenancy, $request));
    }

    #[Test]
    public function reportsWhenItCanResolveCorrectly(): void
    {
        $resolver = new SubdomainIdentityResolver('subdomain', 'my-app.com');

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
        $resolver = new SubdomainIdentityResolver('subdomain', 'localhost');

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

        $this->assertSame('http://my-identifier-1.localhost/test-route', $resolver->route('test-route', $tenancy, $tenant1));
        $this->assertSame('http://my-identifier-2.localhost/test-route', $resolver->route('test-route', $tenancy, $tenant2));
        $this->assertSame('http://my-identifier-3.localhost/test-route', $resolver->route('test-route', $tenancy, $tenant3));
    }
}
