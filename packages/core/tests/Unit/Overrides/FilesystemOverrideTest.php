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
use Sprout\Overrides\FilesystemManagerOverride;
use Sprout\Overrides\FilesystemOverride;
use Sprout\Overrides\StackedOverride;
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
        $this->assertTrue(is_subclass_of(FilesystemManagerOverride::class, BootableServiceOverride::class));
    }

    #[Test]
    public function isRegisteredWithSproutCorrectly(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'filesystem' => [
                'driver'    => StackedOverride::class,
                'overrides' => [
                    FilesystemManagerOverride::class,
                    FilesystemOverride::class,
                ],
            ],
        ]);

        $this->assertFalse($sprout->overrides()->hasOverride('filesystem'));

        $sprout->overrides()->registerOverrides();

        $this->assertTrue($sprout->overrides()->hasOverride('filesystem'));
        $this->assertSame(StackedOverride::class, $sprout->overrides()->getOverrideClass('filesystem'));
        $this->assertTrue($sprout->overrides()->isOverrideBootable('filesystem'));
        $this->assertTrue($sprout->overrides()->hasOverrideBooted('filesystem'));
    }

    #[Test, DataProvider('filesystemResolvedDataProvider')]
    public function bootsCorrectly(bool $return, bool $overrideManager): void
    {
        if ($overrideManager) {
            $overrides = [
                FilesystemManagerOverride::class,
                FilesystemOverride::class,
            ];
        } else {
            $overrides = [
                FilesystemOverride::class,
            ];
        }

        $override = new StackedOverride('filesystem', compact('overrides'));

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

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        // These are only here because there would be errors if their
        // corresponding setters were not called
        $this->assertInstanceOf(Application::class, $override->getApp());
        $this->assertInstanceOf(Sprout::class, $override->getSprout());
    }

    #[Test]
    public function keepsTrackOfResolvedSproutDrivers(): void
    {
        $override = new StackedOverride('filesystem', [
            'overrides' => [
                FilesystemManagerOverride::class,
                FilesystemOverride::class,
            ],
        ]);

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

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        $filesystem = $app->make('filesystem');

        $filesystem->build([
            'driver' => 'sprout',
            'disk'   => 'local',
        ]);

        $this->assertNotEmpty($override->getOverrides()[FilesystemOverride::class]->getDrivers());
        $this->assertContains('ondemand', $override->getOverrides()[FilesystemOverride::class]->getDrivers());
    }

    #[Test]
    public function cleansUpResolvedDrivers(): void
    {
        $override = new StackedOverride('filesystem', [
            'overrides' => [
                FilesystemManagerOverride::class,
                FilesystemOverride::class,
            ],
        ]);

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

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        $filesystemOverride = $override->getOverride(FilesystemOverride::class);

        $this->assertEmpty($filesystemOverride->getDrivers());

        $filesystem = $app->make('filesystem');

        $filesystem->build([
            'driver' => 'sprout',
            'disk'   => 'local',
        ]);

        $this->assertNotEmpty($filesystemOverride->getDrivers());
        $this->assertContains('ondemand', $filesystemOverride->getDrivers());

        $override->cleanup($tenancy, $tenant);

        $this->assertEmpty($filesystemOverride->getDrivers());
    }

    #[Test]
    public function cleansUpResolvedDriversFromPreconfiguredDisks(): void
    {
        $override = new StackedOverride('filesystem', [
            'overrides' => [
                FilesystemManagerOverride::class,
                FilesystemOverride::class,
            ],
        ]);

        $this->app->forgetInstance('filesystem');

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $app->make('config')->set('filesystems.disks.my-disk', [
            'driver' => 'sprout',
            'disk'   => 'local',
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

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        $filesystemOverride = $override->getOverride(FilesystemOverride::class);

        $this->assertEmpty($filesystemOverride->getDrivers());

        $filesystem = $app->make('filesystem');

        $filesystem->disk('my-disk');

        $this->assertNotEmpty($filesystemOverride->getDrivers());
        $this->assertContains('my-disk', $filesystemOverride->getDrivers());

        $override->cleanup($tenancy, $tenant);

        $this->assertEmpty($filesystemOverride->getDrivers());
    }

    #[Test]
    public function cleansUpNothingWithoutResolvedDrivers(): void
    {
        $override = new StackedOverride('filesystem', [
            'overrides' => [
                FilesystemManagerOverride::class,
                FilesystemOverride::class,
            ],
        ]);

        $this->app->forgetInstance('filesystem');

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout  = new Sprout($app, new SettingsRepository());
        $tenant  = Mockery::mock(Tenant::class, TenantHasResources::class);
        $tenancy = Mockery::mock(Tenancy::class);

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        $filesystemOverride = $override->getOverride(FilesystemOverride::class);

        $this->assertEmpty($filesystemOverride->getDrivers());

        $app->make('filesystem');

        $this->assertEmpty($filesystemOverride->getDrivers());

        $override->cleanup($tenancy, $tenant);

        $this->assertEmpty($filesystemOverride->getDrivers());
    }

    public static function filesystemResolvedDataProvider(): array
    {
        return [
            'filesystem resolved no manager override'     => [true, false],
            'filesystem not resolved no manager override' => [false, false],
            'filesystem resolved manager override'        => [true, true],
            'filesystem not resolved  manager override'   => [false, true],
        ];
    }
}
