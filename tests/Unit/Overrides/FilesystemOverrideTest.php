<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Overrides;

use Closure;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemManager;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantHasResources;
use Sprout\Overrides\FilesystemOverride;
use Sprout\Sprout;
use Sprout\Support\SettingsRepository;
use Sprout\Tests\Unit\UnitTestCase;
use function Sprout\sprout;

class FilesystemOverrideTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('sprout.overrides', []);
        });
    }

    private function mockFilesystemManager(): FilesystemManager&MockInterface
    {
        return Mockery::mock(FilesystemManager::class, static function (MockInterface $mock) {
            $mock->shouldReceive('extend')
                 ->with('sprout', Mockery::on(static function ($arg) {
                     return is_callable($arg) && $arg instanceof Closure;
                 }))
                 ->once();
        });
    }

    #[Test]
    public function isBuiltCorrectly(): void
    {
        $this->assertTrue(is_subclass_of(FilesystemOverride::class, BootableServiceOverride::class));
    }

    #[Test]
    public function isRegisteredWithSproutCorrectly(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'filesystem' => [
                'driver' => FilesystemOverride::class,
            ],
        ]);

        $this->assertFalse($sprout->overrides()->hasOverride('filesystem'));

        $sprout->overrides()->registerOverrides();

        $this->assertTrue($sprout->overrides()->hasOverride('filesystem'));
        $this->assertSame(FilesystemOverride::class, $sprout->overrides()->getOverrideClass('filesystem'));
        $this->assertTrue($sprout->overrides()->isOverrideBootable('filesystem'));
        $this->assertTrue($sprout->overrides()->hasOverrideBooted('filesystem'));
    }

    #[Test, DataProvider('filesystemResolvedDataProvider')]
    public function bootsCorrectly(bool $return, bool $overrideManager): void
    {
        $override = new FilesystemOverride('filesystem', ['manager' => $overrideManager]);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, function (MockInterface $mock) use ($return, $overrideManager) {
            $mock->makePartial();

            if ($overrideManager) {
                if ($return) {
                    $mock->shouldReceive('forgetInstance')->with('filesystem')->once();
                }

                $mock->shouldReceive('singleton')
                     ->with('filesystem', Mockery::on(static function ($arg) {
                         return is_callable($arg) && $arg instanceof Closure;
                     }))
                     ->once();
            }

            $mock->shouldReceive('resolved')->withArgs(['filesystem'])->andReturn($return)->times($overrideManager ? 2 : 1);

            if ($return) {
                $mock->shouldReceive('make')
                     ->with('filesystem')
                     ->andReturn($this->mockFilesystemManager())
                     ->times($overrideManager ? 2 : 1);
            } else {
                $mock->shouldReceive('afterResolving')
                     ->withArgs([
                         'filesystem',
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
        $this->assertInstanceOf(Application::class, $override->getApp());
        $this->assertInstanceOf(Sprout::class, $override->getSprout());
    }

    #[Test]
    public function addsDriverCacheManagerHasBeenResolved(): void
    {
        $override = new FilesystemOverride('filesystem', [
            'manager' => false,
        ]);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $app->singleton('filesystem', fn () => $this->mockFilesystemManager());

        $sprout = new Sprout($app, new SettingsRepository());

        $override->boot($app, $sprout);

        $app->make('filesystem');
    }

    #[Test]
    public function keepsTrackOfResolvedSproutDrivers(): void
    {
        $override = new FilesystemOverride('filesystem', []);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout  = new Sprout($app, new SettingsRepository());
        $tenant  = Mockery::mock(Tenant::class, TenantHasResources::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getTenantResourceKey')->andReturn('my-resource-key')->once();
        });
        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) use ($tenant) {
            $mock->shouldReceive('check')->andReturnTrue()->once();
            $mock->shouldReceive('tenant')->andReturn($tenant)->once();
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->once();
        });

        $sprout->setCurrentTenancy($tenancy);

        $override->boot($app, $sprout);

        $filesystem = $app->make('filesystem');

        $filesystem->build([
            'driver' => 'sprout',
            'disk'   => 'local',
        ]);

        $this->assertNotEmpty($override->getDrivers());
        $this->assertContains('ondemand', $override->getDrivers());
    }

    #[Test]
    public function cleansUpResolvedDrivers(): void
    {
        $override = new FilesystemOverride('filesystem', []);

        $this->app->forgetInstance('filesystem');

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout  = new Sprout($app, new SettingsRepository());
        $tenant  = Mockery::mock(Tenant::class, TenantHasResources::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getTenantResourceKey')->andReturn('my-resource-key')->once();
        });
        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) use ($tenant) {
            $mock->shouldReceive('check')->andReturnTrue()->once();
            $mock->shouldReceive('tenant')->andReturn($tenant)->once();
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->once();
        });

        $sprout->setCurrentTenancy($tenancy);

        $override->boot($app, $sprout);

        $this->assertEmpty($override->getDrivers());

        $filesystem = $app->make('filesystem');

        $filesystem->build([
            'driver' => 'sprout',
            'disk'   => 'local',
        ]);

        $this->assertNotEmpty($override->getDrivers());
        $this->assertContains('ondemand', $override->getDrivers());

        $override->cleanup($tenancy, $tenant);

        $this->assertEmpty($override->getDrivers());
    }

    #[Test]
    public function cleansUpResolvedDriversFromPreconfiguredDisks(): void
    {
        $override = new FilesystemOverride('filesystem', []);

        $this->app->forgetInstance('filesystem');

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $app->make('config')->set('filesystems.disks.my-disk', [
            'driver' => 'sprout',
            'disk' => 'local'
        ]);

        $sprout  = new Sprout($app, new SettingsRepository());
        $tenant  = Mockery::mock(Tenant::class, TenantHasResources::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getTenantResourceKey')->andReturn('my-resource-key')->once();
        });
        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) use ($tenant) {
            $mock->shouldReceive('check')->andReturnTrue()->once();
            $mock->shouldReceive('tenant')->andReturn($tenant)->once();
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->once();
        });

        $sprout->setCurrentTenancy($tenancy);

        $override->boot($app, $sprout);

        $this->assertEmpty($override->getDrivers());

        $filesystem = $app->make('filesystem');

        $filesystem->disk('my-disk');

        $this->assertNotEmpty($override->getDrivers());
        $this->assertContains('my-disk', $override->getDrivers());

        $override->cleanup($tenancy, $tenant);

        $this->assertEmpty($override->getDrivers());
    }

    #[Test]
    public function cleansUpNothingWithoutResolvedDrivers(): void
    {
        $override = new FilesystemOverride('filesystem', []);

        $this->app->forgetInstance('filesystem');

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout  = new Sprout($app, new SettingsRepository());
        $tenant  = Mockery::mock(Tenant::class, TenantHasResources::class);
        $tenancy = Mockery::mock(Tenancy::class);

        $sprout->setCurrentTenancy($tenancy);

        $override->boot($app, $sprout);

        $this->assertEmpty($override->getDrivers());

        $app->make('filesystem');

        $this->assertEmpty($override->getDrivers());

        $override->cleanup($tenancy, $tenant);

        $this->assertEmpty($override->getDrivers());
    }

    public static function filesystemResolvedDataProvider(): array
    {
        return [
            'cache resolved no manager override'     => [true, false],
            'cache not resolved no manager override' => [false, false],
            'cache resolved manager override'        => [true, true],
            'cache not resolved  manager override'   => [false, true],
        ];
    }
}
