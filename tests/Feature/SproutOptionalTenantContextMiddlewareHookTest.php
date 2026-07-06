<?php
declare(strict_types=1);

namespace Sprout\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Exceptions\NoTenantFoundException;
use Sprout\Sprout;
use Sprout\Support\ResolutionHook;
use Workbench\App\Models\TenantModel;

/**
 * Exercises the optional middleware when the *middleware* hook is the only active
 * resolution hook. With routing resolution disabled, the middleware's own
 * handleResolution call — and each of its arguments — becomes observable.
 */
class SproutOptionalTenantContextMiddlewareHookTest extends FeatureTestCase
{
    use RefreshDatabase;

    #[Test]
    public function resolvesTheTenantViaTheMiddlewareHook(): void
    {
        $tenant = TenantModel::factory()->createOne();

        // Resolution can only happen in the middleware here, so this fails unless the
        // guarded handleResolution call actually runs.
        $this->withHeader('Tenants-Identifier', $tenant->getTenantIdentifier())
             ->get('/optional-middleware-hook-test')
             ->assertOk()
             ->assertContent($tenant->getTenantIdentifier());
    }

    #[Test]
    public function passesThroughWithoutThrowingWhenIdentifierDoesNotMatchAnyTenant(): void
    {
        Exceptions::fake();

        // An identifier is present but matches no tenant. Because the middleware passes
        // $throw = false, this must resolve silently rather than raise. (identify()
        // clears the resolver on failure, hence "none".)
        $this->withHeader('Tenants-Identifier', 'does-not-exist')
             ->get('/optional-middleware-hook-test')
             ->assertOk()
             ->assertContent('no-tenant:none');

        Exceptions::assertNotReported(NoTenantFoundException::class);
    }

    #[Test]
    public function doesNotMarkTheTenancyResolvedWhenThereIsNoIdentifier(): void
    {
        // No identifier at all. Because the middleware passes $optional = true, the
        // resolver short-circuits *before* recording itself against the tenancy, so
        // resolver() stays null.
        $this->get('/optional-middleware-hook-test')
             ->assertOk()
             ->assertContent('no-tenant:none');
    }

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
            // Only the middleware hook is active — routing never resolves.
            $config->set('sprout.core.hooks', [ResolutionHook::Middleware]);
            // The header resolver defaults to the routing hook only, so it must be
            // told to run on the middleware hook too.
            $config->set('multitenancy.resolvers.header.hooks', [ResolutionHook::Middleware]);
        });
    }

    protected function defineRoutes($router): void
    {
        $router->middleware('web')
               ->group(function ($router) {
                   $router->possiblyTenanted(function ($router) {
                       $router->get('/optional-middleware-hook-test', function () {
                           $tenancy = app(Sprout::class)->getCurrentTenancy();
                           $tenant  = $tenancy?->tenant();

                           if ($tenant !== null) {
                               return $tenant->getTenantIdentifier();
                           }

                           return 'no-tenant:' . ($tenancy?->resolver()?->getName() ?? 'none');
                       })->name('optional-middleware-hook-test');
                   }, 'header');
               });
    }
}
