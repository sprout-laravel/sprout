<?php
declare(strict_types=1);

namespace Sprout\Tests\_Original\Http\Resolvers;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Attributes\CurrentTenant;
use Sprout\Contracts\Tenant;
use Workbench\App\Models\TenantModel;
use function Sprout\resolver;

class PathResolverTest extends TestCase
{
    use WithWorkbench, RefreshDatabase;

    protected $enablesPackageDiscoveries = true;

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
            $config->set('multitenancy.defaults.resolver', 'path');
        });
    }

    protected function withManualParameterName($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('multitenancy.resolvers.path.parameter', 'custom-parameter');
        });
    }

    protected function withParameterPatternName($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('multitenancy.resolvers.path.pattern', '.*');
        });
    }

    protected function withoutManualParameterName($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('multitenancy.resolvers.path.parameter', null);
        });
    }

    protected function withoutParameterPattern($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('multitenancy.resolvers.path.pattern', null);
        });
    }

    protected function defineRoutes($router): void
    {
        $router->get('/', function () {
            return 'no';
        });

        $router->tenanted(function (Router $router) {
            $router->get('/path-route', function (#[CurrentTenant] Tenant $tenant) {
                return $tenant->getTenantKey();
            })->name('path.route');
        }, 'path', 'tenants');

        $router->get('/{identifier}/path-request', function (#[CurrentTenant] Tenant $tenant) {
            return $tenant->getTenantKey();
        })->middleware('sprout.tenanted')->name('path.request');
    }

    #[Test]
    public function resolvesFromParameter(): void
    {
        $tenant = TenantModel::factory()->createOne();

        $result = $this->get(route('path.route', ['tenants_path' => $tenant->getTenantIdentifier()]));

        $result->assertOk();
        $result->assertContent((string)$tenant->getTenantKey());
    }

    #[Test]
    public function resolvesWithoutParameter(): void
    {
        $tenant = TenantModel::factory()->createOne();

        $result = $this->get('/' . $tenant->getTenantIdentifier() . '/path-request');

        $result->assertOk();
        $result->assertContent((string)$tenant->getTenantKey());
    }

    #[Test]
    public function throwsExceptionForInvalidTenantWithParameter(): void
    {
        $result = $this->get(route('path.route', ['tenants_path' => 'i-am-not-real']));

        $result->assertInternalServerError();
    }

    #[Test]
    public function throwsExceptionForInvalidTenantWithoutParameter(): void
    {
        $result = $this->get('/i-am-not-real/path-request');

        $result->assertInternalServerError();
    }

    #[Test, DefineEnvironment('withoutParameterPattern')]
    public function hasNoParameterPatternByDefault(): void
    {
        /** @var \Sprout\Http\Resolvers\SubdomainIdentityResolver $resolver */
        $resolver = resolver('path');

        $this->assertNull($resolver->getPattern());
    }

    #[Test, DefineEnvironment('withoutManualParameterName')]
    public function hasDefaultParameterNameByDefault(): void
    {
        /** @var \Sprout\Http\Resolvers\SubdomainIdentityResolver $resolver */
        $resolver = resolver('path');

        $this->assertSame('{tenancy}_{resolver}', $resolver->getParameter());
    }

    #[Test, DefineEnvironment('withManualParameterName')]
    public function allowsForCustomParameterName(): void
    {
        /** @var \Sprout\Http\Resolvers\PathIdentityResolver $resolver */
        $resolver = resolver('path');

        $this->assertSame('custom-parameter', $resolver->getParameter());
    }

    #[Test, DefineEnvironment('withParameterPatternName')]
    public function allowsForCustomParameterPattern(): void
    {
        /** @var \Sprout\Http\Resolvers\PathIdentityResolver $resolver */
        $resolver = resolver('path');

        $this->assertSame('.*', $resolver->getPattern());
    }
}
