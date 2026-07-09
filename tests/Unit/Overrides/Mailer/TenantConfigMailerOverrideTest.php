<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Overrides\Mailer;

use Closure;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Mail\MailManager;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\ConfigStore;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantHasResources;
use Sprout\Exceptions\CyclicOverrideException;
use Sprout\Managers\ConfigStoreManager;
use Sprout\Overrides\Mailer\TenantConfigMailerOverride;
use Sprout\Sprout;
use Sprout\Support\SettingsRepository;
use Sprout\TenantConfig;
use Sprout\Tests\Unit\UnitTestCase;

use function Sprout\sprout;

class TenantConfigMailerOverrideTest extends UnitTestCase
{
    public static function mailerResolvedDataProvider(): array
    {
        return [
            'mailer resolved'     => [true],
            'mailer not resolved' => [false],
        ];
    }

    #[Test]
    public function isBuiltCorrectly(): void
    {
        $this->assertTrue(is_subclass_of(TenantConfigMailerOverride::class, BootableServiceOverride::class));
    }

    #[Test]
    public function isRegisteredWithSproutCorrectly(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'mailer' => [
                'driver' => TenantConfigMailerOverride::class,
            ],
        ]);

        $this->assertFalse($sprout->overrides()->hasOverride('mailer'));

        $sprout->overrides()->registerOverrides();

        $this->assertTrue($sprout->overrides()->hasOverride('mailer'));
        $this->assertSame(TenantConfigMailerOverride::class, $sprout->overrides()->getOverrideClass('mailer'));
        $this->assertTrue($sprout->overrides()->isOverrideBootable('mailer'));
        $this->assertTrue($sprout->overrides()->hasOverrideBooted('mailer'));
    }

    #[Test, DataProvider('mailerResolvedDataProvider')]
    public function bootsCorrectly(bool $return): void
    {
        $override = new TenantConfigMailerOverride('mailer', []);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, function (MockInterface $mock) use ($return) {
            $mock->makePartial();
            $mock->shouldReceive('resolved')->withArgs(['mail.manager'])->andReturn($return)->once();

            if ($return) {
                $mock->shouldReceive('make')
                     ->with('mail.manager')
                     ->andReturn($this->mockMailManager())
                     ->once();
            } else {
                $mock->shouldReceive('afterResolving')
                     ->withArgs([
                         'mail.manager',
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
    public function errorsIfOverriddenMailerAlsoUsesConfig(): void
    {
        $override = new TenantConfigMailerOverride('mailer', []);

        $tenant = Mockery::mock(Tenant::class, TenantHasResources::class, static function (MockInterface $mock) {
        });
        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) use ($tenant) {
            $mock->shouldReceive('check')->andReturnTrue()->once();
            $mock->shouldReceive('tenant')->andReturn($tenant)->once();
        });

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $app->make('config')->set('multitenancy.defaults.config', 'filesystem');
        $app->make('config')->set('mail.mailers.tenant-mailer', [
            'transport' => 'sprout:config',
        ]);

        $app->singleton(TenantConfig::class, fn () => new TenantConfig($app, Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($tenancy, $tenant) {
            $mock->shouldReceive('get')
                 ->andReturn(Mockery::mock(ConfigStore::class, function (MockInterface $mock) use ($tenancy, $tenant) {
                     $mock->shouldReceive('get')
                          ->with(
                              $tenancy,
                              $tenant,
                              'mailer',
                              'tenant-mailer',
                          )->andReturn([
                              'transport' => 'sprout:config',
                          ]);
                 }));
        })));

        $sprout = new Sprout($app, new SettingsRepository());

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        /** @var MailManager $manager */
        $manager = $app->make('mail.manager');

        $this->expectException(CyclicOverrideException::class);
        $this->expectExceptionMessage('Attempt to create cyclic config mailer [tenant-mailer] detected');

        $manager->mailer('tenant-mailer');
    }

    #[Test]
    public function keepsTrackOfResolvedConfigDrivers(): void
    {
        $override = new TenantConfigMailerOverride('mailer', []);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $app->make('config')->set('multitenancy.defaults.config', 'filesystem');
        $app->make('config')->set('mail.mailers.tenant-mailer', [
            'transport' => 'sprout:config',
        ]);

        $tenant = Mockery::mock(Tenant::class, TenantHasResources::class, static function (MockInterface $mock) {
        });

        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) use ($tenant) {
            $mock->shouldReceive('check')->andReturnTrue()->once();
            $mock->shouldReceive('tenant')->andReturn($tenant)->once();
        });

        $app->singleton(TenantConfig::class, fn () => new TenantConfig($app, Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($tenancy, $tenant) {
            $mock->shouldReceive('get')
                 ->andReturn(Mockery::mock(ConfigStore::class, function (MockInterface $mock) use ($tenancy, $tenant) {
                     $mock->shouldReceive('get')
                          ->with(
                              $tenancy,
                              $tenant,
                              'mailer',
                              'tenant-mailer',
                          )->andReturn([
                              'transport' => 'smtp',
                              'port'      => 25,
                              'host'      => 'localhost',
                          ]);
                 }));
        })));

        $sprout = new Sprout($app, new SettingsRepository());

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        /** @var MailManager $manager */
        $manager = $app->make('mail.manager');

        $manager->mailer('tenant-mailer');

        $this->assertContains('tenant-mailer', $override->getOverrides());
    }

    #[Test]
    public function cleansUpResolvedDrivers(): void
    {
        $override = new TenantConfigMailerOverride('mailer', []);

        $this->app->forgetInstance('mail.manager');

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        // Proxy the real mail manager so extend()/mailer() pass through, but assert
        // that cleanup purges the resolved tenant mailer.
        $manager = Mockery::mock($app->make('mail.manager'));
        $manager->shouldReceive('purge')->with('tenant-mailer')->once();
        $app->instance('mail.manager', $manager);

        $app->make('config')->set('multitenancy.defaults.config', 'filesystem');
        $app->make('config')->set('mail.mailers.tenant-mailer', [
            'transport' => 'sprout:config',
        ]);

        $tenant = Mockery::mock(Tenant::class, TenantHasResources::class, static function (MockInterface $mock) {
        });

        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) use ($tenant) {
            $mock->shouldReceive('check')->andReturnTrue()->once();
            $mock->shouldReceive('tenant')->andReturn($tenant)->once();
        });

        $app->singleton(TenantConfig::class, fn () => new TenantConfig($app, Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($tenancy, $tenant) {
            $mock->shouldReceive('get')
                 ->andReturn(Mockery::mock(ConfigStore::class, function (MockInterface $mock) use ($tenancy, $tenant) {
                     $mock->shouldReceive('get')
                          ->with(
                              $tenancy,
                              $tenant,
                              'mailer',
                              'tenant-mailer',
                          )->andReturn([
                              'transport' => 'smtp',
                              'port'      => 25,
                              'host'      => 'localhost',
                          ]);
                 }));
        })));

        $sprout = new Sprout($app, new SettingsRepository());

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        $this->assertEmpty($override->getOverrides());

        $manager->mailer('tenant-mailer');

        $this->assertNotEmpty($override->getOverrides());
        $this->assertContains('tenant-mailer', $override->getOverrides());

        $override->cleanup($tenancy, $tenant);

        $this->assertEmpty($override->getOverrides());
    }

    #[Test]
    public function cleansUpNothingWithoutResolvedDrivers(): void
    {
        $override = new TenantConfigMailerOverride('mailer', []);

        $this->app->forgetInstance('mail.manager');

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $app->make('config')->set('multitenancy.defaults.config', 'filesystem');
        $app->make('config')->set('mail.mailers.tenant-mailer', [
            'transport' => 'sprout:config',
        ]);

        $sprout  = new Sprout($app, new SettingsRepository());
        $tenant  = Mockery::mock(Tenant::class, TenantHasResources::class);
        $tenancy = Mockery::mock(Tenancy::class);

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        $this->assertEmpty($override->getOverrides());

        $app->make('mail.manager');

        $this->assertEmpty($override->getOverrides());

        $override->cleanup($tenancy, $tenant);

        $this->assertEmpty($override->getOverrides());
    }

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('sprout.overrides', []);
        });
    }

    private function mockMailManager(): MailManager&MockInterface
    {
        return Mockery::mock(MailManager::class, static function (MockInterface $mock) {
            $mock->shouldReceive('extend')
                 ->with('sprout:config', Mockery::on(static function ($arg) {
                     return is_callable($arg) && $arg instanceof Closure;
                 }))
                 ->once();
        });
    }
}
