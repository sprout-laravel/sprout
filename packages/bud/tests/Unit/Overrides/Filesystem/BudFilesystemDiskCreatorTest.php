<?php
declare(strict_types=1);

namespace Sprout\Bud\Tests\Unit\Overrides\Filesystem;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Bud\Bud;
use Sprout\Bud\Contracts\ConfigStore;
use Sprout\Bud\Managers\ConfigStoreManager;
use Sprout\Bud\Overrides\Broadcast\BudBroadcastConnectionCreator;
use Sprout\Bud\Overrides\Filesystem\BudFilesystemDiskCreator;
use Sprout\Bud\Tests\Unit\UnitTestCase;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantHasResources;
use Sprout\Exceptions\TenancyMissingException;
use Sprout\Exceptions\TenantMissingException;
use Sprout\Overrides\Filesystem\SproutFilesystemManager;
use Sprout\Sprout;
use Sprout\Support\SettingsRepository;

class BudFilesystemDiskCreatorTest extends UnitTestCase
{
    private function mockApplication(bool $default = false): Application&Mockery\MockInterface
    {
        return Mockery::mock(Application::class, static function (Mockery\MockInterface $mock) use ($default) {
            $mock->shouldIgnoreMissing();
        });
    }

    private function mockManager(bool $driver = true): SproutFilesystemManager&Mockery\MockInterface
    {
        return Mockery::mock(SproutFilesystemManager::class, static function (Mockery\MockInterface $mock) use ($driver) {
            if ($driver) {
                $mock->shouldReceive('build')
                     ->with(
                         ['name' => 'fake-disk', 'driver' => 'null'],
                     )
                     ->andReturn(Mockery::mock(Filesystem::class))
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
                                  'filesystem',
                                  'fake-disk'
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
            'name'   => 'fake-disk',
            'driver' => 'fake-driver',
        ];
        $sprout  = $this->getSprout($app);
        $bud     = $this->getBud($app, $this->mockConfigStoreManager($sprout->getCurrentTenancy(), $sprout->getCurrentTenancy()->tenant()));

        $creator = new BudFilesystemDiskCreator(
            $manager,
            $bud,
            $sprout,
            $config,
        );

        $creator();
    }

    #[Test]
    public function throwsAnExceptionWhenConfigIsMissingName(): void
    {
        $app     = $this->mockApplication();
        $manager = $this->mockManager(false);
        $config  = [];
        $sprout  = $this->getSprout($app, false, false);
        $bud     = $this->getBud($app, $this->mockConfigStoreManager());

        $sprout->markAsOutsideContext();

        $this->assertFalse($sprout->withinContext());

        $creator = new BudFilesystemDiskCreator(
            $manager,
            $bud,
            $sprout,
            $config,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Filesystem disk name must be provided');

        $creator();
    }

    #[Test]
    public function throwsAnExceptionWhenOutsideOfContext(): void
    {
        $app     = $this->mockApplication();
        $manager = $this->mockManager(false);
        $config  = [
            'name' => 'fake-disk',
        ];
        $sprout  = $this->getSprout($app, false, false);
        $bud     = $this->getBud($app, $this->mockConfigStoreManager());

        $sprout->markAsOutsideContext();

        $this->assertFalse($sprout->withinContext());

        $creator = new BudFilesystemDiskCreator(
            $manager,
            $bud,
            $sprout,
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
            'name' => 'fake-disk',
        ];
        $sprout  = $this->getSprout($app, false, false);
        $bud     = $this->getBud($app, $this->mockConfigStoreManager());

        $sprout->markAsInContext();

        $this->assertTrue($sprout->withinContext());

        $creator = new BudFilesystemDiskCreator(
            $manager,
            $bud,
            $sprout,
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
            'name' => 'fake-disk',
        ];
        $sprout  = $this->getSprout($app, true, false);
        $bud     = $this->getBud($app, $this->mockConfigStoreManager());

        $this->assertTrue($sprout->withinContext());

        $creator = new BudFilesystemDiskCreator(
            $manager,
            $bud,
            $sprout,
            $config,
        );

        $this->expectException(TenantMissingException::class);
        $this->expectExceptionMessage('There is no current tenant for tenancy [my-tenancy]');

        $creator();
    }
}
