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
use Sprout\Managers\TenancyManager;
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
}
