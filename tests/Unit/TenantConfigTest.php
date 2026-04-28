<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit;

use Illuminate\Contracts\Foundation\Application;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\ConfigStore;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Exceptions\TenancyMissingException;
use Sprout\Exceptions\TenantMissingException;
use Sprout\Managers\ConfigStoreManager;
use Sprout\TenancyOptions;
use Sprout\TenantConfig;

class TenantConfigTest extends UnitTestCase
{
    protected function mockApp(): Application&MockInterface
    {
        return Mockery::mock(Application::class);
    }

    #[Test]
    public function constructorAcceptsAnExplicitConfigStoreManager(): void
    {
        $app     = $this->mockApp();
        $manager = Mockery::mock(ConfigStoreManager::class);

        $config = new TenantConfig($app, $manager);

        $this->assertSame($manager, $config->stores());
    }

    #[Test]
    public function constructorBuildsADefaultConfigStoreManagerWhenNoneProvided(): void
    {
        $app = $this->mockApp();

        $config = new TenantConfig($app);

        $this->assertInstanceOf(ConfigStoreManager::class, $config->stores());
    }

    #[Test]
    public function storeWithoutTenancyDelegatesToManagerWithProvidedName(): void
    {
        $app   = $this->mockApp();
        $store = Mockery::mock(ConfigStore::class);

        $manager = Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($store) {
            $mock->shouldReceive('get')->with('explicit')->andReturn($store)->once();
        });

        $config = new TenantConfig($app, $manager);

        $this->assertSame($store, $config->store('explicit'));
    }

    #[Test]
    public function storeWithoutTenancyDelegatesNullToManagerWhenNoNameProvided(): void
    {
        $app   = $this->mockApp();
        $store = Mockery::mock(ConfigStore::class);

        $manager = Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($store) {
            $mock->shouldReceive('get')->with(null)->andReturn($store)->once();
        });

        $config = new TenantConfig($app, $manager);

        $this->assertSame($store, $config->store());
    }

    #[Test]
    public function storeWithLockedTenancyOptionUsesTheLockedStoreName(): void
    {
        $app   = $this->mockApp();
        $store = Mockery::mock(ConfigStore::class);

        $tenancy = Mockery::mock(Tenancy::class, function (MockInterface $mock) {
            $mock->shouldReceive('optionConfig')->with('config:store.fixed')->andReturn('locked')->atLeast()->once();
        });

        $manager = Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($store) {
            $mock->shouldReceive('get')->with('locked')->andReturn($store)->once();
        });

        $config = new TenantConfig($app, $manager);
        $config->setTenancy($tenancy);

        // Lock wins over both the provided name and any default.
        $this->assertSame($store, $config->store('ignored-because-locked'));
    }

    #[Test]
    public function storeWithoutLockUsesTheProvidedNameWhenSupplied(): void
    {
        $app   = $this->mockApp();
        $store = Mockery::mock(ConfigStore::class);

        $tenancy = Mockery::mock(Tenancy::class, function (MockInterface $mock) {
            $mock->shouldReceive('optionConfig')->with('config:store.fixed')->andReturnNull()->atLeast()->once();
        });

        $manager = Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($store) {
            $mock->shouldReceive('get')->with('explicit')->andReturn($store)->once();
        });

        $config = new TenantConfig($app, $manager);
        $config->setTenancy($tenancy);

        $this->assertSame($store, $config->store('explicit'));
    }

    #[Test]
    public function storeFallsBackToTheTenancysDefaultWhenNeitherLockNorNameProvided(): void
    {
        $app   = $this->mockApp();
        $store = Mockery::mock(ConfigStore::class);

        $tenancy = Mockery::mock(Tenancy::class, function (MockInterface $mock) {
            $mock->shouldReceive('optionConfig')->with('config:store.fixed')->andReturnNull()->atLeast()->once();
            $mock->shouldReceive('optionConfig')->with('config:store.default')->andReturn('tenancy-default')->atLeast()->once();
        });

        $manager = Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($store) {
            $mock->shouldReceive('get')->with('tenancy-default')->andReturn($store)->once();
        });

        $config = new TenantConfig($app, $manager);
        $config->setTenancy($tenancy);

        $this->assertSame($store, $config->store());
    }

    #[Test]
    public function storePassesNullToManagerWhenTenancyHasNoLockNoNameAndNoDefault(): void
    {
        $app   = $this->mockApp();
        $store = Mockery::mock(ConfigStore::class);

        $tenancy = Mockery::mock(Tenancy::class, function (MockInterface $mock) {
            $mock->shouldReceive('optionConfig')->with('config:store.fixed')->andReturnNull()->atLeast()->once();
            $mock->shouldReceive('optionConfig')->with('config:store.default')->andReturnNull()->atLeast()->once();
        });

        $manager = Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($store) {
            $mock->shouldReceive('get')->with(null)->andReturn($store)->once();
        });

        $config = new TenantConfig($app, $manager);
        $config->setTenancy($tenancy);

        $this->assertSame($store, $config->store());
    }

    #[Test]
    public function configThrowsTenancyMissingExceptionWhenNoTenancyBound(): void
    {
        $app     = $this->mockApp();
        $manager = Mockery::mock(ConfigStoreManager::class);

        $config = new TenantConfig($app, $manager);

        $this->expectException(TenancyMissingException::class);

        $config->config('cache', 'default');
    }

    #[Test]
    public function configThrowsTenantMissingExceptionWhenTenancyHasNoTenant(): void
    {
        $app = $this->mockApp();

        $tenancy = Mockery::mock(Tenancy::class, function (MockInterface $mock) {
            $mock->shouldReceive('getName')->andReturn('tenants')->atLeast()->once();
        });

        $manager = Mockery::mock(ConfigStoreManager::class);

        $config = new TenantConfig($app, $manager);
        $config->setTenancy($tenancy);
        // Note: no tenant set — hasTenant() returns false

        $this->expectException(TenantMissingException::class);

        $config->config('cache', 'default');
    }

    #[Test]
    public function configHappyPathDelegatesToTheStoreGetMethod(): void
    {
        $app    = $this->mockApp();
        $tenant = Mockery::mock(Tenant::class);

        $tenancy = Mockery::mock(Tenancy::class, function (MockInterface $mock) {
            $mock->shouldReceive('optionConfig')->with('config:store.fixed')->andReturnNull()->atLeast()->once();
            $mock->shouldReceive('optionConfig')->with('config:store.default')->andReturnNull()->atLeast()->once();
        });

        $expectedConfig = ['driver' => 'redis', 'connection' => 'cache'];

        $store = Mockery::mock(ConfigStore::class, function (MockInterface $mock) use ($tenancy, $tenant, $expectedConfig) {
            $mock->shouldReceive('get')
                 ->with($tenancy, $tenant, 'cache', 'default', null)
                 ->andReturn($expectedConfig)
                 ->once();
        });

        $manager = Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($store) {
            $mock->shouldReceive('get')->with(null)->andReturn($store)->once();
        });

        $config = new TenantConfig($app, $manager);
        $config->setTenancy($tenancy);
        $config->setTenant($tenant);

        $this->assertSame($expectedConfig, $config->config('cache', 'default'));
    }
}
