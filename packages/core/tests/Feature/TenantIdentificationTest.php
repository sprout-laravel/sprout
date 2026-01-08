<?php
declare(strict_types=1);

namespace Sprout\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Core\Contracts\Tenant;
use Sprout\Core\Exceptions\NoTenantFoundException;
use Sprout\Core\Http\Middleware\AddTenantHeaderToResponse;
use Sprout\Core\Support\ResolutionHook;
use Sprout\Core\TenancyOptions;
use Workbench\App\Models\TenantModel;

class TenantIdentificationTest extends FeatureTestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('session.driver', 'array');
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
            $config->set('multitenancy.resolvers.subdomain.domain', 'localhost');
            $config->set('multitenancy.tenancies.tenants.options', [
                TenancyOptions::overrides(['auth', 'cache', 'filesystem', 'job']),
            ]);
        });
    }

    protected function defineRoutes($router): void
    {
        $router->middleware('web')
               ->group(function ($router) {
                   $router->tenanted(function ($router) {
                       $router->get('/cookie-test', function (Tenant $tenant) {
                           return $tenant->getTenantIdentifier();
                       })->name('cookie-test');
                   }, 'cookie');

                   $router->tenanted(function ($router) {
                       $router->get('/header-test', function (Tenant $tenant) {
                           return $tenant->getTenantIdentifier();
                       })->name('header-test');
                   }, 'header');

                   $router->get('/fake-header-route', function () {
                       return 'fake-header-route';
                   })->middleware(AddTenantHeaderToResponse::class)->name('fake-header-route');

                   $router->tenanted(function ($router) {
                       $router->get('/path-test', function (Tenant $tenant) {
                           return $tenant->getTenantIdentifier();
                       })->name('path-test');
                   }, 'path');

                   $router->tenanted(function ($router) {
                       $router->get('/session-test', function (Tenant $tenant) {
                           return $tenant->getTenantIdentifier();
                       })->name('session-test');
                   }, 'session');

                   $router->tenanted(function ($router) {
                       $router->get('/subdomain-test', function (Tenant $tenant) {
                           return $tenant->getTenantIdentifier();
                       })->name('subdomain-test');
                   }, 'subdomain');

                   $router->tenanted(function ($router) {
                       $router->get('/subdomain-header-test', function (Tenant $tenant) {
                           return $tenant->getTenantIdentifier();
                       })->middleware(AddTenantHeaderToResponse::class)->name('subdomain-with-header-middleware-test');
                   }, 'subdomain');
               });
    }

    #[Test]
    public function canIdentifyViaCookie(): void
    {
        $tenant = TenantModel::factory()->createOne();

        $this->withCookie('Tenants-Identifier', $tenant->getTenantIdentifier())
             ->get('/cookie-test')
             ->assertOk()
             ->assertSee($tenant->getTenantIdentifier());
    }

    #[Test]
    public function canIdentifyViaUnencryptedCookie(): void
    {
        $tenant = TenantModel::factory()->createOne();

        $this->withUnencryptedCookie('Tenants-Identifier', $tenant->getTenantIdentifier())
             ->get('/cookie-test')
             ->assertOk()
             ->assertSee($tenant->getTenantIdentifier());
    }

    #[Test]
    public function canIdentifyViaHeader(): void
    {
        $tenant = TenantModel::factory()->createOne();

        $this->withHeader('Tenants-Identifier', $tenant->getTenantIdentifier())
             ->get('/header-test')
             ->assertOk()
             ->assertSee($tenant->getTenantIdentifier());
    }

    #[Test]
    public function addsHeaderToResponse(): void
    {
        $tenant = TenantModel::factory()->createOne();

        $this->withHeader('Tenants-Identifier', $tenant->getTenantIdentifier())
             ->get('/header-test')
             ->assertOk()
             ->assertSee($tenant->getTenantIdentifier())
             ->assertHeader('Tenants-Identifier', $tenant->getTenantIdentifier());
    }

    #[Test]
    public function doesNotAddHeaderToResponseWithoutTenant(): void
    {
        $this->withHeader('Tenants-Identifier', 'fake-identifier')
             ->get('/fake-header-route')
             ->assertOk()
             ->assertHeaderMissing('Tenants-Identifier');
    }

    #[Test]
    public function doesNothingWhenTheHeaderMiddlewareIsAddedToRouteThatUseDifferentIdentityResolvers(): void
    {
        $tenant = TenantModel::factory()->createOne();

        $this->withHeader('Tenants-Identifier', 'fake-identifier')
             ->get(route('subdomain-with-header-middleware-test', $tenant->getTenantIdentifier()))
             ->assertOk()
             ->assertHeaderMissing('Tenants-Identifier');
    }

    #[Test]
    public function canIdentifyViaPath(): void
    {
        $tenant = TenantModel::factory()->createOne();

        $this->get(route('path-test', $tenant->getTenantIdentifier()))
             ->assertOk()
             ->assertSee($tenant->getTenantIdentifier());
    }

    #[Test]
    public function canIdentifyViaSession(): void
    {
        $tenant = TenantModel::factory()->createOne();

        $this->withSession(['multitenancy.tenants' => $tenant->getTenantIdentifier()])
             ->get('/session-test')
             ->assertOk()
             ->assertSee($tenant->getTenantIdentifier());
    }

    #[Test]
    public function canIdentifyViaSubdomain(): void
    {
        $tenant = TenantModel::factory()->createOne();

        $this->get(route('subdomain-test', $tenant->getTenantIdentifier()))
             ->assertOk()
             ->assertSee($tenant->getTenantIdentifier());
    }

    #[Test]
    public function errorsWhenThereIsNoTenancyAndMiddlewareIsNotSupported(): void
    {
        config()->set('sprout.core.hooks', [ResolutionHook::Routing]);

        Exceptions::fake();

        $this->get(route('subdomain-test', 'fake-identifier'))
             ->assertServerError();

        Exceptions::assertReported(function (NoTenantFoundException $exception) {
            return $exception->getMessage() === 'No valid tenant [tenants] found [subdomain]';
        });
    }
}
