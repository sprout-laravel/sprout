<?php
declare(strict_types=1);

namespace Http\Resolvers;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Session\Middleware\StartSession;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Attributes\CurrentTenant;
use Sprout\Contracts\Tenant;
use Sprout\Exceptions\CompatibilityException;
use Sprout\Overrides\AuthOverride;
use Sprout\Overrides\CacheOverride;
use Sprout\Overrides\CookieOverride;
use Sprout\Overrides\JobOverride;
use Sprout\Overrides\SessionOverride;
use Sprout\Overrides\StorageOverride;
use Workbench\App\Models\TenantModel;
use function Sprout\sprout;

#[Group('resolvers'), Group('sessions')]
class SessionResolverTest extends TestCase
{
    use WithWorkbench, RefreshDatabase;

    protected $enablesPackageDiscoveries = true;

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
            $config->set('multitenancy.defaults.resolver', 'session');
            $config->set('multitenancy.resolvers.session', [
                'driver'  => 'session',
                'session' => 'multitenancy.{tenancy}',
            ]);
            $config->set('sprout.services', [
                StorageOverride::class,
                JobOverride::class,
                CacheOverride::class,
                AuthOverride::class,
                CookieOverride::class,
            ]);
        });
    }

    protected function withSessionOverride($app):void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('sprout.services', [
                StorageOverride::class,
                JobOverride::class,
                CacheOverride::class,
                AuthOverride::class,
                CookieOverride::class,
                SessionOverride::class,
            ]);
        });
    }

    protected function defineRoutes($router): void
    {
        $router->middleware(StartSession::class)->group(function (Router $router) {
            $router->get('/', function () {
                return 'no';
            });

            $router->tenanted(function (Router $router) {
                $router->get('/session-route', function (#[CurrentTenant] Tenant $tenant) {
                    return $tenant->getTenantKey();
                })->name('session.route');
            }, 'session', 'tenants');
        });
    }

    #[Test]
    public function resolvesFromRoute(): void
    {
        $tenant = TenantModel::factory()->createOne();

        $result = $this->withSession(['multitenancy' => ['tenants' => $tenant->getTenantIdentifier()]])->get(route('session.route'));

        $result->assertOk();
        $result->assertContent((string)$tenant->getTenantKey());
        $result->assertSessionHas('multitenancy.tenants', $tenant->getTenantIdentifier());
    }

    #[Test]
    public function throwsExceptionForInvalidTenant(): void
    {
        $result = $this->withSession(['multitenancy' => ['tenants' => 'i-am-not-real']])->get(route('session.route'));
        $result->assertInternalServerError();
    }

    #[Test]
    public function throwsExceptionWithoutHeader(): void
    {
        $result = $this->get(route('session.route'));

        $result->assertInternalServerError();
    }

    #[Test]
    public function throwsExceptionIfSessionOverrideIsEnabled(): void
    {
        sprout()->registerOverride(SessionOverride::class);
        $tenant = TenantModel::factory()->createOne();

        $result = $this->withSession(['multitenancy' => ['tenants' => $tenant->getTenantIdentifier()]])->get(route('session.route'));
        $result->assertInternalServerError();
        $this->assertInstanceOf(CompatibilityException::class, $result->exception);
        $this->assertSame(
            'Cannot use resolver [session] with override [' . SessionOverride::class . ']',
            $result->exception->getMessage()
        );
    }
}
