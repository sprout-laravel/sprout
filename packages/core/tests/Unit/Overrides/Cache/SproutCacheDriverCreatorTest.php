<?php
declare(strict_types=1);

namespace Sprout\Core\Tests\Unit\Overrides\Cache;

use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Contracts\Tenant;
use Sprout\Core\Exceptions\MisconfigurationException;
use Sprout\Core\Exceptions\TenancyMissingException;
use Sprout\Core\Exceptions\TenantMissingException;
use Sprout\Core\Overrides\Cache\SproutCacheDriverCreator;
use Sprout\Core\Sprout;
use Sprout\Core\Support\SettingsRepository;
use Sprout\Core\Tests\Unit\UnitTestCase;

class SproutCacheDriverCreatorTest extends UnitTestCase
{
    private function mockTenancy(bool $withTenant = true, bool $getsKey = true): Tenancy&Mockery\MockInterface
    {
        $tenant = Mockery::mock(Tenant::class, static function (Mockery\MockInterface $mock) use ($getsKey, $withTenant) {
            if ($withTenant && $getsKey) {
                $mock->shouldReceive('getTenantKey')->andReturn(7777777)->once();
            }
        });

        return Mockery::mock(Tenancy::class, static function (Mockery\MockInterface $mock) use ($getsKey, $withTenant, $tenant) {
            $mock->shouldReceive('check')->andReturn($withTenant)->once();

            if ($getsKey) {
                $mock->shouldReceive('getName')->andReturn('my-tenancy')->once();
            }

            if ($withTenant) {
                $mock->shouldReceive('tenant')->andReturn($tenant)->once();
            }
        });
    }

    #[Test]
    public function canCreateTheDriver(): void
    {
        /** @var \Illuminate\Foundation\Application&\Mockery\MockInterface $app */
        $app     = Mockery::mock($this->app, static function (Mockery\MockInterface $mock) {
            $mock->makePartial();

            $mock->shouldReceive('make')
                 ->with('config')
                 ->andReturn(Mockery::mock(Repository::class, static function (Mockery\MockInterface $mock) {
                     $mock->shouldReceive('get')
                          ->with('cache.stores.my-fake-store')
                          ->andReturn([
                              'driver' => 'null',
                              'prefix' => 'hello-there',
                          ])
                          ->once();
                 }));
        });
        $cache   = Mockery::mock(CacheManager::class, static function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('build')
                 ->andReturn(Mockery::mock(CacheRepository::class))
                 ->with(Mockery::on(static function ($arg) {
                     return is_array($arg)
                            && isset($arg['driver'])
                            && $arg['driver'] === 'null'
                            && isset($arg['prefix'])
                            && $arg['prefix'] === 'hello-there_my-tenancy_7777777';
                 }))
                 ->once();
        });
        $sprout  = new Sprout($app, new SettingsRepository());
        $creator = new SproutCacheDriverCreator(
            $app,
            $cache,
            ['override' => 'my-fake-store',],
            $sprout
        );

        $tenancy = $this->mockTenancy();

        $sprout->setCurrentTenancy($tenancy);

        $store = $creator();

        $this->assertInstanceOf(CacheRepository::class, $store);
    }

    #[Test]
    public function throwsAnExceptionWhenOutsideMultitenantedContext(): void
    {
        /** @var \Illuminate\Foundation\Application&\Mockery\MockInterface $app */
        $app     = Mockery::mock(Application::class);
        $cache   = Mockery::mock(CacheManager::class);
        $sprout  = new Sprout($app, new SettingsRepository());
        $creator = new SproutCacheDriverCreator(
            $app,
            $cache,
            ['override' => 'my-fake-store',],
            $sprout
        );

        $this->expectException(TenancyMissingException::class);
        $this->expectExceptionMessage('There is no current tenancy');

        $creator();
    }

    #[Test]
    public function throwsAnExceptionWhenThereIsNoTenancy(): void
    {
        /** @var \Illuminate\Foundation\Application&\Mockery\MockInterface $app */
        $app     = Mockery::mock(Application::class);
        $cache   = Mockery::mock(CacheManager::class);
        $sprout  = new Sprout($app, new SettingsRepository());
        $creator = new SproutCacheDriverCreator(
            $app,
            $cache,
            ['override' => 'my-fake-store',],
            $sprout
        );

        $sprout->markAsInContext();

        $this->expectException(TenancyMissingException::class);
        $this->expectExceptionMessage('There is no current tenancy');

        $creator();
    }

    #[Test]
    public function throwsAnExceptionWhenThereIsNoTenant(): void
    {
        /** @var \Illuminate\Foundation\Application&\Mockery\MockInterface $app */
        $app     = Mockery::mock(Application::class, static function (Mockery\MockInterface $mock) {
            $mock->shouldIgnoreMissing();
        });
        $cache   = Mockery::mock(CacheManager::class);
        $sprout  = new Sprout($app, new SettingsRepository());
        $creator = new SproutCacheDriverCreator(
            $app,
            $cache,
            ['override' => 'my-fake-store',],
            $sprout
        );

        $sprout->setCurrentTenancy($this->mockTenancy(false));

        $this->expectException(TenantMissingException::class);
        $this->expectExceptionMessage('There is no current tenant for tenancy [my-tenancy]');

        $creator();
    }

    #[Test]
    public function throwsAnExceptionWhenThereIsNoOverride(): void
    {
        /** @var \Illuminate\Foundation\Application&\Mockery\MockInterface $app */
        $app     = Mockery::mock(Application::class, static function (Mockery\MockInterface $mock) {
            $mock->shouldIgnoreMissing();
        });
        $cache   = Mockery::mock(CacheManager::class);
        $sprout  = new Sprout($app, new SettingsRepository());
        $creator = new SproutCacheDriverCreator(
            $app,
            $cache,
            [],
            $sprout
        );

        $sprout->setCurrentTenancy($this->mockTenancy(true, false));

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The service override [cache] is missing a required value for \'override\'');

        $creator();
    }

    #[Test]
    public function throwsAnExceptionWhenTheOverrideIsNotConfigured(): void
    {
        /** @var \Illuminate\Foundation\Application&\Mockery\MockInterface $app */
        $app     = Mockery::mock(Application::class, static function (Mockery\MockInterface $mock) {
            $mock->shouldIgnoreMissing();

            $mock->shouldReceive('make')
                 ->with('config')
                 ->andReturn(Mockery::mock(Repository::class, static function (Mockery\MockInterface $mock) {
                     $mock->shouldReceive('get')
                          ->with('cache.stores.my-fake-store')
                          ->andReturnNull()
                          ->once();
                 }));
        });
        $cache   = Mockery::mock(CacheManager::class);
        $sprout  = new Sprout($app, new SettingsRepository());
        $creator = new SproutCacheDriverCreator(
            $app,
            $cache,
            ['override' => 'my-fake-store'],
            $sprout
        );

        $sprout->setCurrentTenancy($this->mockTenancy(true, false));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cache store [my-fake-store] is not defined');

        $creator();
    }
}
