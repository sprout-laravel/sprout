<?php
declare(strict_types=1);

namespace Sprout\Tests\Services;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Sprout\Exceptions\TenantMissing;
use Sprout\Managers\TenancyManager;
use Workbench\App\Models\NoResourcesTenantModel;
use Workbench\App\Models\TenantModel;

#[Group('services'), Group('filesystem')]
class FilesystemTest extends TestCase
{
    use WithWorkbench, RefreshDatabase;

    protected $enablesPackageDiscoveries = true;

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
            $config->set('filesystems.disks.tenant', [
                'driver'  => 'sprout',
                'disk'    => 'local',
                'tenancy' => 'tenants',
            ]);
        });
    }

    #[Test]
    public function canCreateScopedTenantFilesystemDisk(): void
    {
        $tenant = TenantModel::factory()->createOne();

        app(TenancyManager::class)->get()->setTenant($tenant);

        $disk = Storage::disk('tenant');

        $this->assertNotNull($disk);
        $this->assertSame($tenant->getTenantResourceKey(), basename($disk->path('')));
    }

    #[Test]
    public function canCreateScopedTenantFilesystemDiskWithCustomConfig(): void
    {
        config()->set('filesystems.disks.tenant.disk', config('filesystems.disks.local'));

        $tenant = TenantModel::factory()->createOne();

        app(TenancyManager::class)->get()->setTenant($tenant);

        $disk = Storage::disk('tenant');

        $this->assertNotNull($disk);
        $this->assertSame($tenant->getTenantResourceKey(), basename($disk->path('')));
    }

    #[Test]
    public function throwsExceptionIfThereIsNoTenant(): void
    {
        $this->expectException(TenantMissing::class);
        $this->expectExceptionMessage('There is no current tenant for tenancy [tenants]');

        Storage::disk('tenant');
    }

    #[Test]
    public function throwsExceptionIfTheTenantDoesNotHaveResources(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Current tenant isn\t configured for resources');

        config()->set('multitenancy.providers.tenants.model', NoResourcesTenantModel::class);

        app(TenancyManager::class)->get()->setTenant(NoResourcesTenantModel::factory()->createOne());

        Storage::disk('tenant');
    }

    #[Test]
    public function cleansUpStorageDiskAfterTenantChange(): void
    {
        $tenant = TenantModel::factory()->createOne();

        app(TenancyManager::class)->get()->setTenant($tenant);

        Storage::disk('tenant');

        Storage::shouldReceive('forgetDisk')->withArgs(['tenant'])->once();

        app(TenancyManager::class)->get()->setTenant(null);
    }

    #[Test]
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
}
