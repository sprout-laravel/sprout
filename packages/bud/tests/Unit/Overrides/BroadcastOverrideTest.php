<?php
declare(strict_types=1);

namespace Sprout\Bud\Tests\Unit\Overrides;

use Closure;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
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
use Sprout\Bud\Overrides\Broadcast\BudBroadcastManager;
use Sprout\Bud\Overrides\BroadcastConnectionOverride;
use Sprout\Bud\Overrides\BroadcastManagerOverride;
use Sprout\Bud\Tests\Unit\UnitTestCase;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantHasResources;
use Sprout\Overrides\StackedOverride;
use Sprout\Sprout;
use Sprout\Support\SettingsRepository;
use function Sprout\sprout;

class BroadcastOverrideTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('sprout.overrides', []);
        });
    }

    private function mockBudBroadcastManager(bool $extends = true, ?Closure $callback = null): BudBroadcastManager&MockInterface
    {
        return Mockery::mock(BudBroadcastManager::class, static function (MockInterface $mock) use ($extends, $callback) {
            if ($extends) {
                $mock->shouldReceive('extend')
                     ->with('bud', Mockery::on(static function ($arg) {
                         return is_callable($arg) && $arg instanceof Closure;
                     }))
                     ->once();
            }

            if ($callback) {
                $callback($mock);
            }
        });
    }

    #[Test]
    public function isBuiltCorrectly(): void
    {
        $this->assertTrue(is_subclass_of(BroadcastManagerOverride::class, BootableServiceOverride::class));
        $this->assertTrue(is_subclass_of(BroadcastConnectionOverride::class, BootableServiceOverride::class));
    }

    #[Test]
    public function isRegisteredWithSproutCorrectly(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'broadcast' => [
                'driver'    => StackedOverride::class,
                'overrides' => [
                    BroadcastManagerOverride::class,
                    BroadcastConnectionOverride::class,
                ],
            ],
        ]);

        $this->assertFalse($sprout->overrides()->hasOverride('broadcast'));

        $sprout->overrides()->registerOverrides();

        $this->assertTrue($sprout->overrides()->hasOverride('broadcast'));
        $this->assertSame(StackedOverride::class, $sprout->overrides()->getOverrideClass('broadcast'));
        $this->assertTrue($sprout->overrides()->isOverrideBootable('broadcast'));
        $this->assertTrue($sprout->overrides()->hasOverrideBooted('broadcast'));
    }

    #[Test, DataProvider('broadcastResolvedDataProvider')]
    public function bootsCorrectly(bool $return): void
    {
        $override = new StackedOverride('broadcast', [
            'overrides' => [
                BroadcastManagerOverride::class,
                BroadcastConnectionOverride::class,
            ],
        ]);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, function (MockInterface $mock) use ($return) {
            $mock->makePartial();

            if ($return) {
                $mock->shouldReceive('forgetInstance')->with(BroadcastManager::class)->once();
            }

            $mock->shouldReceive('singleton')
                 ->with(BroadcastManager::class, Mockery::on(static function ($arg) {
                     return is_callable($arg) && $arg instanceof Closure;
                 }))
                 ->once();

            $mock->shouldReceive('resolved')
                 ->withArgs([BroadcastManager::class])
                 ->andReturn($return)
                 ->times(2);

            if ($return) {
                $mock->shouldReceive('make')
                     ->with(BroadcastManager::class)
                     ->andReturn($this->mockBudBroadcastManager())
                     ->times(2);
            } else {
                $mock->shouldReceive('afterResolving')
                     ->withArgs([
                         BroadcastManager::class,
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
        $override = new StackedOverride('broadcast', [
            'overrides' => [
                BroadcastConnectionOverride::class,
            ],
        ]);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, function (MockInterface $mock) {
            $mock->makePartial();
        });

        // We have to bind the mock so that the extension can be registered.
        $app->singleton(BroadcastManager::class, fn () => Mockery::mock(BroadcastManager::class));

        $sprout = new Sprout($app, new SettingsRepository());

        $override->setApp($app)->setSprout($sprout);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot override broadcast connections without the Bud broadcast manager override');

        // This is important, otherwise it doesn't behave nicely with the
        // afterResolving method.
        $app->make(BroadcastManager::class);

        $override->boot($app, $sprout);
    }

    #[Test]
    public function errorsWithNoTenantSpecificConfig(): void
    {
        $override = new StackedOverride('broadcast', [
            'overrides' => [
                BroadcastManagerOverride::class,
                BroadcastConnectionOverride::class,
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

        $app->singleton(Bud::class, fn () => new Bud($app, Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($tenancy, $tenant) {
            $mock->shouldReceive('get')
                 ->andReturn(Mockery::mock(ConfigStore::class, function (MockInterface $mock) use ($tenancy, $tenant) {
                     $mock->shouldReceive('get')
                          ->with(
                              $tenancy,
                              $tenant,
                              'broadcast',
                              'bud-connection',
                          )->andReturn(null);
                 }));
        })));

        $sprout = new Sprout($app, new SettingsRepository());

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        /** @var \Sprout\Bud\Overrides\Broadcast\BudBroadcastManager $manager */
        $manager = $app->make(BroadcastManager::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to find configuration for [broadcast.bud-connection] for tenant [my-tenant] on tenancy [my-tenancy]');

        $manager->connectUsing('bud-connection', [
            'driver' => 'bud',
        ]);
    }

    #[Test]
    public function errorsIfOverriddenConnectionAlsoUsesBud(): void
    {
        $override = new StackedOverride('broadcast', [
            'overrides' => [
                BroadcastManagerOverride::class,
                BroadcastConnectionOverride::class,
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

        $app->singleton(Bud::class, fn () => new Bud($app, Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($tenancy, $tenant) {
            $mock->shouldReceive('get')
                 ->andReturn(Mockery::mock(ConfigStore::class, function (MockInterface $mock) use ($tenancy, $tenant) {
                     $mock->shouldReceive('get')
                          ->with(
                              $tenancy,
                              $tenant,
                              'broadcast',
                              'bud-connection',
                          )->andReturn([
                             'driver' => 'bud',
                         ]);
                 }));
        })));

        $sprout = new Sprout($app, new SettingsRepository());

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        /** @var \Sprout\Bud\Overrides\Broadcast\BudBroadcastManager $manager */
        $manager = $app->make(BroadcastManager::class);

        $this->expectException(CyclicOverrideException::class);
        $this->expectExceptionMessage('Attempt to create cyclic bud broadcast connection [bud-connection] detected');

        $manager->connectUsing('bud-connection', [
            'driver' => 'bud',
        ]);
    }

    #[Test]
    public function keepsTrackOfResolvedBudDrivers(): void
    {
        $override = new StackedOverride('broadcast', [
            'overrides' => [
                BroadcastManagerOverride::class,
                BroadcastConnectionOverride::class,
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

        $app->singleton(Bud::class, fn () => new Bud($app, Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($tenancy, $tenant) {
            $mock->shouldReceive('get')
                 ->andReturn(Mockery::mock(ConfigStore::class, function (MockInterface $mock) use ($tenancy, $tenant) {
                     $mock->shouldReceive('get')
                          ->with(
                              $tenancy,
                              $tenant,
                              'broadcast',
                              'bud-connection',
                          )->andReturn([
                             'driver' => 'null',
                         ]);
                 }));
        })));

        $sprout = new Sprout($app, new SettingsRepository());

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        /** @var \Sprout\Bud\Overrides\Broadcast\BudBroadcastManager $manager */
        $manager = $app->make(BroadcastManager::class);

        $manager->connectUsing('bud-connection', [
            'driver' => 'bud',
        ]);

        $this->assertNotEmpty($override->getOverrides()[BroadcastConnectionOverride::class]->getOverrides());
        $this->assertContains('bud-connection', $override->getOverrides()[BroadcastConnectionOverride::class]->getOverrides());
    }

    #[Test]
    public function cleansUpResolvedDrivers(): void
    {
        $override = new StackedOverride('broadcast', [
            'overrides' => [
                BroadcastManagerOverride::class,
                BroadcastConnectionOverride::class,
            ],
        ]);

        $this->app->forgetInstance(BroadcastManager::class);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $app->make('config')->set('multitenancy.defaults.config', 'filesystem');

        $sprout = new Sprout($app, new SettingsRepository());

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
                              'broadcast',
                              'bud-connection',
                          )->andReturn([
                             'driver' => 'null',
                         ]);
                 }));
        })));

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        $broadcastOverride = $override->getOverride(BroadcastConnectionOverride::class);

        $this->assertEmpty($broadcastOverride->getOverrides());

        $manager = $app->make(BroadcastManager::class);

        $manager->connectUsing('bud-connection', [
            'driver' => 'bud',
        ]);

        $this->assertNotEmpty($broadcastOverride->getOverrides());
        $this->assertContains('bud-connection', $broadcastOverride->getOverrides());

        $override->cleanup($tenancy, $tenant);

        $this->assertEmpty($broadcastOverride->getOverrides());
    }

    #[Test]
    public function cleansUpResolvedDriversFromPreconfiguredConnections(): void
    {
        $override = new StackedOverride('broadcast', [
            'overrides' => [
                BroadcastManagerOverride::class,
                BroadcastConnectionOverride::class,
            ],
        ]);

        $this->app->forgetInstance(BroadcastManager::class);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $app->make('config')->set('multitenancy.defaults.config', 'filesystem');

        $app->make('config')->set('broadcasting.connections.bud-connection', [
            'driver' => 'bud',
        ]);

        $sprout = new Sprout($app, new SettingsRepository());

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
                              'broadcast',
                              'bud-connection',
                          )->andReturn([
                             'driver' => 'null',
                         ]);
                 }));
        })));

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        $broadcastOverride = $override->getOverride(BroadcastConnectionOverride::class);

        $this->assertEmpty($broadcastOverride->getOverrides());

        $manager = $app->make(BroadcastManager::class);

        $manager->connection('bud-connection');

        $this->assertNotEmpty($broadcastOverride->getOverrides());
        $this->assertContains('bud-connection', $broadcastOverride->getOverrides());

        $override->cleanup($tenancy, $tenant);

        $this->assertEmpty($broadcastOverride->getOverrides());
    }

    #[Test]
    public function cleansUpNothingWithoutResolvedDrivers(): void
    {
        $override = new StackedOverride('broadcast', [
            'overrides' => [
                BroadcastManagerOverride::class,
                BroadcastConnectionOverride::class,
            ],
        ]);

        $this->app->forgetInstance(BroadcastManager::class);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $app->make('config')->set('multitenancy.defaults.config', 'filesystem');

        $app->make('config')->set('broadcasting.connections.bud-connection', [
            'driver' => 'bud',
        ]);

        $sprout = new Sprout($app, new SettingsRepository());

        $tenant = Mockery::mock(Tenant::class, TenantHasResources::class, static function (MockInterface $mock) {
        });

        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) use ($tenant) {
        });

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        $broadcastOverride = $override->getOverride(BroadcastConnectionOverride::class);

        $this->assertEmpty($broadcastOverride->getOverrides());

        $override->cleanup($tenancy, $tenant);

        $this->assertEmpty($broadcastOverride->getOverrides());
    }

    public static function broadcastResolvedDataProvider(): array
    {
        return [
            'broadcast resolved'     => [true],
            'broadcast not resolved' => [false],
        ];
    }
}
