<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Overrides\Auth;

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Foundation\Application;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Sprout\TenantConfig;
use Sprout\Contracts\ConfigStore;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantHasResources;
use Sprout\Exceptions\TenancyMissingException;
use Sprout\Exceptions\TenantMissingException;
use Sprout\Managers\ConfigStoreManager;
use Sprout\Overrides\Auth\TenantConfigAuthManager;
use Sprout\Overrides\Auth\TenantConfigAuthProviderCreator;
use Sprout\Sprout;
use Sprout\Support\SettingsRepository;
use Sprout\Tests\Unit\UnitTestCase;

class TenantConfigAuthManagerCreatorTest extends UnitTestCase
{
    private function mockApplication(bool $default = false): Application&Mockery\MockInterface
    {
        return Mockery::mock(Application::class, static function (Mockery\MockInterface $mock) use ($default) {
            $mock->shouldIgnoreMissing();
        });
    }

    private function mockManager(bool $driver = true): TenantConfigAuthManager&Mockery\MockInterface
    {
        return Mockery::mock(TenantConfigAuthManager::class, static function (Mockery\MockInterface $mock) use ($driver) {
            if ($driver) {
                $mock->shouldReceive('createUserProviderFromConfig')
                     ->with(
                         ['provider' => 'fake-provider', 'driver' => 'null'],
                     )
                     ->andReturn(Mockery::mock(UserProvider::class))
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
                                  'auth',
                                  'fake-provider'
                              )
                              ->andReturn([
                                  'driver' => 'null',
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

    private function getTenantConfig(Application $app, ConfigStoreManager $manager): TenantConfig
    {
        return new TenantConfig($app, $manager);
    }

    #[Test]
    public function canCreateTheDriver(): void
    {
        $app     = $this->mockApplication();
        $manager = $this->mockManager();
        $config  = [
            'provider' => 'fake-provider',
            'driver'   => 'fake-driver',
        ];
        $sprout  = $this->getSprout($app);
        $tenantConfig     = $this->getTenantConfig($app, $this->mockConfigStoreManager($sprout->getCurrentTenancy(), $sprout->getCurrentTenancy()->tenant()));

        $creator = new TenantConfigAuthProviderCreator(
            $manager,
            $tenantConfig,
            $sprout,
            'fake-provider',
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
            'provider' => 'fake-provider',
        ];
        $sprout  = $this->getSprout($app, false, false);
        $tenantConfig     = $this->getTenantConfig($app, $this->mockConfigStoreManager());

        $sprout->markAsOutsideContext();

        $this->assertFalse($sprout->withinContext());

        $creator = new TenantConfigAuthProviderCreator(
            $manager,
            $tenantConfig,
            $sprout,
            'fake-provider',
            $config
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
            'provider' => 'fake-provider',
        ];
        $sprout  = $this->getSprout($app, false, false);
        $tenantConfig     = $this->getTenantConfig($app, $this->mockConfigStoreManager());

        $sprout->markAsInContext();

        $this->assertTrue($sprout->withinContext());

        $creator = new TenantConfigAuthProviderCreator(
            $manager,
            $tenantConfig,
            $sprout,
            'fake-provider',
            $config
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
            'provider' => 'fake-provider',
        ];
        $sprout  = $this->getSprout($app, true, false);
        $tenantConfig     = $this->getTenantConfig($app, $this->mockConfigStoreManager());

        $this->assertTrue($sprout->withinContext());

        $creator = new TenantConfigAuthProviderCreator(
            $manager,
            $tenantConfig,
            $sprout,
            'fake-provider',
            $config
        );

        $this->expectException(TenantMissingException::class);
        $this->expectExceptionMessage('There is no current tenant for tenancy [my-tenancy]');

        $creator();
    }
}
