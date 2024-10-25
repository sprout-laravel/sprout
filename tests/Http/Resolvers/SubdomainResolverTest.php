<?php
declare(strict_types=1);

namespace Http\Resolvers;

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

class SubdomainResolverTest extends TestCase
{
    use WithWorkbench, RefreshDatabase;

    protected $enablesPackageDiscoveries = true;

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
            $config->set('multitenancy.resolvers.subdomain.domain', 'localhost');
        });
    }

    protected function withManualParameterName($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('multitenancy.resolvers.subdomain.parameter', 'custom-parameter');
        });
    }

    protected function withParameterPatternName($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('multitenancy.resolvers.subdomain.pattern', '.*');
        });
    }

    protected function withoutManualParameterName($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('multitenancy.resolvers.subdomain.parameter', null);
        });
    }

    protected function withoutParameterPattern($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('multitenancy.resolvers.subdomain.pattern', null);
        });
    }

    protected function defineRoutes($router)
    {
        $router->get('/', function () {
            return 'no';
        });

        $router->tenanted(function (Router $router) {
            $router->get('/subdomain-route', function (#[CurrentTenant] Tenant $tenant) {
                return $tenant->getTenantKey();
            })->name('subdomain.route');
        }, 'subdomain', 'tenants');

        $router->get('/subdomain-request', function (#[CurrentTenant] Tenant $tenant) {
            return $tenant->getTenantKey();
        })->middleware('sprout.tenanted')->name('subdomain.request');
    }

    #[Test]
    public function resolvesFromParameter(): void
    {
        $tenant = TenantModel::factory()->createOne();

        $result = $this->get(route('subdomain.route', ['tenants_subdomain' => $tenant->getTenantIdentifier()]));

        $result->assertOk();
        $result->assertContent((string)$tenant->getTenantKey());
    }

    #[Test]
    public function resolvesWithoutParameter(): void
    {
        $tenant = TenantModel::factory()->createOne();

        $result = $this->get('http://' . $tenant->getTenantIdentifier() . '.localhost/subdomain-request');

        $result->assertOk();
        $result->assertContent((string)$tenant->getTenantKey());
    }

    #[Test]
    public function throwsExceptionForInvalidTenantWithParameter(): void
    {
        $result = $this->get(route('subdomain.route', ['tenants_subdomain' => 'i-am-not-real']));

        $result->assertInternalServerError();
    }

    #[Test]
    public function throwsExceptionForInvalidTenantWithoutParameter(): void
    {
        $result = $this->get('http://i-am-not-real.localhost/subdomain-request');

        $result->assertInternalServerError();
    }

    #[Test]
    public function throwsExceptionForInvalidTenantWithJustMultitenantedDomain(): void
    {
        $result = $this->get('http://localhost/subdomain-request');

        $result->assertInternalServerError();
    }

    #[Test, DefineEnvironment('withoutParameterPattern')]
    public function hasNoParameterPatternByDefault(): void
    {
        /** @var \Sprout\Http\Resolvers\SubdomainIdentityResolver $resolver */
        $resolver = resolver('subdomain');

        $this->assertNull($resolver->getPattern());
    }

    #[Test, DefineEnvironment('withoutManualParameterName')]
    public function hasDefaultParameterNameByDefault(): void
    {
        /** @var \Sprout\Http\Resolvers\SubdomainIdentityResolver $resolver */
        $resolver = resolver('subdomain');

        $this->assertSame('{tenancy}_{resolver}', $resolver->getParameter());
    }

    #[Test, DefineEnvironment('withManualParameterName')]
    public function allowsForCustomParameterName(): void
    {
        /** @var \Sprout\Http\Resolvers\SubdomainIdentityResolver $resolver */
        $resolver = resolver('subdomain');

        $this->assertSame('custom-parameter', $resolver->getParameter());
    }

    #[Test, DefineEnvironment('withParameterPatternName')]
    public function allowsForCustomParameterPattern(): void
    {
        /** @var \Sprout\Http\Resolvers\SubdomainIdentityResolver $resolver */
        $resolver = resolver('subdomain');

        $this->assertSame('.*', $resolver->getPattern());
    }
}
