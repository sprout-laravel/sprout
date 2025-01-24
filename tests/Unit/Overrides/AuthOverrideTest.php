<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Overrides;

use Closure;
use Illuminate\Auth\AuthManager;
use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Config\Repository;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\DeferrableServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Overrides\Auth\SproutAuthCacheTokenRepository;
use Sprout\Overrides\Auth\SproutAuthDatabaseTokenRepository;
use Sprout\Overrides\Auth\SproutAuthPasswordBrokerManager;
use Sprout\Overrides\AuthOverride;
use Sprout\Sprout;
use Sprout\Support\SettingsRepository;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;
use Workbench\App\Models\User;
use function Sprout\sprout;
use function Sprout\tenancy;

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
        $this->assertTrue(is_subclass_of(AuthOverride::class, BootableServiceOverride::class));
    }

    #[Test]
    public function isRegisteredWithSproutCorrectly(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'auth' => [
                'driver' => AuthOverride::class,
            ],
        ]);

        $this->assertFalse($sprout->overrides()->hasOverride('auth'));

        $sprout->overrides()->registerOverrides();

        $this->assertTrue($sprout->overrides()->hasOverride('auth'));
        $this->assertSame(AuthOverride::class, $sprout->overrides()->getOverrideClass('auth'));
        $this->assertTrue($sprout->overrides()->isOverrideBootable('auth'));
        $this->assertTrue($sprout->overrides()->hasOverrideBooted('auth'));
    }

    #[Test, DataProvider('authPasswordResolvedDataProvider')]
    public function bootsCorrectly(bool $return): void
    {
        $override = new AuthOverride('auth', []);

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

        $override->boot($app, $sprout);
    }

    #[Test]
    public function overridesThePasswordBrokerManager(): void
    {
        $override = new AuthOverride('auth', []);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout = new Sprout($app, new SettingsRepository());

        $override->boot($app, $sprout);

        $manager = $app->make('auth.password');

        $this->assertInstanceOf(SproutAuthPasswordBrokerManager::class, $manager);
    }

    #[Test, DataProvider('authResolvedDataProvider')]
    public function setsUpForTheTenancy(bool $authReturn, bool $authPasswordReturn): void
    {
        $override = new AuthOverride('auth', []);

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

        $override->boot($app, $sprout);

        $override->setup(
            Mockery::mock(Tenancy::class),
            Mockery::mock(Tenant::class)
        );
    }

    #[Test, DataProvider('authResolvedDataProvider')]
    public function cleansUpAfterTheTenancy(bool $authReturn, bool $authPasswordReturn): void
    {
        $override = new AuthOverride('auth', []);

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

        $override->boot($app, $sprout);

        $override->cleanup(
            Mockery::mock(Tenancy::class),
            Mockery::mock(Tenant::class)
        );
    }

    #[Test, DataProvider('authResolvedDataProvider')]
    public function cleansUpAfterTheTenancyWithoutOverriddenBrokerManager(bool $authReturn, bool $authPasswordReturn): void
    {
        $override = new AuthOverride('auth', []);

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
