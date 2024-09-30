<?php
declare(strict_types=1);

namespace Sprout\Tests\Resolvers;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Attributes\CurrentTenant;
use Sprout\Contracts\Tenant;
use Workbench\App\Models\TenantModel;

#[Group('resolvers'), Group('cookies')]
class CookieResolverTest extends TestCase
{
    use WithWorkbench, RefreshDatabase;

    protected $enablesPackageDiscoveries = true;

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
            $config->set('multitenancy.defaults.resolver', 'cookie');
            $config->set('multitenancy.resolvers.cookie', [
                'driver' => 'cookie',
                'cookie' => '{Tenancy}-Identifier',
            ]);
        });
    }

    protected function defineRoutes($router)
    {
        $router->get('/', function () {
            return 'no';
        });

        $router->tenanted(function (Router $router) {
            $router->get('/cookie-route', function (#[CurrentTenant] Tenant $tenant) {
                return $tenant->getTenantKey();
            })->name('cookie.route');
        }, 'cookie', 'tenants');
    }

    #[Test]
    public function resolvesFromRoute(): void
    {
        $tenant = TenantModel::factory()->createOne();

        $result = $this->withUnencryptedCookie('Tenants-Identifier', $tenant->getTenantIdentifier())->get(route('cookie.route'));

        $result->assertOk();
        $result->assertContent((string)$tenant->getTenantKey());
        $result->cookie('Tenants-Identifier', $tenant->getTenantIdentifier());
    }

    #[Test]
    public function throwsExceptionForInvalidTenant(): void
    {
        $result = $this->withCookie('Tenants-Identifier', 'i-am-not-real')->get(route('cookie.route'));
        $result->assertInternalServerError();
    }

    #[Test]
    public function throwsExceptionWithoutHeader(): void
    {
        $result = $this->get(route('cookie.route'));

        $result->assertInternalServerError();
    }
}
