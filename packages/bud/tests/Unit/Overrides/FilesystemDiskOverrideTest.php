<?php
declare(strict_types=1);

namespace Sprout\Bud\Tests\Unit\Overrides;

use Closure;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemManager;
use LogicException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Sprout\Bud\Bud;
use Sprout\Bud\Contracts\ConfigStore;
use Sprout\Bud\Exceptions\CyclicOverrideException;
use Sprout\Bud\Managers\ConfigStoreManager;
use Sprout\Bud\Overrides\FilesystemDiskOverride;
use Sprout\Bud\Tests\Unit\UnitTestCase;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantHasResources;
use Sprout\Overrides\Filesystem\SproutFilesystemManager;
use Sprout\Overrides\FilesystemManagerOverride;
use Sprout\Overrides\StackedOverride;
use Sprout\Sprout;
use Sprout\Support\SettingsRepository;
use function Sprout\sprout;

class FilesystemDiskOverrideTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('sprout.overrides', []);
        });
    }

    private function mockFilesystemManager(): FilesystemManager&MockInterface
    {
        return Mockery::mock(SproutFilesystemManager::class, static function (MockInterface $mock) {
            $mock->shouldReceive('extend')
                 ->with('bud', Mockery::on(static function ($arg) {
                     return is_callable($arg) && $arg instanceof Closure;
                 }))
                 ->once();
        });
    }

    #[Test]
    public function isBuiltCorrectly(): void
    {
        $this->assertTrue(is_subclass_of(FilesystemDiskOverride::class, BootableServiceOverride::class));
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
                    FilesystemDiskOverride::class,
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
    public function bootsCorrectly(bool $return): void
    {
        $overrides = [
            FilesystemManagerOverride::class,
            FilesystemDiskOverride::class,
        ];

        $override = new StackedOverride('filesystem', compact('overrides'));

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, function (MockInterface $mock) use ($return) {
            $mock->makePartial();

            if ($return) {
                $mock->shouldReceive('forgetInstance')->with('filesystem')->once();
            }

            $mock->shouldReceive('singleton')
                 ->with('filesystem', Mockery::on(static function ($arg) {
                     return is_callable($arg) && $arg instanceof Closure;
                 }))
                 ->once();

            $mock->shouldReceive('resolved')->withArgs(['filesystem'])->andReturn($return)->times(2);

            if ($return) {
                $mock->shouldReceive('make')
                     ->with('filesystem')
                     ->andReturn($this->mockFilesystemManager())
                     ->times(2);
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
    public function errorsWithoutManager(): void
    {
        $override = new StackedOverride('filesystem', [
            'overrides' => [
                FilesystemDiskOverride::class,
            ],
        ]);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, function (MockInterface $mock) {
            $mock->makePartial();
        });

        // We have to bind the mock so that the extension can be registered.
        $app->singleton('filesystem', fn () => Mockery::mock(FilesystemManager::class));

        $sprout = new Sprout($app, new SettingsRepository());

        $override->setApp($app)->setSprout($sprout);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot override filesystem disks without the Sprout filesystem manager override');

        // This is important, otherwise it doesn't behave nicely with the
        // afterResolving method.
        $app->make('filesystem');

        $override->boot($app, $sprout);
    }

    #[Test]
    public function errorsWithNoTenantSpecificConfig(): void
    {
        $override = new StackedOverride('filesystem', [
            'overrides' => [
                FilesystemManagerOverride::class,
                FilesystemDiskOverride::class,
            ],
        ]);

        $tenant = Mockery::mock(Tenant::class, TenantHasResources::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getTenantIdentifier')->andReturn('my-tenant')->once();
        });

        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) use ($tenant) {
            $mock->shouldReceive('check')->andReturnTrue()->once();
            $mock->shouldReceive('tenant')->andReturn($tenant)->once();
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->once();
        });

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) use ($tenancy, $tenant) {
            $mock->makePartial();
        });

        $app->make('config')->set('multitenancy.defaults.config', 'filesystem');
        $app->make('config')->set('filesystems.disks.bud-disk', [
            'driver' => 'bud',
        ]);

        $app->singleton(Bud::class, fn () => new Bud($app, Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($tenancy, $tenant) {
            $mock->shouldReceive('get')
                 ->andReturn(Mockery::mock(ConfigStore::class, function (MockInterface $mock) use ($tenancy, $tenant) {
                     $mock->shouldReceive('get')
                          ->with(
                              $tenancy,
                              $tenant,
                              'filesystem',
                              'bud-disk',
                          )->andReturn(null);
                 }));
        })));

        $sprout = new Sprout($app, new SettingsRepository());

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        /** @var \Sprout\Overrides\Filesystem\SproutFilesystemManager $manager */
        $manager = $app->make('filesystem');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to find configuration for [filesystem.bud-disk] for tenant [my-tenant] on tenancy [my-tenancy]');

        $manager->disk('bud-disk');
    }

    #[Test]
    public function errorsIfOverriddenConnectionAlsoUsesBud(): void
    {
        $override = new StackedOverride('filesystem', [
            'overrides' => [
                FilesystemManagerOverride::class,
                FilesystemDiskOverride::class,
            ],
        ]);

        $tenant  = Mockery::mock(Tenant::class, TenantHasResources::class, static function (MockInterface $mock) {
        });
        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) use ($tenant) {
            $mock->shouldReceive('check')->andReturnTrue()->once();
            $mock->shouldReceive('tenant')->andReturn($tenant)->once();
        });

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) use ($tenancy, $tenant) {
            $mock->makePartial();
        });

        $app->make('config')->set('multitenancy.defaults.config', 'filesystem');
        $app->make('config')->set('filesystems.disks.bud-disk', [
            'driver' => 'bud',
        ]);

        $app->singleton(Bud::class, fn () => new Bud($app, Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($tenancy, $tenant) {
            $mock->shouldReceive('get')
                 ->andReturn(Mockery::mock(ConfigStore::class, function (MockInterface $mock) use ($tenancy, $tenant) {
                     $mock->shouldReceive('get')
                          ->with(
                              $tenancy,
                              $tenant,
                              'filesystem',
                              'bud-disk',
                          )->andReturn([
                             'driver' => 'bud',
                         ]);
                 }));
        })));

        $sprout = new Sprout($app, new SettingsRepository());

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        /** @var \Sprout\Overrides\Filesystem\SproutFilesystemManager $manager */
        $manager = $app->make('filesystem');

        $this->expectException(CyclicOverrideException::class);
        $this->expectExceptionMessage('Attempt to create cyclic bud filesystem disk [bud-disk] detected');

        $manager->disk('bud-disk');
    }

    #[Test]
    public function keepsTrackOfResolvedBudDrivers(): void
    {
        $override = new StackedOverride('filesystem', [
            'overrides' => [
                FilesystemManagerOverride::class,
                FilesystemDiskOverride::class,
            ],
        ]);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $app->make('config')->set('multitenancy.defaults.config', 'filesystem');
        $app->make('config')->set('filesystems.disks.bud-disk', [
            'driver' => 'bud',
        ]);

        $tenant = Mockery::mock(Tenant::class, TenantHasResources::class, static function (MockInterface $mock) {
        });

        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) use ($tenant) {
            $mock->shouldReceive('check')->andReturnTrue()->once();
            $mock->shouldReceive('tenant')->andReturn($tenant)->once();
        });

        $app->singleton(Bud::class, fn () => new Bud($app, Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($tenancy, $tenant) {
            $mock->shouldReceive('get')
                 ->andReturn(Mockery::mock(ConfigStore::class, function (MockInterface $mock) use ($tenancy, $tenant) {
                     $mock->shouldReceive('get')
                          ->with(
                              $tenancy,
                              $tenant,
                              'filesystem',
                              'bud-disk',
                          )->andReturn([
                             'driver' => 'local',
                             'root'   => storage_path('app'),
                             'throw'  => false,
                         ]);
                 }));
        })));

        $sprout = new Sprout($app, new SettingsRepository());

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        /** @var \Sprout\Overrides\Filesystem\SproutFilesystemManager $filesystem */
        $filesystem = $app->make('filesystem');

        $filesystem->disk('bud-disk');

        $this->assertNotEmpty($override->getOverrides()[FilesystemDiskOverride::class]->getOverrides());
        $this->assertContains('bud-disk', $override->getOverrides()[FilesystemDiskOverride::class]->getOverrides());
    }

    #[Test]
    public function cleansUpResolvedDrivers(): void
    {
        $override = new StackedOverride('filesystem', [
            'overrides' => [
                FilesystemManagerOverride::class,
                FilesystemDiskOverride::class,
            ],
        ]);

        $this->app->forgetInstance('filesystem');

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $app->make('config')->set('multitenancy.defaults.config', 'filesystem');
        $app->make('config')->set('filesystems.disks.bud-disk', [
            'driver' => 'bud',
        ]);

        $tenant = Mockery::mock(Tenant::class, TenantHasResources::class, static function (MockInterface $mock) {
        });

        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) use ($tenant) {
            $mock->shouldReceive('check')->andReturnTrue()->once();
            $mock->shouldReceive('tenant')->andReturn($tenant)->once();
        });

        $app->singleton(Bud::class, fn () => new Bud($app, Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($tenancy, $tenant) {
            $mock->shouldReceive('get')
                 ->andReturn(Mockery::mock(ConfigStore::class, function (MockInterface $mock) use ($tenancy, $tenant) {
                     $mock->shouldReceive('get')
                          ->with(
                              $tenancy,
                              $tenant,
                              'filesystem',
                              'bud-disk',
                          )->andReturn([
                             'driver' => 'local',
                             'root'   => storage_path('app'),
                             'throw'  => false,
                         ]);
                 }));
        })));

        $sprout  = new Sprout($app, new SettingsRepository());

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        $filesystemOverride = $override->getOverride(FilesystemDiskOverride::class);

        $this->assertEmpty($filesystemOverride->getOverrides());

        $filesystem = $app->make('filesystem');

        $filesystem->disk('bud-disk');

        $this->assertNotEmpty($filesystemOverride->getOverrides());
        $this->assertContains('bud-disk', $filesystemOverride->getOverrides());

        $override->cleanup($tenancy, $tenant);

        $this->assertEmpty($filesystemOverride->getOverrides());
    }

    #[Test]
    public function cleansUpNothingWithoutResolvedDrivers(): void
    {
        $override = new StackedOverride('filesystem', [
            'overrides' => [
                FilesystemManagerOverride::class,
                FilesystemDiskOverride::class,
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

        $filesystemOverride = $override->getOverride(FilesystemDiskOverride::class);

        $this->assertEmpty($filesystemOverride->getOverrides());

        $app->make('filesystem');

        $this->assertEmpty($filesystemOverride->getOverrides());

        $override->cleanup($tenancy, $tenant);

        $this->assertEmpty($filesystemOverride->getOverrides());
    }

    public static function filesystemResolvedDataProvider(): array
    {
        return [
            'filesystem resolved'     => [true],
            'filesystem not resolved' => [false],
        ];
    }
}
