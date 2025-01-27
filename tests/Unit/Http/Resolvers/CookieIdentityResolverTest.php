<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Http\Resolvers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Cookie\CookieJar;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Facades\Cookie;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Exceptions\CompatibilityException;
use Sprout\Http\Resolvers\CookieIdentityResolver;
use Sprout\Sprout;
use Sprout\Support\ResolutionHook;
use Sprout\Support\SettingsRepository;
use Sprout\Tests\Unit\UnitTestCase;

class CookieIdentityResolverTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.defaults.resolver', 'cookie');
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
        $resolver = new CookieIdentityResolver(
            'cookie',
            '{Tenancy}-Cookie-Identifier',
            ['minutes' => 3600],
            [ResolutionHook::Middleware]
        );

        $this->assertSame('cookie', $resolver->getName());
        $this->assertSame('{Tenancy}-Cookie-Identifier', $resolver->getCookieName());
        $this->assertSame(['minutes' => 3600], $resolver->getCookieOptions());
        $this->assertSame([ResolutionHook::Middleware], $resolver->getHooks());
    }

    #[Test]
    public function hasSensibleDefaults(): void
    {
        $resolver = new CookieIdentityResolver('cookie');

        $this->assertSame('cookie', $resolver->getName());
        $this->assertSame('{Tenancy}-Identifier', $resolver->getCookieName());
        $this->assertEmpty($resolver->getCookieOptions());
        $this->assertSame([ResolutionHook::Routing], $resolver->getHooks());
    }

    #[Test]
    public function canGenerateTheCookieNameForATenancy(): void
    {
        $resolver = new CookieIdentityResolver('cookie');

        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->once();
        });

        $this->assertSame('My-tenancy-Identifier', $resolver->getRequestCookieName($tenancy));
    }

    #[Test]
    public function createsRouteGroup(): void
    {
        $resolver = new CookieIdentityResolver('cookie');

        $tenancy = Mockery::mock(Tenancy::class, static function ($mock) {
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->once();
        });

        $routes = static fn () => false;

        /** @var \Illuminate\Routing\Router&\Mockery\MockInterface $router */
        $router = Mockery::mock(Router::class, static function (MockInterface $mock) use ($routes) {
            $mock->shouldReceive('middleware')
                 ->with(['sprout.tenanted:cookie,my-tenancy'])
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
    public function performsSetUp(): void
    {
        $resolver = new CookieIdentityResolver('cookie', options: ['minutes' => 3600]);

        $tenant = Mockery::mock(Tenant::class);

        /** @var \Sprout\Contracts\Tenancy&MockInterface $tenancy */
        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->once();
            $mock->shouldReceive('check')->andReturn(true)->once();
            $mock->shouldReceive('identifier')->andReturn('my-identifier')->once();
        });

        $app = $this->mockApp();

        $app->shouldReceive('make')
            ->with(CookieJar::class)
            ->andReturn(
                Mockery::mock(CookieJar::class, static function (MockInterface $mock) {
                    $mock->shouldReceive('queue')->once();
                })
            )
            ->once();

        $resolver->setApp($app);

        Cookie::shouldReceive('make')
              ->with('My-tenancy-Identifier', 'my-identifier', 3600)
              ->once();

        $resolver->setup($tenancy, $tenant);
    }

    #[Test]
    public function canResolveFromRequest(): void
    {
        $resolver = new CookieIdentityResolver('cookie');

        /** @var \Sprout\Contracts\Tenancy&MockInterface $tenancy */
        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('hasOption')->with('overrides.all')->andReturn(false)->once();
            $mock->shouldReceive('optionConfig')->with('overrides')->andReturn([])->once();
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->once();
        });

        $request = Mockery::mock(Request::class, static function (MockInterface $mock) {
            $mock->shouldReceive('cookie')
                 ->with('My-tenancy-Identifier')
                 ->andReturn('my-identifier')
                 ->once();
        });

        $this->assertSame('my-identifier', $resolver->resolveFromRequest($request, $tenancy));
    }

    #[Test]
    public function throwsAnExceptionIfAllOverridesAreEnabled(): void
    {
        $resolver = new CookieIdentityResolver('cookie');

        /** @var \Sprout\Contracts\Tenancy&MockInterface $tenancy */
        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('hasOption')->with('overrides.all')->andReturn(true)->once();
        });

        $request = Mockery::mock(Request::class);

        $this->expectException(CompatibilityException::class);
        $this->expectExceptionMessage('Cannot use resolver [cookie] with service override [cookie]');

        $resolver->resolveFromRequest($request, $tenancy);
    }

    #[Test]
    public function throwsAnExceptionIfTheCookieOverrideIsEnabled(): void
    {
        $resolver = new CookieIdentityResolver('cookie');

        /** @var \Sprout\Contracts\Tenancy&MockInterface $tenancy */
        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('hasOption')->with('overrides.all')->andReturn(false)->once();
            $mock->shouldReceive('optionConfig')->with('overrides')->andReturn(['cookie'])->once();
        });

        $request = Mockery::mock(Request::class);

        $this->expectException(CompatibilityException::class);
        $this->expectExceptionMessage('Cannot use resolver [cookie] with service override [cookie]');

        $resolver->resolveFromRequest($request, $tenancy);
    }

    #[Test]
    public function reportsWhenItCanResolveCorrectly(): void
    {
        $resolver = new CookieIdentityResolver('cookie', hooks: [ResolutionHook::Routing]);

        $request = Mockery::mock(Request::class);

        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('wasResolved')->andReturnFalse()->times(3);
        });

        $this->assertFalse($resolver->canResolve($request, $tenancy, ResolutionHook::Booting));
        $this->assertTrue($resolver->canResolve($request, $tenancy, ResolutionHook::Routing));
        $this->assertFalse($resolver->canResolve($request, $tenancy, ResolutionHook::Middleware));
    }
}
