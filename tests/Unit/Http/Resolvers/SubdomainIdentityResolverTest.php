<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Http\Resolvers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\Attributes\DefineRoute;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Http\Resolvers\SubdomainIdentityResolver;
use Sprout\Support\ResolutionHook;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;
use function Sprout\resolver;
use function Sprout\sprout;
use function Sprout\tenancy;

class SubdomainIdentityResolverTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
            $config->set('multitenancy.defaults.resolver', 'subdomain');
            $config->set('multitenancy.resolvers.subdomain.domain', 'localhost');
        });
    }

    protected function defineRoutes($router): void
    {
        $router->tenanted(function () {
            Route::get('/tenant', function () {
            })->name('tenant-route');
        }, 'subdomain');
    }

    protected function withCustomParameterPattern(Application $app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.resolvers.subdomain.pattern', '.*');
        });
    }

    protected function withCustomParameterName(Application $app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.resolvers.subdomain.parameter', 'custom_parameter_name');
        });
    }

    protected function withCustomParameterNamePattern(Application $app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.resolvers.subdomain.parameter', '{resolver}_{tenancy}');
        });
    }

    protected function withCustomSubdomainPattern(Application $app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.resolvers.subdomain.subdomain', '{Tenancy}-{tenancy}-{Resolver}-{resolver}');
        });
    }

    protected function withTenantedRoute(Router $router): void
    {
        Route::tenanted(static function () {
            Route::get('/tenant', function () {
            })->name('tenant-route');
        }, 'subdomain');
    }

    #[Test]
    public function isRegisteredAndCanBeAccessed(): void
    {
        $resolver = resolver('subdomain');

        $this->assertInstanceOf(SubdomainIdentityResolver::class, $resolver);
        $this->assertSame('{tenancy}_{resolver}', $resolver->getParameter());
        $this->assertSame('localhost', $resolver->getDomain());
        $this->assertSame('.*', $resolver->getPattern());
        $this->assertSame([ResolutionHook::Routing], $resolver->getHooks());
    }

    #[Test]
    public function replacesPlaceholdersInSubdomainName(): void
    {
        $resolver = resolver('subdomain');
        $tenancy  = tenancy();

        $this->assertInstanceOf(SubdomainIdentityResolver::class, $resolver);
        $this->assertSame('{tenancy}_{resolver}', $resolver->getParameter());
        $this->assertSame('localhost', $resolver->getDomain());
        $this->assertSame($tenancy->getName() . '_' . $resolver->getName(), $resolver->getRouteParameterName($tenancy));
        $this->assertSame('.*', $resolver->getPattern());
        $this->assertSame([ResolutionHook::Routing], $resolver->getHooks());
    }

    #[Test, DefineEnvironment('withCustomParameterPattern')]
    public function acceptsCustomParameterPattern(): void
    {
        $resolver = resolver('subdomain');

        $this->assertInstanceOf(SubdomainIdentityResolver::class, $resolver);
        $this->assertSame('{tenancy}_{resolver}', $resolver->getParameter());
        $this->assertSame('localhost', $resolver->getDomain());
        $this->assertSame('.*', $resolver->getPattern());
        $this->assertSame([ResolutionHook::Routing], $resolver->getHooks());
    }

    #[Test, DefineEnvironment('withCustomParameterName')]
    public function acceptsCustomSubdomainName(): void
    {
        $resolver = resolver('subdomain');

        $this->assertInstanceOf(SubdomainIdentityResolver::class, $resolver);
        $this->assertNotSame('{tenancy}_{resolver}', $resolver->getParameter());
        $this->assertSame('custom_parameter_name', $resolver->getParameter());
        $this->assertSame([ResolutionHook::Routing], $resolver->getHooks());
    }

    #[Test, DefineEnvironment('withCustomParameterNamePattern')]
    public function replacesAllPlaceholdersInSubdomainName(): void
    {
        $resolver = resolver('subdomain');
        $tenancy  = tenancy();

        $this->assertInstanceOf(SubdomainIdentityResolver::class, $resolver);
        $this->assertSame('{resolver}_{tenancy}', $resolver->getParameter());
        $this->assertSame('localhost', $resolver->getDomain());
        $this->assertSame($resolver->getName() . '_' . $tenancy->getName(), $resolver->getRouteParameterName($tenancy));
        $this->assertSame('{' . $resolver->getName() . '_' . $tenancy->getName() . '}.localhost', $resolver->getRouteDomain($tenancy));
        $this->assertSame('.*', $resolver->getPattern());
        $this->assertSame([ResolutionHook::Routing], $resolver->getHooks());
    }

    #[Test, DefineEnvironment('withCustomParameterPattern'), DefineRoute('withTenantedRoute')]
    public function setsUpRouteProperly(): void
    {
        $resolver = resolver('subdomain');
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
        $resolver = resolver('subdomain');
        $tenancy  = tenancy();
        $tenant   = TenantModel::factory()->createOne();

        $this->assertSame('http://' . $tenant->getTenantIdentifier() . '.localhost/tenant', $resolver->route('tenant-route', $tenancy, $tenant));
        $this->assertSame('/tenant', $resolver->route('tenant-route', $tenancy, $tenant, absolute: false));
        $this->assertSame('http://' . $tenant->getTenantIdentifier() . '.localhost/tenant', sprout()->route('tenant-route', $tenant, $resolver->getName(), $tenancy->getName()));
        $this->assertSame('http://' . $tenant->getTenantIdentifier() . '.localhost/tenant', sprout()->route('tenant-route', $tenant));
    }
}
