<?php
declare(strict_types=1);

namespace Sprout\Tests\Resolvers;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Attributes\CurrentTenant;
use Sprout\Contracts\Tenant;
use Workbench\App\Models\TenantModel;

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

    protected function defineRoutes($router)
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
        $tenant = TenantModel::first();

        $result = $this->get(route('path.route', ['tenants_path' => $tenant->getTenantIdentifier()]));

        $result->assertOk();
        $result->assertContent((string)$tenant->getTenantKey());
    }

    #[Test]
    public function resolvesWithoutParameter(): void
    {
        $tenant = TenantModel::first();

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
}
