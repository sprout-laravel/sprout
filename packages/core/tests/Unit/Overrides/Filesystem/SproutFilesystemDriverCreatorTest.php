<?php
declare(strict_types=1);

namespace Sprout\Core\Tests\Unit\Overrides\Filesystem;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Application;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Contracts\Tenant;
use Sprout\Core\Contracts\TenantHasResources;
use Sprout\Core\Exceptions\MisconfigurationException;
use Sprout\Core\Exceptions\TenancyMissingException;
use Sprout\Core\Exceptions\TenantMissingException;
use Sprout\Core\Overrides\Filesystem\SproutFilesystemDriverCreator;
use Sprout\Core\Sprout;
use Sprout\Core\Support\SettingsRepository;
use Sprout\Core\Tests\Unit\UnitTestCase;

class SproutFilesystemDriverCreatorTest extends UnitTestCase
{
    private function mockApplication(?string $configKey = null, ?array $config = null, bool $default = false): Application&Mockery\MockInterface
    {
        return Mockery::mock(Application::class, static function (Mockery\MockInterface $mock) use ($config, $configKey, $default) {
            if ($configKey) {
                $mock->shouldReceive('make')->with('config')->andReturn(
                    Mockery::mock(Repository::class, static function (Mockery\MockInterface $mock) use ($config, $configKey, $default) {
                        if ($default) {
                            $mock->shouldReceive('get')->with('filesystems.default')->andReturn('local')->once();
                        }

                        $mock->shouldReceive('get')->with($configKey)->andReturn($config)->once();
                    })
                )->once();
            }

            $mock->shouldIgnoreMissing();
        });
    }

    private function mockManager(?string $driver = null): FilesystemManager&Mockery\MockInterface
    {
        return Mockery::mock(FilesystemManager::class, static function (Mockery\MockInterface $mock) use ($driver) {
            if ($driver) {
                $mock->shouldReceive('createScopedDriver')
                     ->with(Mockery::on(function ($arg) use ($driver) {
                         return is_array($arg)
                                && (isset($arg['prefix']) && $arg['prefix'] === 'my-tenancy/my-resource-key')
                                && (isset($arg['disk']) && is_array($arg['disk']))
                                && (isset($arg['disk']['driver']) && $arg['disk']['driver'] === $driver);
                     }))
                     ->andReturn(Mockery::mock(Filesystem::class))
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
                    $mock->shouldReceive('getTenantResourceKey')->andReturn('my-resource-key')->once();
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
                    $mock->shouldReceive('tenant')->andReturn($tenant)->once();
                }

                $mock->shouldReceive('getName')->andReturn('my-tenancy')->once();
            }));
        }

        return $sprout;
    }

    #[Test]
    public function canCreateTheDriver(): void
    {
        $app     = $this->mockApplication('filesystems.disks.fake-disk', ['driver' => 'fake-driver']);
        $manager = $this->mockManager('fake-driver');
        $config  = ['disk' => 'fake-disk'];
        $sprout  = $this->getSprout($app);

        $creator = new SproutFilesystemDriverCreator(
            $app,
            $manager,
            $config,
            $sprout
        );

        $creator();
    }

    #[Test]
    public function fallsBackToDefaultDiskWhenCreatingDriver(): void
    {
        $app     = $this->mockApplication('filesystems.disks.local', ['driver' => 'local'], true);
        $manager = $this->mockManager('local');
        $config  = [];
        $sprout  = $this->getSprout($app);

        $creator = new SproutFilesystemDriverCreator(
            $app,
            $manager,
            $config,
            $sprout
        );

        $creator();
    }

    #[Test]
    public function canUseOnDemandDiskConfig(): void
    {
        $app     = $this->mockApplication();
        $manager = $this->mockManager('fake-driver');
        $config  = ['disk' => ['driver' => 'fake-driver']];
        $sprout  = $this->getSprout($app);

        $creator = new SproutFilesystemDriverCreator(
            $app,
            $manager,
            $config,
            $sprout
        );

        $creator();
    }

    #[Test]
    public function throwsAnExceptionWhenOutsideOfContext(): void
    {
        $app     = $this->mockApplication();
        $manager = $this->mockManager();
        $config  = [];
        $sprout  = $this->getSprout($app, false, false);

        $sprout->markAsOutsideContext();

        $this->assertFalse($sprout->withinContext());

        $creator = new SproutFilesystemDriverCreator(
            $app,
            $manager,
            $config,
            $sprout
        );

        $this->expectException(TenancyMissingException::class);
        $this->expectExceptionMessage('There is no current tenancy');

        $creator();
    }

    #[Test]
    public function throwsAnExceptionWhenThereIsNoTenancy(): void
    {
        $app     = $this->mockApplication();
        $manager = $this->mockManager();
        $config  = [];
        $sprout  = $this->getSprout($app, false, false);

        $sprout->markAsInContext();

        $this->assertTrue($sprout->withinContext());

        $creator = new SproutFilesystemDriverCreator(
            $app,
            $manager,
            $config,
            $sprout
        );

        $this->expectException(TenancyMissingException::class);
        $this->expectExceptionMessage('There is no current tenancy');

        $creator();
    }

    #[Test]
    public function throwsAnExceptionWhenThereIsNoTenant(): void
    {
        $app     = $this->mockApplication();
        $manager = $this->mockManager();
        $config  = [];
        $sprout  = $this->getSprout($app, true, false);

        $this->assertTrue($sprout->withinContext());

        $creator = new SproutFilesystemDriverCreator(
            $app,
            $manager,
            $config,
            $sprout
        );

        $this->expectException(TenantMissingException::class);
        $this->expectExceptionMessage('There is no current tenant for tenancy [my-tenancy]');

        $creator();
    }

    #[Test]
    public function throwsAnExceptionWhenTheTenantIsNotConfiguredForResources(): void
    {
        $app     = $this->mockApplication();
        $manager = $this->mockManager();
        $config  = [];
        $sprout  = $this->getSprout($app, true, true, false);

        $this->assertTrue($sprout->withinContext());

        $creator = new SproutFilesystemDriverCreator(
            $app,
            $manager,
            $config,
            $sprout
        );

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The current tenant [my-tenancy] is not configured correctly for resources');

        $creator();
    }
}
