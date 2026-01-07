<?php
declare(strict_types=1);

namespace Sprout\Bud\Tests\Unit\Overrides;

use Closure;
use Illuminate\Auth\AuthManager;
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
use Sprout\Bud\Overrides\Auth\BudAuthManager;
use Sprout\Bud\Overrides\AuthManagerOverride;
use Sprout\Bud\Overrides\AuthProviderOverride;
use Sprout\Bud\Tests\Unit\UnitTestCase;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantHasResources;
use Sprout\Overrides\StackedOverride;
use Sprout\Sprout;
use Sprout\Support\SettingsRepository;
use function Sprout\sprout;

class AuthOverrideTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('sprout.overrides', []);
        });
    }

    private function mockBudAuthManager(bool $extends = true, ?Closure $callback = null): BudAuthManager&MockInterface
    {
        return Mockery::mock(BudAuthManager::class, static function (MockInterface $mock) use ($extends, $callback) {
            if ($extends) {
                $mock->shouldReceive('provider')
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
        $this->assertTrue(is_subclass_of(AuthManagerOverride::class, BootableServiceOverride::class));
        $this->assertTrue(is_subclass_of(AuthProviderOverride::class, BootableServiceOverride::class));
    }

    #[Test]
    public function isRegisteredWithSproutCorrectly(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'auth' => [
                'driver'    => StackedOverride::class,
                'overrides' => [
                    AuthManagerOverride::class,
                    AuthProviderOverride::class,
                ],
            ],
        ]);

        $this->assertFalse($sprout->overrides()->hasOverride('auth'));

        $sprout->overrides()->registerOverrides();

        $this->assertTrue($sprout->overrides()->hasOverride('auth'));
        $this->assertSame(StackedOverride::class, $sprout->overrides()->getOverrideClass('auth'));
        $this->assertTrue($sprout->overrides()->isOverrideBootable('auth'));
        $this->assertTrue($sprout->overrides()->hasOverrideBooted('auth'));
    }

    #[Test, DataProvider('authResolvedDataProvider')]
    public function bootsCorrectly(bool $return): void
    {
        $override = new StackedOverride('auth', [
            'overrides' => [
                AuthManagerOverride::class,
                AuthProviderOverride::class,
            ],
        ]);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, function (MockInterface $mock) use ($return) {
            $mock->makePartial();

            if ($return) {
                $mock->shouldReceive('forgetInstance')->with('auth')->once();
            }

            $mock->shouldReceive('singleton')
                 ->with('auth', Mockery::on(static function ($arg) {
                     return is_callable($arg) && $arg instanceof Closure;
                 }))
                 ->once();

            $mock->shouldReceive('resolved')
                 ->withArgs(['auth'])
                 ->andReturn($return)
                 ->times(2);

            if ($return) {
                $mock->shouldReceive('make')
                     ->with('auth')
                     ->andReturn($this->mockBudAuthManager())
                     ->times(2);
            } else {
                $mock->shouldReceive('afterResolving')
                     ->withArgs([
                         'auth',
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
        $override = new StackedOverride('auth', [
            'overrides' => [
                AuthProviderOverride::class,
            ],
        ]);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, function (MockInterface $mock) {
            $mock->makePartial();
        });

        // We have to bind the mock so that the extension can be registered.
        $app->singleton(AuthManager::class, fn () => Mockery::mock(AuthManager::class));

        $sprout = new Sprout($app, new SettingsRepository());

        $override->setApp($app)->setSprout($sprout);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot override auth providers without the Bud auth manager override');

        // This is important, otherwise it doesn't behave nicely with the
        // afterResolving method.
        $app->make('auth');

        $override->boot($app, $sprout);
    }

    #[Test]
    public function errorsWithNoTenantSpecificConfig(): void
    {
        $override = new StackedOverride('auth', [
            'overrides' => [
                AuthManagerOverride::class,
                AuthProviderOverride::class,
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
                              'auth',
                              'bud-provider',
                          )->andReturn(null);
                 }));
        })));

        $sprout = new Sprout($app, new SettingsRepository());

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        /** @var \Sprout\Bud\Overrides\Auth\BudAuthManager $manager */
        $manager = $app->make('auth');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to find configuration for [auth.bud-provider] for tenant [my-tenant] on tenancy [my-tenancy]');

        $manager->createUserProviderFromConfig(['driver' => 'bud', 'provider' => 'bud-provider']);
    }

    #[Test]
    public function errorsIfOverriddenConnectionAlsoUsesBud(): void
    {
        $override = new StackedOverride('auth', [
            'overrides' => [
                AuthManagerOverride::class,
                AuthProviderOverride::class,
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
                              'auth',
                              'bud-provider',
                          )->andReturn([
                             'driver' => 'bud',
                         ]);
                 }));
        })));

        $sprout = new Sprout($app, new SettingsRepository());

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        /** @var \Sprout\Bud\Overrides\Auth\BudAuthManager $manager */
        $manager = $app->make('auth');

        $this->expectException(CyclicOverrideException::class);
        $this->expectExceptionMessage('Attempt to create cyclic bud auth provider [bud-provider] detected');

        $manager->createUserProviderFromConfig([
            'driver'   => 'bud',
            'provider' => 'bud-provider',
        ]);
    }

    #[Test]
    public function keepsTrackOfResolvedBudProviders(): void
    {
        $override = new StackedOverride('auth', [
            'overrides' => [
                AuthManagerOverride::class,
                AuthProviderOverride::class,
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
                              'auth',
                              'bud-provider',
                          )->andReturn([
                             'driver' => 'database',
                             'table'  => 'fake-table',
                         ]);
                 }));
        })));

        $sprout = new Sprout($app, new SettingsRepository());

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        /** @var \Sprout\Bud\Overrides\Auth\BudAuthManager $manager */
        $manager = $app->make('auth');

        $manager->createUserProviderFromConfig(['provider' => 'bud-provider', 'driver' => 'bud']);

        $this->assertNotEmpty($override->getOverrides()[AuthProviderOverride::class]->getOverrides());
        $this->assertContains('bud-provider', $override->getOverrides()[AuthProviderOverride::class]->getOverrides());
    }

    #[Test]
    public function cleansUpResolvedDrivers(): void
    {
        $override = new StackedOverride('auth', [
            'overrides' => [
                AuthManagerOverride::class,
                AuthProviderOverride::class,
            ],
        ]);

        $this->app->forgetInstance('auth');

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
                              'auth',
                              'bud-provider',
                          )->andReturn([
                             'driver' => 'database',
                             'table'  => 'fake-table',
                         ]);
                 }));
        })));

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        $authOverride = $override->getOverride(AuthProviderOverride::class);

        $this->assertEmpty($authOverride->getOverrides());

        /** @var \Sprout\Bud\Overrides\Auth\BudAuthManager $manager */
        $manager = $app->make('auth');

        $manager->createUserProviderFromConfig(['provider' => 'bud-provider', 'driver' => 'bud']);

        $this->assertNotEmpty($authOverride->getOverrides());
        $this->assertContains('bud-provider', $authOverride->getOverrides());

        $override->cleanup($tenancy, $tenant);

        $this->assertEmpty($authOverride->getOverrides());
    }

    #[Test]
    public function cleansUpResolvedDriversFromPreconfiguredConnections(): void
    {
        $override = new StackedOverride('auth', [
            'overrides' => [
                AuthManagerOverride::class,
                AuthProviderOverride::class,
            ],
        ]);

        $this->app->forgetInstance('auth');

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $app->make('config')->set('multitenancy.defaults.config', 'filesystem');

        $app->make('config')->set('auth.providers.bud-provider', [
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
                              'auth',
                              'bud-provider',
                          )->andReturn([
                             'driver' => 'database',
                             'table'  => 'fake-table',
                         ]);
                 }));
        })));

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        $authOverride = $override->getOverride(AuthProviderOverride::class);

        $this->assertEmpty($authOverride->getOverrides());

        /** @var \Sprout\Bud\Overrides\Auth\BudAuthManager $manager */
        $manager = $app->make('auth');

        $manager->createUserProvider('bud-provider');

        $this->assertNotEmpty($authOverride->getOverrides());
        $this->assertContains('bud-provider', $authOverride->getOverrides());

        $override->cleanup($tenancy, $tenant);

        $this->assertEmpty($authOverride->getOverrides());
    }

    #[Test]
    public function cleansUpNothingWithoutResolvedDrivers(): void
    {
        $override = new StackedOverride('auth', [
            'overrides' => [
                AuthManagerOverride::class,
                AuthProviderOverride::class,
            ],
        ]);

        $this->app->forgetInstance('auth');

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $app->make('config')->set('multitenancy.defaults.config', 'filesystem');

        $app->make('config')->set('auth.providers.bud-provider', [
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

        $authOverride = $override->getOverride(AuthProviderOverride::class);

        $this->assertEmpty($authOverride->getOverrides());

        $override->cleanup($tenancy, $tenant);

        $this->assertEmpty($authOverride->getOverrides());
    }

    public static function authResolvedDataProvider(): array
    {
        return [
            'auth resolved'     => [true],
            'auth not resolved' => [false],
        ];
    }
}
