<?php
declare(strict_types=1);

namespace Sprout\Tests\Overrides;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Exceptions\TenantMissing;
use Sprout\Managers\TenancyManager;
use Sprout\Overrides\AuthOverride;
use Sprout\Overrides\CacheOverride;
use Sprout\Overrides\CookieOverride;
use Sprout\Overrides\JobOverride;
use Sprout\Overrides\SessionOverride;
use Workbench\App\Models\NoResourcesTenantModel;
use Workbench\App\Models\TenantModel;

#[Group('services'), Group('filesystem')]
class StorageOverrideTest extends TestCase
{
    use WithWorkbench, RefreshDatabase;

    protected $enablesPackageDiscoveries = true;

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
        });
    }

    protected function createTenantDisk($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('filesystems.disks.tenant', [
                'driver'  => 'sprout',
                'disk'    => 'local',
                'tenancy' => 'tenants',
            ]);
        });
    }

    protected function noStorageOverride($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('sprout.services', [
                JobOverride::class,
                CacheOverride::class,
                AuthOverride::class,
                CookieOverride::class,
                SessionOverride::class,
            ]);
        });
    }

    #[Test, DefineEnvironment('createTenantDisk')]
    public function canCreateScopedTenantFilesystemDisk(): void
    {
        $tenant = TenantModel::factory()->createOne();

        app(TenancyManager::class)->get()->setTenant($tenant);

        $disk = Storage::disk('tenant');

        $this->assertNotNull($disk);
        $this->assertSame($tenant->getTenantResourceKey(), basename($disk->path('')));
    }

    #[Test, DefineEnvironment('createTenantDisk')]
    public function canCreateScopedTenantFilesystemDiskWithCustomConfig(): void
    {
        config()->set('filesystems.disks.tenant.disk', config('filesystems.disks.local'));

        $tenant = TenantModel::factory()->createOne();

        app(TenancyManager::class)->get()->setTenant($tenant);

        $disk = Storage::disk('tenant');

        $this->assertNotNull($disk);
        $this->assertSame($tenant->getTenantResourceKey(), basename($disk->path('')));
    }

    #[Test, DefineEnvironment('createTenantDisk')]
    public function throwsExceptionIfThereIsNoTenant(): void
    {
        $this->expectException(TenantMissing::class);
        $this->expectExceptionMessage('There is no current tenant for tenancy [tenants]');

        Storage::disk('tenant');
    }

    #[Test, DefineEnvironment('createTenantDisk')]
    public function throwsExceptionIfTheTenantDoesNotHaveResources(): void
    {
        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The current tenant [' . NoResourcesTenantModel::class . '] is not configured correctly for resources');

        config()->set('multitenancy.providers.tenants.model', NoResourcesTenantModel::class);

        app(TenancyManager::class)->get()->setTenant(NoResourcesTenantModel::factory()->createOne());

        Storage::disk('tenant');
    }

    #[Test, DefineEnvironment('createTenantDisk')]
    public function cleansUpStorageDiskAfterTenantChange(): void
    {
        $tenant = TenantModel::factory()->createOne();

        app(TenancyManager::class)->get()->setTenant($tenant);

        Storage::disk('tenant');

        Storage::shouldReceive('forgetDisk')->withArgs(['tenant'])->once();

        app(TenancyManager::class)->get()->setTenant(null);
    }

    #[Test, DefineEnvironment('createTenantDisk')]
    public function recreatesStorageDiskPerTenant(): void
    {
        $tenant1 = TenantModel::factory()->createOne();

        app(TenancyManager::class)->get()->setTenant($tenant1);

        $disk = Storage::disk('tenant');

        $this->assertNotNull($disk);
        $this->assertSame($tenant1->getTenantResourceKey(), basename($disk->path('')));

        $tenant2 = TenantModel::factory()->createOne();

        app(TenancyManager::class)->get()->setTenant($tenant2);

        $disk = Storage::disk('tenant');

        $this->assertNotNull($disk);
        $this->assertSame($tenant2->getTenantResourceKey(), basename($disk->path('')));
    }

    #[Test, DefineEnvironment('noStorageOverride')]
    public function doesNotOverrideStorageIfDisabled(): void
    {
        app(TenancyManager::class)->get()->setTenant(TenantModel::factory()->createOne());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Disk [tenant] does not have a configured driver.');

        Storage::disk('tenant');
    }
}
