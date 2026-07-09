<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Overrides\Cache;

use Closure;
use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Foundation\Application;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\ConfigStore;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantHasResources;
use Sprout\Exceptions\CyclicOverrideException;
use Sprout\Managers\ConfigStoreManager;
use Sprout\Overrides\Cache\TenantConfigCacheStoreOverride;
use Sprout\Sprout;
use Sprout\Support\SettingsRepository;
use Sprout\TenantConfig;
use Sprout\Tests\Unit\UnitTestCase;

use function Sprout\sprout;

class TenantConfigCacheStoreOverrideTest extends UnitTestCase
{
    public static function cacheResolvedDataProvider(): array
    {
        return [
            'cache resolved'     => [true],
            'cache not resolved' => [false],
        ];
    }

    #[Test]
    public function isBuiltCorrectly(): void
    {
        $this->assertTrue(is_subclass_of(TenantConfigCacheStoreOverride::class, BootableServiceOverride::class));
    }

    #[Test]
    public function isRegisteredWithSproutCorrectly(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'cache' => [
                'driver' => TenantConfigCacheStoreOverride::class,
            ],
        ]);

        $this->assertFalse($sprout->overrides()->hasOverride('cache'));

        $sprout->overrides()->registerOverrides();

        $this->assertTrue($sprout->overrides()->hasOverride('cache'));
        $this->assertSame(TenantConfigCacheStoreOverride::class, $sprout->overrides()->getOverrideClass('cache'));
        $this->assertTrue($sprout->overrides()->isOverrideBootable('cache'));
        $this->assertTrue($sprout->overrides()->hasOverrideBooted('cache'));
    }

    #[Test, DataProvider('cacheResolvedDataProvider')]
    public function bootsCorrectly(bool $return): void
    {
        $override = new TenantConfigCacheStoreOverride('cache', []);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, function (MockInterface $mock) use ($return) {
            $mock->makePartial();
            $mock->shouldReceive('resolved')->withArgs(['cache'])->andReturn($return)->once();

            if ($return) {
                $mock->shouldReceive('make')
                     ->with('cache')
                     ->andReturn($this->mockCacheManager())
                     ->once();
            } else {
                $mock->shouldReceive('afterResolving')
                     ->withArgs([
                         'cache',
                         Mockery::on(static function ($arg) {
                             return is_callable($arg) && $arg instanceof Closure;
                         }),
                     ])
                     ->once();
            }
        });

        $sprout = new Sprout($app, new SettingsRepository());

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        // These are only here because there would be errors if their
        // corresponding setters were not called
        $this->assertInstanceOf(Application::class, $override->getApp());
        $this->assertInstanceOf(Sprout::class, $override->getSprout());
    }

    #[Test]
    public function errorsIfOverriddenCacheStoreAlsoUsesConfig(): void
    {
        $override = new TenantConfigCacheStoreOverride('cache', []);

        $tenant = Mockery::mock(Tenant::class, TenantHasResources::class, static function (MockInterface $mock) {
        });
        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) use ($tenant) {
            $mock->shouldReceive('check')->andReturnTrue()->once();
            $mock->shouldReceive('tenant')->andReturn($tenant)->once();
        });

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $app->make('config')->set('multitenancy.defaults.config', 'filesystem');
        $app->make('config')->set('cache.stores.tenant-cache', [
            'driver' => 'sprout:config',
        ]);

        $app->singleton(TenantConfig::class, fn () => new TenantConfig($app, Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($tenancy, $tenant) {
            $mock->shouldReceive('get')
                 ->andReturn(Mockery::mock(ConfigStore::class, static function (MockInterface $mock) use ($tenancy, $tenant) {
                     $mock->shouldReceive('get')
                          ->with(
                              $tenancy,
                              $tenant,
                              'cache',
                              'tenant-cache',
                          )->andReturn([
                              'driver' => 'sprout:config',
                          ]);
                 }));
        })));

        $sprout = new Sprout($app, new SettingsRepository());

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        /** @var CacheManager $manager */
        $manager = $app->make('cache');

        $this->expectException(CyclicOverrideException::class);
        $this->expectExceptionMessage('Attempt to create cyclic config cache store [tenant-cache] detected');

        $manager->store('tenant-cache');
    }

    #[Test]
    public function keepsTrackOfResolvedConfigDrivers(): void
    {
        $override = new TenantConfigCacheStoreOverride('cache', []);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $app->make('config')->set('multitenancy.defaults.config', 'filesystem');
        $app->make('config')->set('cache.stores.tenant-cache', [
            'driver' => 'sprout:config',
        ]);

        $tenant = Mockery::mock(Tenant::class, TenantHasResources::class, static function (MockInterface $mock) {
        });

        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) use ($tenant) {
            $mock->shouldReceive('check')->andReturnTrue()->once();
            $mock->shouldReceive('tenant')->andReturn($tenant)->once();
        });

        $app->singleton(TenantConfig::class, fn () => new TenantConfig($app, Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($tenancy, $tenant) {
            $mock->shouldReceive('get')
                 ->andReturn(Mockery::mock(ConfigStore::class, function (MockInterface $mock) use ($tenancy, $tenant) {
                     $mock->shouldReceive('get')
                          ->with(
                              $tenancy,
                              $tenant,
                              'cache',
                              'tenant-cache',
                          )->andReturn([
                              'driver'    => 'array',
                              'serialize' => false,
                          ]);
                 }));
        })));

        $sprout = new Sprout($app, new SettingsRepository());

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        /** @var CacheManager $manager */
        $manager = $app->make('cache');

        $store = $manager->store('tenant-cache');

        // The store closure must actually return the created store
        $this->assertInstanceOf(CacheRepository::class, $store);

        $this->assertContains('tenant-cache', $override->getOverrides());
    }

    #[Test]
    public function cleansUpResolvedDrivers(): void
    {
        $override = new TenantConfigCacheStoreOverride('cache', []);

        $this->app->forgetInstance('cache');

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $app->make('config')->set('multitenancy.defaults.config', 'filesystem');
        $app->make('config')->set('cache.stores.tenant-cache', [
            'driver' => 'sprout:config',
        ]);

        $tenant = Mockery::mock(Tenant::class, TenantHasResources::class, static function (MockInterface $mock) {
        });

        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) use ($tenant) {
            $mock->shouldReceive('check')->andReturnTrue()->once();
            $mock->shouldReceive('tenant')->andReturn($tenant)->once();
        });

        $app->singleton(TenantConfig::class, fn () => new TenantConfig($app, Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($tenancy, $tenant) {
            $mock->shouldReceive('get')
                 ->andReturn(Mockery::mock(ConfigStore::class, function (MockInterface $mock) use ($tenancy, $tenant) {
                     $mock->shouldReceive('get')
                          ->with(
                              $tenancy,
                              $tenant,
                              'cache',
                              'tenant-cache',
                          )->andReturn([
                              'driver'    => 'array',
                              'serialize' => false,
                          ]);
                 }));
        })));

        $sprout = new Sprout($app, new SettingsRepository());

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        $this->assertEmpty($override->getOverrides());

        // Wrap the real cache manager so we can assert purge() is invoked
        // during cleanup for the resolved store.
        $manager = Mockery::mock($app->make('cache'), static function (MockInterface $mock) {
            $mock->makePartial();
            $mock->shouldReceive('purge')->with('tenant-cache')->once();
        });

        $app->instance('cache', $manager);

        $manager->store('tenant-cache');

        $this->assertNotEmpty($override->getOverrides());
        $this->assertContains('tenant-cache', $override->getOverrides());

        $override->cleanup($tenancy, $tenant);

        $this->assertEmpty($override->getOverrides());
    }

    #[Test]
    public function cleansUpNothingWithoutResolvedDrivers(): void
    {
        $override = new TenantConfigCacheStoreOverride('cache', []);

        $this->app->forgetInstance('cache');

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $app->make('config')->set('multitenancy.defaults.config', 'filesystem');
        $app->make('config')->set('cache.stores.tenant-cache', [
            'driver' => 'sprout:config',
        ]);

        $sprout  = new Sprout($app, new SettingsRepository());
        $tenant  = Mockery::mock(Tenant::class, TenantHasResources::class);
        $tenancy = Mockery::mock(Tenancy::class);

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        $this->assertEmpty($override->getOverrides());

        $app->make('cache');

        $this->assertEmpty($override->getOverrides());

        $override->cleanup($tenancy, $tenant);

        $this->assertEmpty($override->getOverrides());
    }

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('sprout.overrides', []);
        });
    }

    private function mockCacheManager(): CacheManager&MockInterface
    {
        return Mockery::mock(CacheManager::class, static function (MockInterface $mock) {
            $mock->shouldReceive('extend')
                 ->with('sprout:config', Mockery::on(static function ($arg) {
                     return is_callable($arg) && $arg instanceof Closure;
                 }))
                 ->once();
        });
    }
}
