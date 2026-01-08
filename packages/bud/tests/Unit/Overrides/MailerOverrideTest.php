<?php
declare(strict_types=1);

namespace Sprout\Bud\Tests\Unit\Overrides;

use Closure;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Mail\MailManager;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Bud\Bud;
use Sprout\Bud\Contracts\ConfigStore;
use Sprout\Bud\Exceptions\CyclicOverrideException;
use Sprout\Bud\Managers\ConfigStoreManager;
use Sprout\Bud\Overrides\MailerOverride;
use Sprout\Bud\Tests\Unit\UnitTestCase;
use Sprout\Core\Contracts\BootableServiceOverride;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Contracts\Tenant;
use Sprout\Core\Contracts\TenantHasResources;
use Sprout\Core\Sprout;
use Sprout\Core\Support\SettingsRepository;
use function Sprout\Core\sprout;

class MailerOverrideTest extends UnitTestCase
{
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
                 ->with('bud', Mockery::on(static function ($arg) {
                     return is_callable($arg) && $arg instanceof Closure;
                 }))
                 ->once();
        });
    }

    #[Test]
    public function isBuiltCorrectly(): void
    {
        $this->assertTrue(is_subclass_of(MailerOverride::class, BootableServiceOverride::class));
    }

    #[Test]
    public function isRegisteredWithSproutCorrectly(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'mailer' => [
                'driver' => MailerOverride::class,
            ],
        ]);

        $this->assertFalse($sprout->overrides()->hasOverride('mailer'));

        $sprout->overrides()->registerOverrides();

        $this->assertTrue($sprout->overrides()->hasOverride('mailer'));
        $this->assertSame(MailerOverride::class, $sprout->overrides()->getOverrideClass('mailer'));
        $this->assertTrue($sprout->overrides()->isOverrideBootable('mailer'));
        $this->assertTrue($sprout->overrides()->hasOverrideBooted('mailer'));
    }

    #[Test, DataProvider('mailerResolvedDataProvider')]
    public function bootsCorrectly(bool $return): void
    {
        $override = new MailerOverride('mailer', []);

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
    public function errorsIfOverriddenMailerAlsoUsesBud(): void
    {
        $override = new MailerOverride('mailer', []);

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
        $app->make('config')->set('mail.mailers.bud-mailer', [
            'transport' => 'bud',
        ]);

        $app->singleton(Bud::class, fn () => new Bud($app, Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($tenancy, $tenant) {
            $mock->shouldReceive('get')
                 ->andReturn(Mockery::mock(ConfigStore::class, function (MockInterface $mock) use ($tenancy, $tenant) {
                     $mock->shouldReceive('get')
                          ->with(
                              $tenancy,
                              $tenant,
                              'mailer',
                              'bud-mailer',
                          )->andReturn([
                             'transport' => 'bud',
                         ]);
                 }));
        })));

        $sprout = new Sprout($app, new SettingsRepository());

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        /** @var \Illuminate\Mail\MailManager $manager */
        $manager = $app->make('mail.manager');

        $this->expectException(CyclicOverrideException::class);
        $this->expectExceptionMessage('Attempt to create cyclic bud mailer [bud-mailer] detected');

        $manager->mailer('bud-mailer');
    }

    #[Test]
    public function keepsTrackOfResolvedBudDrivers(): void
    {
        $override = new MailerOverride('mailer', []);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $app->make('config')->set('multitenancy.defaults.config', 'filesystem');
        $app->make('config')->set('mail.mailers.bud-mailer', [
            'transport' => 'bud',
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
                              'mailer',
                              'bud-mailer',
                          )->andReturn([
                             'transport' => 'smtp',
                             'port' => 25,
                             'host' => 'localhost'
                         ]);
                 }));
        })));

        $sprout = new Sprout($app, new SettingsRepository());

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        /** @var \Illuminate\Mail\MailManager $manager */
        $manager = $app->make('mail.manager');

        $manager->mailer('bud-mailer');

        $this->assertContains('bud-mailer', $override->getOverrides());
    }

    #[Test]
    public function cleansUpResolvedDrivers(): void
    {
        $override = new MailerOverride('mailer', []);

        $this->app->forgetInstance('mail.manager');

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $app->make('config')->set('multitenancy.defaults.config', 'filesystem');
        $app->make('config')->set('mail.mailers.bud-mailer', [
            'transport' => 'bud',
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
                              'mailer',
                              'bud-mailer',
                          )->andReturn([
                             'transport' => 'smtp',
                             'port' => 25,
                             'host' => 'localhost'
                         ]);
                 }));
        })));

        $sprout = new Sprout($app, new SettingsRepository());

        $sprout->setCurrentTenancy($tenancy);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        $this->assertEmpty($override->getOverrides());

        /** @var \Illuminate\Mail\MailManager $manager */
        $manager = $app->make('mail.manager');

        $manager->mailer('bud-mailer');

        $this->assertNotEmpty($override->getOverrides());
        $this->assertContains('bud-mailer', $override->getOverrides());

        $override->cleanup($tenancy, $tenant);

        $this->assertEmpty($override->getOverrides());
    }

    #[Test]
    public function cleansUpNothingWithoutResolvedDrivers(): void
    {
        $override = new MailerOverride('mailer', []);

        $this->app->forgetInstance('mail.manager');

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $app->make('config')->set('multitenancy.defaults.config', 'filesystem');
        $app->make('config')->set('mail.mailers.bud-mailer', [
            'transport' => 'bud',
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

    public static function mailerResolvedDataProvider(): array
    {
        return [
            'mailer resolved'     => [true],
            'mailer not resolved' => [false],
        ];
    }
}
