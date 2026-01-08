<?php
declare(strict_types=1);

namespace Sprout\Bud\Tests\Unit\Overrides\Mailer;

use Illuminate\Foundation\Application;
use Illuminate\Mail\MailManager;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Bud\Bud;
use Sprout\Bud\Contracts\ConfigStore;
use Sprout\Bud\Managers\ConfigStoreManager;
use Sprout\Bud\Overrides\Mailer\BudMailerTransportCreator;
use Sprout\Bud\Tests\Unit\UnitTestCase;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Contracts\Tenant;
use Sprout\Core\Contracts\TenantHasResources;
use Sprout\Core\Exceptions\TenancyMissingException;
use Sprout\Core\Exceptions\TenantMissingException;
use Sprout\Core\Sprout;
use Sprout\Core\Support\SettingsRepository;
use Symfony\Component\Mailer\Transport\TransportInterface;

class BudMailerCreatorTest extends UnitTestCase
{
    private function mockApplication(bool $default = false): Application&Mockery\MockInterface
    {
        return Mockery::mock(Application::class, static function (Mockery\MockInterface $mock) use ($default) {
            $mock->shouldIgnoreMissing();
        });
    }

    private function mockManager(bool $driver = true): MailManager&Mockery\MockInterface
    {
        return Mockery::mock(MailManager::class, static function (Mockery\MockInterface $mock) use ($driver) {
            if ($driver) {
                $mock->shouldReceive('createSymfonyTransport')
                     ->with(
                         ['name' => 'fake-mailer', 'driver' => 'fake-mailer', 'transport' => 'null'],
                     )
                     ->andReturn(Mockery::mock(TransportInterface::class))
                     ->once();
            }
        });
    }

    private function mockConfigStoreManager(?Tenancy $tenancy = null, ?Tenant $tenant = null): ConfigStoreManager&Mockery\MockInterface
    {
        return Mockery::mock(ConfigStoreManager::class, static function (Mockery\MockInterface $mock) use ($tenancy, $tenant) {
            if ($tenancy && $tenant) {
                $mock->shouldReceive('get')
                     ->with(null)
                     ->andReturn(Mockery::mock(ConfigStore::class, static function (Mockery\MockInterface $mock) use ($tenancy, $tenant) {
                         $mock->shouldReceive('get')
                              ->with(
                                  $tenancy,
                                  $tenant,
                                  'mailer',
                                  'fake-mailer'
                              )
                              ->andReturn([
                                  'transport' => 'null',
                              ])
                              ->once();
                     }))
                     ->once();
            }
        });
    }

    private function getSprout(Application $app, bool $withTenancy = true, bool $withTenant = true, bool $withResources = true): Sprout
    {
        $sprout = new Sprout($app, new SettingsRepository());

        if ($withTenant) {
            if ($withResources) {
                $tenant = Mockery::mock(Tenant::class, TenantHasResources::class, static function (Mockery\MockInterface $mock) {
                });
            } else {
                $tenant = Mockery::mock(Tenant::class);
            }
        } else {
            $tenant = null;
        }

        if ($withTenancy) {
            $sprout->setCurrentTenancy(Mockery::mock(Tenancy::class, static function (Mockery\MockInterface $mock) use ($tenant, $withTenant) {
                $mock->shouldReceive('check')->andReturn($withTenant)->once();

                if ($withTenant) {
                    $mock->shouldReceive('tenant')->andReturn($tenant)->twice();
                } else {
                    $mock->shouldReceive('getName')->andReturn('my-tenancy')->once();
                }
            }));
        }

        return $sprout;
    }

    private function getBud(Application $app, ConfigStoreManager $manager): Bud
    {
        return new Bud($app, $manager);
    }

    #[Test]
    public function canCreateTheDriver(): void
    {
        $app     = $this->mockApplication();
        $manager = $this->mockManager();
        $config  = [
            'name'   => 'fake-mailer',
            'driver' => 'fake-mailer',
        ];
        $sprout  = $this->getSprout($app);
        $bud     = $this->getBud($app, $this->mockConfigStoreManager($sprout->getCurrentTenancy(), $sprout->getCurrentTenancy()->tenant()));

        $creator = new BudMailerTransportCreator(
            $manager,
            $bud,
            $sprout,
            'fake-mailer',
            $config,
        );

        $creator();
    }

    #[Test]
    public function throwsAnExceptionWhenOutsideOfContext(): void
    {
        $app     = $this->mockApplication();
        $manager = $this->mockManager(false);
        $config  = [
            'name' => 'fake-mailer',
        ];
        $sprout  = $this->getSprout($app, false, false);
        $bud     = $this->getBud($app, $this->mockConfigStoreManager());

        $sprout->markAsOutsideContext();

        $this->assertFalse($sprout->withinContext());

        $creator = new BudMailerTransportCreator(
            $manager,
            $bud,
            $sprout,
            'fake-mailer',
            $config,
        );

        $this->expectException(TenancyMissingException::class);
        $this->expectExceptionMessage('There is no current tenancy');

        $creator();
    }

    #[Test]
    public function throwsAnExceptionWhenThereIsNoTenancy(): void
    {
        $app     = $this->mockApplication();
        $manager = $this->mockManager(false);
        $config  = [
            'name' => 'fake-mailer',
        ];
        $sprout  = $this->getSprout($app, false, false);
        $bud     = $this->getBud($app, $this->mockConfigStoreManager());

        $sprout->markAsInContext();

        $this->assertTrue($sprout->withinContext());

        $creator = new BudMailerTransportCreator(
            $manager,
            $bud,
            $sprout,
            'fake-mailer',
            $config,
        );

        $this->expectException(TenancyMissingException::class);
        $this->expectExceptionMessage('There is no current tenancy');

        $creator();
    }

    #[Test]
    public function throwsAnExceptionWhenThereIsNoTenant(): void
    {
        $app     = $this->mockApplication();
        $manager = $this->mockManager(false);
        $config  = [
            'name' => 'fake-mailer',
        ];
        $sprout  = $this->getSprout($app, true, false);
        $bud     = $this->getBud($app, $this->mockConfigStoreManager());

        $this->assertTrue($sprout->withinContext());

        $creator = new BudMailerTransportCreator(
            $manager,
            $bud,
            $sprout,
            'fake-mailer',
            $config,
        );

        $this->expectException(TenantMissingException::class);
        $this->expectExceptionMessage('There is no current tenant for tenancy [my-tenancy]');

        $creator();
    }
}
