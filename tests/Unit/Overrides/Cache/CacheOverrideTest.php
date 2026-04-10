<?php
declare(strict_types=1);

namespace Sprout\Core\Tests\Unit\Overrides;

use Closure;
use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Core\Contracts\BootableServiceOverride;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Contracts\Tenant;
use Sprout\Core\Overrides\CacheOverride;
use Sprout\Core\Sprout;
use Sprout\Core\Support\SettingsRepository;
use Sprout\Core\Tests\Unit\UnitTestCase;
use function Sprout\Core\sprout;

class CacheOverrideTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('sprout.overrides', []);
        });
    }

    private function mockCacheManager(): CacheManager&MockInterface
    {
        /** @var CacheManager&MockInterface $app */
        $app = Mockery::mock(CacheManager::class, static function (MockInterface $mock) {
            $mock->shouldReceive('extend')
                 ->withArgs([
                     'sprout',
                     Mockery::on(static function ($arg) {
                         return is_callable($arg) && $arg instanceof Closure;
                     }),
                 ])
                 ->once();
        });

        return $app;
    }

    #[Test]
    public function isBuiltCorrectly(): void
    {
        $this->assertTrue(is_subclass_of(CacheOverride::class, BootableServiceOverride::class));
    }

    #[Test]
    public function isRegisteredWithSproutCorrectly(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'cache' => [
                'driver' => CacheOverride::class,
            ],
        ]);

        $this->assertFalse($sprout->overrides()->hasOverride('cache'));

        $sprout->overrides()->registerOverrides();

        $this->assertTrue($sprout->overrides()->hasOverride('cache'));
        $this->assertSame(CacheOverride::class, $sprout->overrides()->getOverrideClass('cache'));
        $this->assertTrue($sprout->overrides()->isOverrideBootable('cache'));
        $this->assertTrue($sprout->overrides()->hasOverrideBooted('cache'));
    }

    #[Test, DataProvider('cacheResolvedDataProvider')]
    public function bootsCorrectly(bool $return): void
    {
        $override = new CacheOverride('cache', []);

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

        $override->boot($app, $sprout);

        // These are only here because there would be errors if their
        // corresponding setters were not called
        $this->assertInstanceOf(\Illuminate\Contracts\Foundation\Application::class, $override->getApp());
        $this->assertInstanceOf(Sprout::class, $override->getSprout());
    }

    #[Test]
    public function addsDriverCacheManagerHasBeenResolved(): void
    {
        $override = new CacheOverride('cache', []);

        $app = Mockery::mock(Application::class, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $app->singleton('cache', function () {
            return $this->mockCacheManager();
        });

        $sprout = new Sprout($app, new SettingsRepository());

        $override->boot($app, $sprout);

        $app->make('cache');
    }

    #[Test]
    public function keepsTrackOfResolvedSproutDrivers(): void
    {
        $override = new CacheOverride('cache', []);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout  = new Sprout($app, new SettingsRepository());
        $tenant  = Mockery::mock(Tenant::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getTenantKey')->andReturn(7777)->once();
        });
        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) use ($tenant) {
            $mock->shouldReceive('check')->andReturnTrue()->once();
            $mock->shouldReceive('tenant')->andReturn($tenant)->once();
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->once();
        });

        $sprout->setCurrentTenancy($tenancy);

        $override->boot($app, $sprout);

        $cache = $app->make('cache');

        $cache->build([
            'driver'   => 'sprout',
            'override' => 'array',
        ]);

        $this->assertNotEmpty($override->getDrivers());
        $this->assertContains('ondemand', $override->getDrivers());
    }

    #[Test]
    public function cleansUpResolvedDrivers(): void
    {
        $override = new CacheOverride('cache', []);
        $cache = Mockery::mock($this->app->make('cache'), static function (MockInterface $mock) {
            $mock->makePartial();
            $mock->shouldReceive('forgetDriver')->once();
        });
        $this->app->forgetInstance('cache');

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) use ($cache) {
            $mock->makePartial();

            $mock->shouldReceive('make')
                 ->with('cache')
                 ->andReturn($cache);
        });

        $sprout  = new Sprout($app, new SettingsRepository());
        $tenant  = Mockery::mock(Tenant::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getTenantKey')->andReturn(7777)->once();
        });
        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) use ($tenant) {
            $mock->shouldReceive('check')->andReturnTrue()->once();
            $mock->shouldReceive('tenant')->andReturn($tenant)->once();
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->once();
        });

        $sprout->setCurrentTenancy($tenancy);

        $override->boot($app, $sprout);

        $cache->build([
            'driver'   => 'sprout',
            'override' => 'array',
        ]);

        $this->assertNotEmpty($override->getDrivers());
        $this->assertContains('ondemand', $override->getDrivers());

        $override->cleanup($tenancy, $tenant);

        $this->assertEmpty($override->getDrivers());
    }

    #[Test]
    public function cleansUpNothingWithoutResolvedDrivers(): void
    {
        $override = new CacheOverride('cache', []);
        $cache = Mockery::mock($this->app->make('cache'), static function (MockInterface $mock) {
            $mock->makePartial();
            $mock->shouldNotReceive('forgetDriver');
        });
        $this->app->forgetInstance('cache');

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) use ($cache) {
            $mock->makePartial();

            $mock->shouldReceive('make')
                 ->with('cache')
                 ->andReturn($cache);
        });

        $sprout  = new Sprout($app, new SettingsRepository());
        $tenant  = Mockery::mock(Tenant::class);
        $tenancy = Mockery::mock(Tenancy::class);

        $sprout->setCurrentTenancy($tenancy);

        $override->boot($app, $sprout);

        $this->assertEmpty($override->getDrivers());

        $override->cleanup($tenancy, $tenant);
    }

    public static function cacheResolvedDataProvider(): array
    {
        return [
            'cache resolved'     => [true],
            'cache not resolved' => [false],
        ];
    }
}
