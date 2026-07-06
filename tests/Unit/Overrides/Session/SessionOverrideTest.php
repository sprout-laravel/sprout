<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Overrides;

use Closure;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Session\SessionManager;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\TenantAware;
use Sprout\Overrides\Session\SessionOverride;
use Sprout\Sprout;
use Sprout\Support\Settings;
use Sprout\Support\SettingsRepository;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;

use function Sprout\settings;
use function Sprout\sprout;

class SessionOverrideTest extends UnitTestCase
{
    public static function overrideDatabaseSetting(): array
    {
        return [
            'do not override the database' => [true],
            'override the database'        => [false],
        ];
    }

    #[Test]
    public function isBuiltCorrectly(): void
    {
        $this->assertTrue(is_subclass_of(SessionOverride::class, BootableServiceOverride::class));
    }

    #[Test]
    public function isRegisteredWithSproutCorrectly(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'session' => [
                'driver' => SessionOverride::class,
            ],
        ]);

        $sprout->overrides()->registerOverrides();

        $this->assertTrue($sprout->overrides()->hasOverride('session'));
        $this->assertSame(SessionOverride::class, $sprout->overrides()->getOverrideClass('session'));
        $this->assertTrue($sprout->overrides()->isOverrideBootable('session'));
        $this->assertTrue($sprout->overrides()->hasOverrideBooted('session'));
    }

    #[Test]
    public function performsSetup(): void
    {
        $sprout = sprout();

        config()->set('session.driver', 'file');
        config()->set('session.path', '/original-path');
        config()->set('session.domain', 'original.test');
        config()->set('session.secure', true);
        config()->set('session.same_site', 'strict');
        config()->set('sprout.overrides', [
            'session' => [
                'driver' => SessionOverride::class,
            ],
        ]);

        $this->assertFalse(sprout()->settings()->has('original.session'));

        $this->assertNull(sprout()->settings()->getUrlPath());
        $this->assertNull(sprout()->settings()->getUrlDomain());
        $this->assertNull(sprout()->settings()->shouldCookieBeSecure());
        $this->assertNull(sprout()->settings()->getCookieSameSite());

        $session = app()->make('session');

        $this->assertEmpty($session->getDrivers());

        $tenant  = TenantModel::factory()->createOne();
        $tenancy = $sprout->tenancies()->get();

        $sprout->overrides()->registerOverrides();

        $sprout->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant);

        $override = $sprout->overrides()->get('session');

        $driver = $session->driver();

        $this->assertNotEmpty($session->getDrivers());
        $this->assertInstanceOf(TenantAware::class, $driver->getHandler());
        $this->assertTrue($driver->getHandler()->hasTenant());
        $this->assertTrue($driver->getHandler()->hasTenancy());
        $this->assertInstanceOf(SessionOverride::class, $override);

        $override->setup($tenancy, $tenant);

        $this->assertSame(
            $tenancy->getName() . '_' . $tenant->getTenantIdentifier() . '_session',
            config('session.cookie'),
        );

        // setup() captured the original session config into settings...
        $original = sprout()->settings()->array('original.session');
        $this->assertSame('/original-path', $original['path']);
        $this->assertSame('original.test', $original['domain']);
        $this->assertTrue($original['secure']);
        $this->assertSame('strict', $original['same_site']);

        // ...and refreshed the session store, forgetting the loaded driver
        $this->assertEmpty($session->getDrivers());
    }

    #[Test]
    public function performsCleanup(): void
    {
        $sprout = sprout();

        config()->set('session.driver', 'file');
        config()->set('sprout.overrides', [
            'session' => [
                'driver' => SessionOverride::class,
            ],
        ]);

        $this->assertFalse(sprout()->settings()->has('original.session'));

        $session = app()->make('session');

        $tenant  = TenantModel::factory()->createOne();
        $tenancy = $sprout->tenancies()->get();

        $tenancy->setTenant($tenant);

        $sprout->overrides()->registerOverrides();

        app()->make('session');

        $override = $sprout->overrides()->get('session');
        $driver   = $session->driver();

        $this->assertNotEmpty($session->getDrivers());
        $this->assertInstanceOf(TenantAware::class, $driver->getHandler());
        $this->assertFalse($driver->getHandler()->hasTenant());
        $this->assertFalse($driver->getHandler()->hasTenancy());
        $this->assertInstanceOf(SessionOverride::class, $override);

        // Seed the stored original config, plus a tenant-overridden current config
        sprout()->settings()->set('original.session', [
            'path'      => '/original-path',
            'domain'    => 'original.test',
            'secure'    => true,
            'same_site' => 'strict',
        ]);

        config()->set('session.path', '/tenant-path');
        config()->set('session.domain', 'tenant.test');
        config()->set('session.secure', false);
        config()->set('session.same_site', 'lax');

        $override->cleanup($tenancy, $tenant);

        // cleanup() restored every original value from settings...
        $this->assertSame('/original-path', config('session.path'));
        $this->assertSame('original.test', config('session.domain'));
        $this->assertTrue(config('session.secure'));
        $this->assertSame('strict', config('session.same_site'));

        // ...cleared the stored original...
        $this->assertEmpty(sprout()->settings()->array('original.session'));

        // ...and refreshed the session store, forgetting the loaded driver
        $this->assertEmpty($session->getDrivers());
    }

    #[Test]
    public function setSessionConfigFromSproutSettings(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'session' => [
                'driver' => SessionOverride::class,
            ],
        ]);

        config()->set('session.path', '/test-path');
        config()->set('session.domain', 'test-domain.localhost');
        config()->set('session.secure', false);
        config()->set('session.same_site', 'lax');

        $tenant  = TenantModel::factory()->createOne();
        $tenancy = $sprout->tenancies()->get();

        $tenancy->setTenant($tenant);

        $sprout->overrides()->registerOverrides();

        $this->assertSame('/test-path', config('session.path'));
        $this->assertSame('test-domain.localhost', config('session.domain'));
        $this->assertFalse(config('session.secure'));
        $this->assertSame('lax', config('session.same_site'));

        $override = $sprout->overrides()->get('session');

        settings()->setUrlPath('/test-path2');
        settings()->setUrlDomain('test-domain2.localhost');
        settings()->setCookieSecure(true);
        settings()->setCookieSameSite('strict');

        $override->setup($tenancy, $tenant);

        $this->assertSame('/test-path2', config('session.path'));
        $this->assertSame('test-domain2.localhost', config('session.domain'));
        $this->assertTrue(config('session.secure'));
        $this->assertSame('strict', config('session.same_site'));
    }

    #[Test, DataProvider('overrideDatabaseSetting')]
    public function bootsCorrectlyWhenSessionManagerHasNotBeenResolved(bool $database): void
    {
        $override = new SessionOverride('session', []);

        /** @var Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
            $mock->shouldReceive('resolved')->withArgs(['session'])->andReturnFalse()->once();
            $mock->shouldReceive('afterResolving')
                 ->withArgs([
                     'session',
                     Mockery::on(static function ($arg) {
                         return is_callable($arg) && $arg instanceof Closure;
                     }),
                 ])
                 ->once();
        });

        $sprout = new Sprout($app, new SettingsRepository());

        $override->boot($app, $sprout);

        // These are only here because there would be errors if their
        // corresponding setters were not called
        $this->assertInstanceOf(\Illuminate\Contracts\Foundation\Application::class, $override->getApp());
        $this->assertInstanceOf(Sprout::class, $override->getSprout());
    }

    #[Test, DataProvider('overrideDatabaseSetting')]
    public function bootsCorrectlyWhenSessionManagerHasBeenResolved(bool $database): void
    {
        $override = new SessionOverride('session', []);

        $app = Mockery::mock(Application::class, function (MockInterface $mock) use ($database) {
            $mock->shouldReceive('resolved')->withArgs(['session'])->andReturnTrue()->once();
            $mock->shouldReceive('make')
                 ->withArgs(['session'])
                 ->andReturn($this->mockSessionManager($database))
                 ->once();
        });

        $sprout = new Sprout($app, new SettingsRepository([
            Settings::NO_DATABASE_OVERRIDE => $database,
        ]));

        $override->boot($app, $sprout);
    }

    #[Test, DataProvider('overrideDatabaseSetting')]
    public function addsDriverSessionManagerHasBeenResolved(bool $database): void
    {
        $override = new SessionOverride('session', []);

        $app = Mockery::mock(Application::class, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $app->singleton('session', function () use ($database) {
            return $this->mockSessionManager($database, false);
        });

        $sprout = new Sprout($app, new SettingsRepository([
            Settings::NO_DATABASE_OVERRIDE => $database,
        ]));

        $override->boot($app, $sprout);

        $app->make('session');
    }

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('sprout.overrides', []);
        });
    }

    protected function mockSessionManager(bool $database, bool $forget = true): SessionManager&MockInterface
    {
        return Mockery::mock(SessionManager::class, static function (MockInterface $mock) use ($database, $forget) {
            $mock->shouldReceive('extend')
                 ->withArgs([
                     Mockery::on(static function ($arg) {
                         return $arg === 'file' || $arg === 'native';
                     }),
                     Mockery::on(static function ($arg) {
                         return is_callable($arg) && $arg instanceof Closure;
                     }),
                 ])
                 ->twice();

            if ($database) {
                $mock->shouldNotReceive('extend')
                     ->withArgs([
                         'database',
                         Mockery::on(static function ($arg) {
                             return is_callable($arg) && $arg instanceof Closure;
                         }),
                     ]);
            } else {
                $mock->shouldReceive('extend')
                     ->withArgs([
                         'database',
                         Mockery::on(static function ($arg) {
                             return is_callable($arg) && $arg instanceof Closure;
                         }),
                     ])
                     ->once();
            }

            if ($forget) {
                $mock->shouldReceive('forgetDrivers')->once();
            }
        });
    }
}
