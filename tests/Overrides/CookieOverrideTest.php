<?php
declare(strict_types=1);

namespace Sprout\Tests\Overrides;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cookie;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Attributes\CurrentTenant;
use Sprout\Contracts\Tenant;
use Sprout\Overrides\AuthOverride;
use Sprout\Overrides\CacheOverride;
use Sprout\Overrides\CookieOverride;
use Sprout\Overrides\JobOverride;
use Sprout\Overrides\SessionOverride;
use Sprout\Overrides\StorageOverride;
use Workbench\App\Models\TenantModel;

#[Group('services'), Group('cookies')]
class CookieOverrideTest extends TestCase
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

    protected function noCookieOverride($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('sprout.services', [
                StorageOverride::class,
                JobOverride::class,
                CacheOverride::class,
                AuthOverride::class,
                SessionOverride::class,
            ]);
        });
    }

    protected function defineRoutes($router): void
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

        $router->tenanted(function (Router $router) {
            $router->get('/path-route', function (#[CurrentTenant] Tenant $tenant) {
                return response($tenant->getTenantIdentifier())->cookie(
                    Cookie::make('yes_tenancy_cookie', $tenant->getTenantKey())
                );
            })->name('path.route')->middleware('web');
        }, 'path', 'tenants');
    }

    #[Test]
    public function doesNotAffectNonTenantedCookies(): void
    {
        $result = $this->get(route('home'));

        $result->assertOk()->assertCookie('no_tenancy_cookie');

        /** @var \Symfony\Component\HttpFoundation\Cookie $cookie */
        $cookie = $result->getCookie('no_tenancy_cookie');

        $this->assertSame(config('session.domain'), $cookie->getDomain());
        $this->assertSame(config('session.path'), $cookie->getPath());
        $this->assertSame((bool)config('session.secure'), $cookie->isSecure());
        $this->assertSame(config('session.same_site'), $cookie->getSameSite());
        $this->assertSame('foo', $cookie->getValue());
    }

    #[Test]
    public function setsTheCookieDomainWhenUsingTheSubdomainIdentityResolver(): void
    {
        $tenant = TenantModel::factory()->createOne();

        $result = $this->get(route('subdomain.route', [$tenant->getTenantIdentifier()]));

        $result->assertOk()->assertCookie('yes_tenancy_cookie');

        /** @var \Symfony\Component\HttpFoundation\Cookie $cookie */
        $cookie = $result->getCookie('yes_tenancy_cookie');

        $this->assertSame($tenant->getTenantIdentifier() . '.localhost', $cookie->getDomain());
        $this->assertSame(config('session.path'), $cookie->getPath());
        $this->assertSame((bool)config('session.secure'), $cookie->isSecure());
        $this->assertSame(config('session.same_site'), $cookie->getSameSite());
        $this->assertSame((string)$tenant->getTenantKey(), $cookie->getValue());
    }

    #[Test]
    public function setsTheCookiePathWhenUsingThePathIdentityResolver(): void
    {
        $tenant = TenantModel::factory()->createOne();

        $result = $this->get(route('path.route', [$tenant->getTenantIdentifier()]));

        $result->assertOk()->assertCookie('yes_tenancy_cookie');

        /** @var \Symfony\Component\HttpFoundation\Cookie $cookie */
        $cookie = $result->getCookie('yes_tenancy_cookie');

        $this->assertSame(config('session.domain'), $cookie->getDomain());
        $this->assertSame($tenant->getTenantIdentifier(), $cookie->getPath());
        $this->assertSame((bool)config('session.secure'), $cookie->isSecure());
        $this->assertSame(config('session.same_site'), $cookie->getSameSite());
        $this->assertSame((string)$tenant->getTenantKey(), $cookie->getValue());
    }

    #[Test, DefineEnvironment('noCookieOverride')]
    public function doesNotSetTheCookieDomainWhenUsingTheSubdomainIdentityResolverIfDisabled(): void
    {
        $tenant = TenantModel::factory()->createOne();

        $result = $this->get(route('subdomain.route', [$tenant->getTenantIdentifier()]));

        $result->assertOk()->assertCookie('yes_tenancy_cookie');

        /** @var \Symfony\Component\HttpFoundation\Cookie $cookie */
        $cookie = $result->getCookie('yes_tenancy_cookie');

        $this->assertSame(config('session.domain'), $cookie->getDomain());
        $this->assertSame(config('session.path'), $cookie->getPath());
        $this->assertSame((bool)config('session.secure'), $cookie->isSecure());
        $this->assertSame(config('session.same_site'), $cookie->getSameSite());
        $this->assertSame((string)$tenant->getTenantKey(), $cookie->getValue());
    }

    #[Test, DefineEnvironment('noCookieOverride')]
    public function doesNotSetTheCookiePathWhenUsingThePathIdentityResolverIfDisabled(): void
    {
        $tenant = TenantModel::factory()->createOne();

        $result = $this->get(route('path.route', [$tenant->getTenantIdentifier()]));

        $result->assertOk()->assertCookie('yes_tenancy_cookie');

        /** @var \Symfony\Component\HttpFoundation\Cookie $cookie */
        $cookie = $result->getCookie('yes_tenancy_cookie');

        $this->assertSame(config('session.domain'), $cookie->getDomain());
        $this->assertSame(config('session.path'), $cookie->getPath());
        $this->assertSame((bool)config('session.secure'), $cookie->isSecure());
        $this->assertSame(config('session.same_site'), $cookie->getSameSite());
        $this->assertSame((string)$tenant->getTenantKey(), $cookie->getValue());
    }
}
