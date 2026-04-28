<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Attributes;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Attributes\TenantConfig as TenantConfigAttribute;
use Sprout\Contracts\ConfigStore;
use Sprout\Managers\ConfigStoreManager;
use Sprout\TenantConfig as TenantConfigService;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;
use function Sprout\tenancy;

class TenantConfigTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], function ($config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
        });
    }

    /**
     * Integration tests (sibling-pattern): exercise the real container's
     * contextual-attribute resolution via $this->app->call().
     */

    #[Test]
    public function resolvesConfigDataFromTheCurrentTenancyAndTenant(): void
    {
        /** @var \Sprout\Contracts\Tenancy $tenancy */
        $tenancy = tenancy('tenants');
        $tenant  = TenantModel::factory()->createOne();
        $tenancy->setTenant($tenant);

        $expected = ['driver' => 'redis', 'connection' => 'cache'];
        $store    = Mockery::mock(ConfigStore::class, function (MockInterface $mock) use ($expected, $tenancy, $tenant) {
            $mock->shouldReceive('get')
                 ->with($tenancy, $tenant, 'cache', 'default', null)
                 ->andReturn($expected);
        });

        $manager = Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($store) {
            $mock->shouldReceive('get')->andReturn($store);
        });

        // Replace the TenantConfig service binding so the contextual attribute
        // resolves a service backed by our mocked ConfigStoreManager and with
        // the tenancy/tenant explicitly populated.
        $service = (new TenantConfigService($this->app, $manager))
            ->setTenancy($tenancy)
            ->setTenant($tenant);
        $this->app->instance(TenantConfigService::class, $service);

        $callback = static function (#[TenantConfigAttribute('cache', 'default')] ?array $config) {
            return $config;
        };

        $this->assertSame($expected, $this->app->call($callback));
    }

    #[Test]
    public function resolvesConfigDataWithAnExplicitStore(): void
    {
        /** @var \Sprout\Contracts\Tenancy $tenancy */
        $tenancy = tenancy('tenants');
        $tenant  = TenantModel::factory()->createOne();
        $tenancy->setTenant($tenant);

        $expected = ['driver' => 'redis'];
        $store    = Mockery::mock(ConfigStore::class, function (MockInterface $mock) use ($expected, $tenancy, $tenant) {
            $mock->shouldReceive('get')
                 ->with($tenancy, $tenant, 'cache', 'default', null)
                 ->andReturn($expected);
        });

        $manager = Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($store) {
            $mock->shouldReceive('get')->with('database')->andReturn($store);
        });

        $service = (new TenantConfigService($this->app, $manager))
            ->setTenancy($tenancy)
            ->setTenant($tenant);
        $this->app->instance(TenantConfigService::class, $service);

        $callback = static function (#[TenantConfigAttribute('cache', 'default', 'database')] ?array $config) {
            return $config;
        };

        $this->assertSame($expected, $this->app->call($callback));
    }

    #[Test]
    public function returnsNullWhenStoreReturnsNull(): void
    {
        /** @var \Sprout\Contracts\Tenancy $tenancy */
        $tenancy = tenancy('tenants');
        $tenant  = TenantModel::factory()->createOne();
        $tenancy->setTenant($tenant);

        $store = Mockery::mock(ConfigStore::class, function (MockInterface $mock) use ($tenancy, $tenant) {
            $mock->shouldReceive('get')
                 ->with($tenancy, $tenant, 'cache', 'missing', null)
                 ->andReturnNull();
        });

        $manager = Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($store) {
            $mock->shouldReceive('get')->andReturn($store);
        });

        $service = (new TenantConfigService($this->app, $manager))
            ->setTenancy($tenancy)
            ->setTenant($tenant);
        $this->app->instance(TenantConfigService::class, $service);

        $callback = static function (#[TenantConfigAttribute('cache', 'missing')] ?array $config) {
            return $config;
        };

        $this->assertNull($this->app->call($callback));
    }

    /**
     * Isolation tests (mocked container): exercise resolve() directly to
     * lock the delegation contract independently of Laravel's contextual-
     * attribute machinery.
     */

    #[Test]
    public function resolveDelegatesToTheTenantConfigServiceWithProvidedStore(): void
    {
        $service  = $this->mockTenantConfigService();
        $expected = ['driver' => 'redis'];

        $service->shouldReceive('config')
                ->with('cache', 'default', null, 'database')
                ->andReturn($expected)
                ->once();

        $container = Mockery::mock(Container::class, function (MockInterface $mock) use ($service) {
            $mock->shouldReceive('make')->with(TenantConfigService::class)->andReturn($service)->once();
        });

        $attribute = new TenantConfigAttribute('cache', 'default', 'database');

        $this->assertSame($expected, $attribute->resolve($attribute, $container));
    }

    #[Test]
    public function resolveDelegatesToTheTenantConfigServiceWithNullStore(): void
    {
        $service  = $this->mockTenantConfigService();
        $expected = ['driver' => 'file'];

        $service->shouldReceive('config')
                ->with('cache', 'default', null, null)
                ->andReturn($expected)
                ->once();

        $container = Mockery::mock(Container::class, function (MockInterface $mock) use ($service) {
            $mock->shouldReceive('make')->with(TenantConfigService::class)->andReturn($service)->once();
        });

        $attribute = new TenantConfigAttribute('cache', 'default');

        $this->assertSame($expected, $attribute->resolve($attribute, $container));
    }

    #[Test]
    public function resolveReturnsNullWhenServiceReturnsNull(): void
    {
        $service = $this->mockTenantConfigService();

        $service->shouldReceive('config')
                ->with('cache', 'default', null, null)
                ->andReturnNull()
                ->once();

        $container = Mockery::mock(Container::class, function (MockInterface $mock) use ($service) {
            $mock->shouldReceive('make')->with(TenantConfigService::class)->andReturn($service)->once();
        });

        $attribute = new TenantConfigAttribute('cache', 'default');

        $this->assertNull($attribute->resolve($attribute, $container));
    }

    /**
     * Helper: builds a partial mock of TenantConfigService.
     *
     * `TenantConfigService` is a `final` class, so Mockery cannot create
     * a regular mock from the class name. Instead we construct a real
     * instance (with mocked Application and ConfigStoreManager dependencies)
     * and wrap it via Mockery::mock($realInstance) — Mockery's documented
     * pattern for partial-mocking final classes.
     *
     * Do not let TenantConfigService::__construct grow side effects that
     * touch Application — the Application mock here has no expectations.
     */
    private function mockTenantConfigService(): MockInterface
    {
        $app     = Mockery::mock(Application::class);
        $manager = Mockery::mock(ConfigStoreManager::class);

        return Mockery::mock(new TenantConfigService($app, $manager));
    }
}
