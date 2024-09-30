<?php
declare(strict_types=1);

namespace Sprout\Tests\Services;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cookie;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Attributes\CurrentTenant;
use Sprout\Contracts\Tenant;
use Workbench\App\Models\TenantModel;

#[Group('services'), Group('cookies')]
class CookieTest extends TestCase
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

    protected function defineRoutes($router)
    {
        $router->get('/', function () {
            return response('No tenancy')->cookie(Cookie::make('no_tenancy_cookie', 'foo'));
        })->middleware('web')->name('home');

        $router->tenanted(function (Router $router) {
            $router->get('/subdomain-route', function (#[CurrentTenant] Tenant $tenant) {
                return response($tenant->getTenantIdentifier())->cookie(
                    Cookie::make('yes_tenancy_cookie', $tenant->getTenantKey())
                );
            })->name('subdomain.route')->middleware('web');
        }, 'subdomain', 'tenants');
    }

    #[Test]
    public function resolvesFromParameter(): void
    {
        $result1 = $this->get(route('home'));

        $result1->assertOk()->assertCookie('no_tenancy_cookie');

        $cookie1 = $result1->getCookie('no_tenancy_cookie');

        $this->assertSame(config('session.domain'), $cookie1->getDomain());
        $this->assertSame(config('session.path'), $cookie1->getPath());
        $this->assertSame((bool)config('session.secure'), $cookie1->isSecure());
        $this->assertSame(config('session.same_site'), $cookie1->getSameSite());
        $this->assertSame('foo', $cookie1->getValue());

        $tenant = TenantModel::factory()->createOne();

        $result2 = $this->get(route('subdomain.route', [$tenant->getTenantIdentifier()]));

        $result2->assertOk()->assertCookie('yes_tenancy_cookie');

        $cookie2 = $result2->getCookie('yes_tenancy_cookie');

        $this->assertSame($tenant->getTenantIdentifier() .'.localhost', $cookie2->getDomain());
        $this->assertSame(config('session.path'), $cookie2->getPath());
        $this->assertSame((bool)config('session.secure'), $cookie2->isSecure());
        $this->assertSame(config('session.same_site'), $cookie2->getSameSite());
        $this->assertSame((string)$tenant->getTenantKey(), $cookie2->getValue());
    }
}
