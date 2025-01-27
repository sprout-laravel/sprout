<?php
declare(strict_types=1);

namespace Sprout\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\Tenant;
use Sprout\TenancyOptions;
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
    public function canIdentifyViaHeader(): void
    {
        $tenant = TenantModel::factory()->createOne();

        $this->withHeader('Tenants-Identifier', $tenant->getTenantIdentifier())
             ->get('/header-test')
             ->assertOk()
             ->assertSee($tenant->getTenantIdentifier());
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
}
