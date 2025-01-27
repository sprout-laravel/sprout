<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Http\Resolvers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Session\Store;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Exceptions\CompatibilityException;
use Sprout\Http\Resolvers\SessionIdentityResolver;
use Sprout\Sprout;
use Sprout\Support\ResolutionHook;
use Sprout\Support\SettingsRepository;
use Sprout\Tests\Unit\UnitTestCase;

class SessionIdentityResolverTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.defaults.resolver', 'session');
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
        $resolver = new SessionIdentityResolver(
            'session',
            '{Tenancy}-Session-Identifier'
        );

        $this->assertSame('session', $resolver->getName());
        $this->assertSame('{Tenancy}-Session-Identifier', $resolver->getSessionName());
        $this->assertSame([ResolutionHook::Middleware], $resolver->getHooks());
    }

    #[Test]
    public function hasSensibleDefaults(): void
    {
        $resolver = new SessionIdentityResolver('session');

        $this->assertSame('session', $resolver->getName());
        $this->assertSame('multitenancy.{tenancy}', $resolver->getSessionName());
        $this->assertSame([ResolutionHook::Middleware], $resolver->getHooks());
    }

    #[Test]
    public function canGenerateTheSessionNameForATenancy(): void
    {
        $resolver = new SessionIdentityResolver('session');

        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->once();
        });

        $this->assertSame('multitenancy.my-tenancy', $resolver->getRequestSessionName($tenancy));
    }

    #[Test]
    public function createsRouteGroup(): void
    {
        $resolver = new SessionIdentityResolver('session');

        $tenancy = Mockery::mock(Tenancy::class, static function ($mock) {
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->once();
        });

        $routes = static fn () => false;

        /** @var \Illuminate\Routing\Router&\Mockery\MockInterface $router */
        $router = Mockery::mock(Router::class, static function (MockInterface $mock) use ($routes) {
            $mock->shouldReceive('middleware')
                 ->with(['sprout.tenanted:session,my-tenancy'])
                 ->andReturn(
                     Mockery::mock(RouteRegistrar::class, static function (MockInterface $mock) use ($routes) {
                         $mock->shouldReceive('group')
                              ->with($routes)
                              ->andReturnSelf()
                              ->once();
                     })
                 )
                 ->once();
        });

        $resolver->routes($router, $routes, $tenancy);
    }

    #[Test]
    public function canResolveFromRequest(): void
    {
        $resolver = new SessionIdentityResolver('session');

        /** @var \Sprout\Contracts\Tenancy&MockInterface $tenancy */
        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('hasOption')->with('overrides.all')->andReturn(false)->once();
            $mock->shouldReceive('optionConfig')->with('overrides')->andReturn([])->once();
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->once();
        });

        $request = Mockery::mock(Request::class, static function (MockInterface $mock) {
            $mock->shouldReceive('session')
                 ->andReturn(
                     Mockery::mock(Store::class, static function (MockInterface $mock) {
                         $mock->shouldReceive('get')
                              ->with('multitenancy.my-tenancy')
                              ->andReturn('my-identifier')
                              ->once();
                     })
                 )
                 ->once();
        });

        $app = $this->mockApp();

        $sprout = $this->getSprout($app);

        $resolver->setApp($app)->setSprout($sprout);

        $this->assertSame('my-identifier', $resolver->resolveFromRequest($request, $tenancy));
    }

    #[Test]
    public function throwsAnExceptionIfAllOverridesAreEnabled(): void
    {
        $resolver = new SessionIdentityResolver('session');

        /** @var \Sprout\Contracts\Tenancy&MockInterface $tenancy */
        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('hasOption')->with('overrides.all')->andReturn(true)->once();
        });

        $request = Mockery::mock(Request::class);

        $this->expectException(CompatibilityException::class);
        $this->expectExceptionMessage('Cannot use resolver [session] with service override [session]');

        $resolver->resolveFromRequest($request, $tenancy);
    }

    #[Test]
    public function throwsAnExceptionIfTheSessionOverrideIsEnabled(): void
    {
        $resolver = new SessionIdentityResolver('session');

        /** @var \Sprout\Contracts\Tenancy&MockInterface $tenancy */
        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('hasOption')->with('overrides.all')->andReturn(false)->once();
            $mock->shouldReceive('optionConfig')->with('overrides')->andReturn(['session'])->once();
        });

        $request = Mockery::mock(Request::class);

        $this->expectException(CompatibilityException::class);
        $this->expectExceptionMessage('Cannot use resolver [session] with service override [session]');

        $resolver->resolveFromRequest($request, $tenancy);
    }

    #[Test]
    public function reportsWhenItCanResolveCorrectly(): void
    {
        $resolver = new SessionIdentityResolver('session');

        $request = Mockery::mock(Request::class, static function (MockInterface $mock) {
            $mock->shouldReceive('hasSession')->andReturn(true)->times(3);
        });

        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('wasResolved')->andReturnFalse()->times(3);
        });

        $this->assertFalse($resolver->canResolve($request, $tenancy, ResolutionHook::Booting));
        $this->assertFalse($resolver->canResolve($request, $tenancy, ResolutionHook::Routing));
        $this->assertTrue($resolver->canResolve($request, $tenancy, ResolutionHook::Middleware));
    }

    #[Test]
    public function cannotResolveWithoutASession(): void
    {
        $resolver = new SessionIdentityResolver('session');

        $request = Mockery::mock(Request::class, static function (MockInterface $mock) {
            $mock->shouldReceive('hasSession')->andReturn(false)->times(3);
        });

        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('wasResolved')->andReturnFalse()->times(3);
        });

        $this->assertFalse($resolver->canResolve($request, $tenancy, ResolutionHook::Booting));
        $this->assertFalse($resolver->canResolve($request, $tenancy, ResolutionHook::Routing));
        $this->assertFalse($resolver->canResolve($request, $tenancy, ResolutionHook::Middleware));
    }

    #[Test]
    public function canGenerateRouteUrls(): void
    {
        $resolver = new SessionIdentityResolver('session');

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
