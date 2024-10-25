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
use Sprout\Http\Middleware\AddTenantHeaderToResponse;
use Workbench\App\Models\TenantModel;

class HeaderResolverTest extends TestCase
{
    use WithWorkbench, RefreshDatabase;

    protected $enablesPackageDiscoveries = true;

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
            $config->set('multitenancy.defaults.resolver', 'header');
        });
    }

    protected function defineRoutes($router)
    {
        $router->get('/', function () {
            return 'no';
        });

        $router->tenanted(function (Router $router) {
            $router->get('/header-route', function (#[CurrentTenant] Tenant $tenant) {
                return $tenant->getTenantKey();
            })->name('header.route');
        }, 'header', 'tenants');
    }

    #[Test]
    public function resolvesFromRoute(): void
    {
        $tenant = TenantModel::factory()->createOne();

        $result = $this->get(route('header.route'), ['Tenants-Identifier' => $tenant->getTenantIdentifier()]);

        $result->assertOk();
        $result->assertContent((string)$tenant->getTenantKey());
    }

    #[Test]
    public function throwsExceptionForInvalidTenant(): void
    {
        $result = $this->get(route('header.route'), ['Tenants-Identifier' => 'i-am-not-real']);

        $result->assertInternalServerError();
    }

    #[Test]
    public function throwsExceptionWithoutHeader(): void
    {
        $result = $this->get(route('header.route'));

        $result->assertInternalServerError();
    }

    #[Test]
    public function addTenantHeaderQueueingMiddleware(): void
    {
        $route = app(Router::class)->getRoutes()->getByName('header.route');

        $this->assertNotNull($route);
        $this->assertContains(AddTenantHeaderToResponse::class . ':header,tenants', $route->middleware());
    }
}
