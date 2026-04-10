<?php
declare(strict_types=1);

namespace Sprout\Core\Tests\Unit\Overrides;

use Closure;
use Illuminate\Auth\AuthManager;
use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Core\Contracts\BootableServiceOverride;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Contracts\Tenant;
use Sprout\Core\Overrides\Auth\SproutAuthPasswordBrokerManager;
use Sprout\Core\Overrides\AuthGuardOverride;
use Sprout\Core\Overrides\AuthPasswordOverride;
use Sprout\Core\Overrides\StackedOverride;
use Sprout\Core\Sprout;
use Sprout\Core\Support\SettingsRepository;
use Sprout\Core\Tests\Unit\UnitTestCase;
use function Sprout\Core\sprout;

class AuthOverrideTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('sprout.overrides', []);
        });
    }

    #[Test]
    public function isBuiltCorrectly(): void
    {
        $this->assertFalse(is_subclass_of(AuthGuardOverride::class, BootableServiceOverride::class));
        $this->assertTrue(is_subclass_of(AuthPasswordOverride::class, BootableServiceOverride::class));
    }

    #[Test]
    public function isRegisteredWithSproutCorrectly(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'auth' => [
                'driver'    => StackedOverride::class,
                'overrides' => [
                    AuthGuardOverride::class,
                    AuthPasswordOverride::class,
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

    #[Test, DataProvider('authPasswordResolvedDataProvider')]
    public function bootsCorrectly(bool $return): void
    {
        $override = new StackedOverride('auth', [
            'overrides' => [
                AuthGuardOverride::class,
                AuthPasswordOverride::class,
            ],
        ]);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) use ($return) {
            $mock->makePartial();
            $mock->shouldReceive('removeDeferredServices')
                 ->withArgs([['auth.password']])
                 ->once();

            $mock->shouldReceive('singleton')
                 ->withArgs([
                     'auth.password',
                     Mockery::on(static function ($closure) {
                         return is_callable($closure) && $closure instanceof Closure;
                     }),
                 ])
                 ->once();

            $mock->shouldReceive('resolved')->withArgs(['auth.password'])->once()->andReturn($return);

            if ($return) {
                $mock->shouldReceive('forgetInstance')->withArgs(['auth.password'])->once();
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
    public function overridesThePasswordBrokerManager(): void
    {
        $override = new StackedOverride('auth', [
            'overrides' => [
                AuthGuardOverride::class,
                AuthPasswordOverride::class,
            ],
        ]);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout = new Sprout($app, new SettingsRepository());

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        $manager = $app->make('auth.password');

        $this->assertInstanceOf(SproutAuthPasswordBrokerManager::class, $manager);
    }

    #[Test, DataProvider('authResolvedDataProvider')]
    public function setsUpForTheTenancy(bool $authReturn, bool $authPasswordReturn): void
    {
        $override = new StackedOverride('auth', [
            'overrides' => [
                AuthGuardOverride::class,
                AuthPasswordOverride::class,
            ],
        ]);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) use ($authReturn, $authPasswordReturn) {
            $mock->makePartial();

            $mock->shouldReceive('resolved')
                 ->withArgs(['auth'])
                 ->andReturn($authReturn)
                 ->once();

            if ($authReturn) {
                $authManager = Mockery::mock(AuthManager::class, static function (MockInterface $mock) {
                    $mock->shouldReceive('hasResolvedGuards')->once()->andReturn(true);
                    $mock->shouldReceive('forgetGuards')->once();
                });

                $mock->shouldReceive('make')
                     ->withArgs([AuthManager::class])
                     ->andReturn($authManager)
                     ->once();
            }

            $mock->shouldReceive('resolved')
                 ->withArgs(['auth.password'])
                 ->andReturn($authPasswordReturn)
                 ->atLeast()
                 ->once();

            if ($authPasswordReturn) {
                $passwordManager = Mockery::mock(SproutAuthPasswordBrokerManager::class, static function (MockInterface $mock) {
                    $mock->shouldReceive('flush')->once();
                });

                $mock->shouldReceive('make')
                     ->withArgs(['auth.password'])
                     ->andReturn($passwordManager)
                     ->once();
            }
        });

        $sprout = new Sprout($app, new SettingsRepository());

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        $override->setup(
            Mockery::mock(Tenancy::class),
            Mockery::mock(Tenant::class)
        );
    }

    #[Test, DataProvider('authResolvedDataProvider')]
    public function cleansUpAfterTheTenancy(bool $authReturn, bool $authPasswordReturn): void
    {
        $override = new StackedOverride('auth', [
            'overrides' => [
                AuthGuardOverride::class,
                AuthPasswordOverride::class,
            ],
        ]);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) use ($authReturn, $authPasswordReturn) {
            $mock->makePartial();

            $mock->shouldReceive('resolved')
                 ->withArgs(['auth'])
                 ->andReturn($authReturn)
                 ->once();

            if ($authReturn) {
                $authManager = Mockery::mock(AuthManager::class, static function (MockInterface $mock) {
                    $mock->shouldReceive('hasResolvedGuards')->once()->andReturn(true);
                    $mock->shouldReceive('forgetGuards')->once();
                });

                $mock->shouldReceive('make')
                     ->withArgs([AuthManager::class])
                     ->andReturn($authManager)
                     ->once();
            }

            $mock->shouldReceive('resolved')
                 ->withArgs(['auth.password'])
                 ->andReturn($authPasswordReturn)
                 ->atLeast()
                 ->once();

            if ($authPasswordReturn) {
                $passwordManager = Mockery::mock(SproutAuthPasswordBrokerManager::class, static function (MockInterface $mock) {
                    $mock->shouldReceive('flush')->once();
                });

                $mock->shouldReceive('make')
                     ->withArgs(['auth.password'])
                     ->andReturn($passwordManager)
                     ->once();
            }
        });

        $sprout = new Sprout($app, new SettingsRepository());

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        $override->cleanup(
            Mockery::mock(Tenancy::class),
            Mockery::mock(Tenant::class)
        );
    }

    #[Test, DataProvider('authResolvedDataProvider')]
    public function cleansUpAfterTheTenancyWithoutOverriddenBrokerManager(bool $authReturn, bool $authPasswordReturn): void
    {
        $override = new StackedOverride('auth', [
            'overrides' => [
                AuthGuardOverride::class,
                AuthPasswordOverride::class,
            ],
        ]);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) use ($authReturn, $authPasswordReturn) {
            $mock->makePartial();

            $mock->shouldReceive('resolved')
                 ->withArgs(['auth'])
                 ->andReturn($authReturn)
                 ->once();

            if ($authReturn) {
                $authManager = Mockery::mock(AuthManager::class, static function (MockInterface $mock) {
                    $mock->shouldReceive('hasResolvedGuards')->once()->andReturn(true);
                    $mock->shouldReceive('forgetGuards')->once();
                });

                $mock->shouldReceive('make')
                     ->withArgs([AuthManager::class])
                     ->andReturn($authManager)
                     ->once();
            }

            $mock->shouldReceive('resolved')
                 ->withArgs(['auth.password'])
                 ->andReturn($authPasswordReturn)
                 ->atLeast()
                 ->once();

            if ($authPasswordReturn) {
                $passwordManager = Mockery::mock(PasswordBrokerManager::class, static function (MockInterface $mock) {
                    $mock->shouldNotReceive('flush');
                });

                $mock->shouldReceive('make')
                     ->withArgs(['auth.password'])
                     ->andReturn($passwordManager)
                     ->once();
            }
        });

        $sprout = new Sprout($app, new SettingsRepository());

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        $override->cleanup(
            Mockery::mock(Tenancy::class),
            Mockery::mock(Tenant::class)
        );
    }

    public static function authPasswordResolvedDataProvider(): array
    {
        return [
            'auth.password resolved'     => [true],
            'auth.password not resolved' => [false],
        ];
    }

    public static function authResolvedDataProvider(): array
    {
        return [
            'auth resolved auth.password not resolved'     => [true, false],
            'auth resolved auth.password resolved'         => [true, true],
            'auth not resolved auth.password not resolved' => [false, false],
            'auth not resolved auth.password resolved'     => [false, true],
        ];
    }
}
