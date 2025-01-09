<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Http\Resolvers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\Attributes\DefineRoute;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Http\Resolvers\PathIdentityResolver;
use Sprout\Support\ResolutionHook;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;
use function Sprout\resolver;
use function Sprout\sprout;
use function Sprout\tenancy;

class PathIdentityResolverTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
            $config->set('multitenancy.defaults.resolver', 'path');
        });
    }

    protected function defineRoutes($router): void
    {
        $router->tenanted(function () {
            Route::get('/tenant', function () {
            })->name('tenant-route');
        }, 'path');
    }

    protected function withCustomSegment(Application $app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.resolvers.path.segment', 2);
        });
    }

    protected function withCustomParameterPattern(Application $app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.resolvers.path.pattern', '.*');
        });
    }

    protected function withCustomParameterName(Application $app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.resolvers.path.parameter', 'custom_parameter_name');
        });
    }

    protected function withCustomParameterNamePattern(Application $app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.resolvers.path.parameter', '{resolver}_{tenancy}');
        });
    }

    protected function withCustomPathNamePattern(Application $app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.resolvers.path.path', '{Tenancy}-{tenancy}-{Resolver}-{resolver}');
        });
    }

    protected function withTenantedRoute(Router $router): void
    {
        Route::tenanted(static function () {
            Route::get('/tenant', function () {
            })->name('tenant-route');
        }, 'path');
    }

    #[Test]
    public function isRegisteredAndCanBeAccessed(): void
    {
        $resolver = resolver('path');

        $this->assertInstanceOf(PathIdentityResolver::class, $resolver);
        $this->assertSame('{tenancy}_{resolver}', $resolver->getParameter());
        $this->assertSame(1, $resolver->getSegment());
        $this->assertNull($resolver->getPattern());
        $this->assertSame([ResolutionHook::Routing], $resolver->getHooks());
    }

    #[Test]
    public function replacesPlaceholdersInPathName(): void
    {
        $resolver = resolver('path');
        $tenancy  = tenancy();

        $this->assertInstanceOf(PathIdentityResolver::class, $resolver);
        $this->assertSame('{tenancy}_{resolver}', $resolver->getParameter());
        $this->assertSame(1, $resolver->getSegment());
        $this->assertSame($tenancy->getName() . '_' . $resolver->getName(), $resolver->getRouteParameterName($tenancy));
        $this->assertSame('{' . $tenancy->getName() . '_' . $resolver->getName() . '}', $resolver->getRoutePrefix($tenancy));
        $this->assertNull($resolver->getPattern());
        $this->assertSame([ResolutionHook::Routing], $resolver->getHooks());
    }

    #[Test, DefineEnvironment('withCustomSegment')]
    public function acceptsCustomSegment(): void
    {
        $resolver = resolver('path');

        $this->assertInstanceOf(PathIdentityResolver::class, $resolver);
        $this->assertSame(2, $resolver->getSegment());
        $this->assertSame('{tenancy}_{resolver}', $resolver->getParameter());
        $this->assertNull($resolver->getPattern());
        $this->assertSame([ResolutionHook::Routing], $resolver->getHooks());
    }

    #[Test, DefineEnvironment('withCustomParameterPattern')]
    public function acceptsCustomParameterPattern(): void
    {
        $resolver = resolver('path');

        $this->assertInstanceOf(PathIdentityResolver::class, $resolver);
        $this->assertSame('{tenancy}_{resolver}', $resolver->getParameter());
        $this->assertSame(1, $resolver->getSegment());
        $this->assertSame('.*', $resolver->getPattern());
        $this->assertSame([ResolutionHook::Routing], $resolver->getHooks());
    }

    #[Test, DefineEnvironment('withCustomParameterName')]
    public function acceptsCustomPathName(): void
    {
        $resolver = resolver('path');

        $this->assertInstanceOf(PathIdentityResolver::class, $resolver);
        $this->assertNotSame('{tenancy}_{resolver}', $resolver->getParameter());
        $this->assertSame('custom_parameter_name', $resolver->getParameter());
        $this->assertSame([ResolutionHook::Routing], $resolver->getHooks());
    }

    #[Test, DefineEnvironment('withCustomParameterNamePattern')]
    public function replacesAllPlaceholdersInPathName(): void
    {
        $resolver = resolver('path');
        $tenancy  = tenancy();

        $this->assertInstanceOf(PathIdentityResolver::class, $resolver);
        $this->assertSame('{resolver}_{tenancy}', $resolver->getParameter());
        $this->assertSame(1, $resolver->getSegment());
        $this->assertSame($resolver->getName() . '_' . $tenancy->getName(), $resolver->getRouteParameterName($tenancy));
        $this->assertNull($resolver->getPattern());
        $this->assertSame([ResolutionHook::Routing], $resolver->getHooks());
    }

    #[Test, DefineEnvironment('withCustomParameterPattern'), DefineRoute('withTenantedRoute')]
    public function setsUpRouteProperly(): void
    {
        $resolver = resolver('path');
        $tenancy  = tenancy();
        $routes   = app(Router::class)->getRoutes();

        $this->assertTrue($resolver->hasPattern());
        $this->assertTrue($routes->hasNamedRoute('tenant-route'));

        $route = $routes->getByName('tenant-route');

        $this->assertContains($tenancy->getName() . '_' . $resolver->getName(), $route->parameterNames());
        $this->assertArrayHasKey($tenancy->getName() . '_' . $resolver->getName(), $route->wheres);
        $this->assertSame('.*', $route->wheres[$tenancy->getName() . '_' . $resolver->getName()]);
    }

    #[Test]
    public function canGenerateRoutesForATenant(): void
    {
        $resolver = resolver('path');
        $tenancy  = tenancy();
        $tenant   = TenantModel::factory()->createOne();

        $this->assertSame('http://localhost/' . $tenant->getTenantIdentifier() . '/tenant', $resolver->route('tenant-route', $tenancy, $tenant));
        $this->assertSame('/' . $tenant->getTenantIdentifier() . '/tenant', $resolver->route('tenant-route', $tenancy, $tenant, absolute: false));
        $this->assertSame('http://localhost/' . $tenant->getTenantIdentifier() . '/tenant', sprout()->route('tenant-route', $tenant, $resolver->getName(), $tenancy->getName()));
        $this->assertSame('http://localhost/' . $tenant->getTenantIdentifier() . '/tenant', sprout()->route('tenant-route', $tenant));
    }
}
