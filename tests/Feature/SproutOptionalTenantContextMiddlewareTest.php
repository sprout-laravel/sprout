<?php
declare(strict_types=1);

namespace Sprout\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Exceptions\NoTenantFoundException;
use Sprout\Sprout;
use Workbench\App\Models\TenantModel;

class SproutOptionalTenantContextMiddlewareTest extends FeatureTestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
        });
    }

    protected function defineRoutes($router): void
    {
        $router->middleware('web')
               ->group(function ($router) {
                   $router->possiblyTenanted(function ($router) {
                       $router->get('/optional-header-test', function () {
                           $tenant = app(Sprout::class)->getCurrentTenancy()?->tenant();

                           return $tenant?->getTenantIdentifier() ?? 'no-tenant';
                       })->name('optional-header-test');
                   }, 'header');
               });
    }

    #[Test]
    public function resolvesTheTenantWhenIdentifierIsPresent(): void
    {
        $tenant = TenantModel::factory()->createOne();

        $this->withHeader('Tenants-Identifier', $tenant->getTenantIdentifier())
             ->get('/optional-header-test')
             ->assertOk()
             ->assertSee($tenant->getTenantIdentifier());
    }

    #[Test]
    public function passesThroughWithoutThrowingWhenNoTenantIsPresent(): void
    {
        Exceptions::fake();

        $this->get('/optional-header-test')
             ->assertOk()
             ->assertSee('no-tenant');

        Exceptions::assertNotReported(NoTenantFoundException::class);
    }

    #[Test]
    public function passesThroughWithoutThrowingWhenIdentifierDoesNotMatchAnyTenant(): void
    {
        Exceptions::fake();

        $this->withHeader('Tenants-Identifier', 'does-not-exist')
             ->get('/optional-header-test')
             ->assertOk()
             ->assertSee('no-tenant');

        Exceptions::assertNotReported(NoTenantFoundException::class);
    }
}
