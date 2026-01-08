<?php
declare(strict_types=1);

namespace Sprout\Bud\Tests\Unit\Overrides;

use Closure;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Bud\Bud;
use Sprout\Bud\Contracts\ConfigStore;
use Sprout\Bud\Exceptions\CyclicOverrideException;
use Sprout\Bud\Managers\ConfigStoreManager;
use Sprout\Bud\Overrides\DatabaseConnectionOverride;
use Sprout\Bud\Tests\Unit\UnitTestCase;
use Sprout\Core\Contracts\BootableServiceOverride;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Contracts\Tenant;
use Sprout\Core\Contracts\TenantHasResources;
use Sprout\Core\Sprout;
use Sprout\Core\Support\SettingsRepository;
use function Sprout\Core\sprout;

class DatabaseConnectionOverrideTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('sprout.overrides', []);
        });
    }

    private function mockDatabaseManager(bool $extends = true, ?Closure $callback = null): DatabaseManager&MockInterface
    {
        return Mockery::mock(DatabaseManager::class, static function (MockInterface $mock) use ($extends, $callback) {
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
        $this->assertTrue(is_subclass_of(DatabaseConnectionOverride::class, BootableServiceOverride::class));
    }

    #[Test]
    public function isRegisteredWithSproutCorrectly(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'database' => [
                'driver' => DatabaseConnectionOverride::class,
            ],
        ]);

        $this->assertFalse($sprout->overrides()->hasOverride('database'));

        $sprout->overrides()->registerOverrides();

        $this->assertTrue($sprout->overrides()->hasOverride('database'));
        $this->assertSame(DatabaseConnectionOverride::class, $sprout->overrides()->getOverrideClass('database'));
        $this->assertTrue($sprout->overrides()->isOverrideBootable('database'));
        $this->assertTrue($sprout->overrides()->hasOverrideBooted('database'));
    }

    #[Test, DataProvider('managerResolvedDataProvider')]
    public function bootsCorrectly(bool $return): void
    {
        $override = new DatabaseConnectionOverride('database', []);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, function (MockInterface $mock) use ($return) {
            $mock->makePartial();

            $mock->shouldReceive('resolved')
                 ->withArgs(['db'])
                 ->andReturn($return)
                 ->once();

            if ($return) {
                $mock->shouldReceive('make')
                     ->with('db')
                     ->andReturn($this->mockDatabaseManager())
                     ->once();
            } else {
                $mock->shouldReceive('afterResolving')
                     ->withArgs([
                         'db',
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
    public function errorsIfOverriddenConnectionAlsoUsesBud(): void
    {
        $override = new DatabaseConnectionOverride('database', []);

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
        $app->make('config')->set('database.connections.bud-connection', [
            'driver'   => 'bud',
            'database' => 'bud-database',
        ]);

        $app->singleton(Bud::class, fn () => new Bud($app, Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($tenancy, $tenant) {
            $mock->shouldReceive('get')
                 ->andReturn(Mockery::mock(ConfigStore::class, static function (MockInterface $mock) use ($tenancy, $tenant) {
                     $mock->shouldReceive('get')
                          ->with(
                              $tenancy,
                              $tenant,
                              'database',
                              'bud-connection',
                          )->andReturn([
                             'driver'   => 'bud',
                             'database' => 'bud-database',
                         ]);
                 }));
        })));

        $sprout = new Sprout($app, new SettingsRepository());

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        /** @var DatabaseManager $manager */
        $manager = $app->make('db');

        $this->expectException(CyclicOverrideException::class);
        $this->expectExceptionMessage('Attempt to create cyclic bud database connection [bud-connection] detected');

        $manager->connection('bud-connection');
    }

    #[Test]
    public function keepsTrackOfResolvedBudDrivers(): void
    {
        $override = new DatabaseConnectionOverride('database', []);

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
        $app->make('config')->set('database.connections.bud-connection', [
            'driver'   => 'bud',
            'database' => 'bud-database',
        ]);

        $app->singleton(Bud::class, fn () => new Bud($app, Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($tenancy, $tenant) {
            $mock->shouldReceive('get')
                 ->andReturn(Mockery::mock(ConfigStore::class, function (MockInterface $mock) use ($tenancy, $tenant) {
                     $mock->shouldReceive('get')
                          ->with(
                              $tenancy,
                              $tenant,
                              'database',
                              'bud-connection',
                          )->andReturn([
                             'driver' => 'mysql',
                         ]);
                 }));
        })));

        $sprout = new Sprout($app, new SettingsRepository());

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        /** @var \Illuminate\Database\DatabaseManager $manager */
        $manager = $app->make('db');

        $manager->connection('bud-connection');

        $this->assertNotEmpty($override->getOverrides());
        $this->assertContains('bud-connection', $override->getOverrides());
    }

    #[Test]
    public function cleansUpResolvedDrivers(): void
    {
        $override = new DatabaseConnectionOverride('database', []);

        $this->app->forgetInstance('db');

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $app->make('config')->set('multitenancy.defaults.config', 'filesystem');
        $app->make('config')->set('database.connections.bud-connection', [
            'driver'   => 'bud',
            'database' => 'bud-database',
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
                              'database',
                              'bud-connection',
                          )->andReturn([
                             'driver' => 'mysql',
                         ]);
                 }));
        })));

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        $this->assertEmpty($override->getOverrides());

        $manager = $app->make('db');

        $manager->connection('bud-connection');

        $this->assertNotEmpty($override->getOverrides());
        $this->assertContains('bud-connection', $override->getOverrides());

        $override->cleanup($tenancy, $tenant);

        $this->assertEmpty($override->getOverrides());
    }

    #[Test]
    public function cleansUpNothingWithoutResolvedDrivers(): void
    {
        $override = new DatabaseConnectionOverride('database', []);

        $this->app->forgetInstance('db');

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $app->make('config')->set('multitenancy.defaults.config', 'filesystem');
        $app->make('config')->set('database.connections.bud-connection', [
            'driver'   => 'bud',
            'database' => 'bud-database',
        ]);

        $sprout = new Sprout($app, new SettingsRepository());

        $tenant = Mockery::mock(Tenant::class, TenantHasResources::class, static function (MockInterface $mock) {
        });

        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) use ($tenant) {
        });

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        $this->assertEmpty($override->getOverrides());

        $override->cleanup($tenancy, $tenant);

        $this->assertEmpty($override->getOverrides());
    }

    public static function managerResolvedDataProvider(): array
    {
        return [
            'database manager resolved'     => [true],
            'database manager not resolved' => [false],
        ];
    }
}
