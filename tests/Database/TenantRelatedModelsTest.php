<?php
declare(strict_types=1);

namespace Sprout\Tests\Database;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Managers\TenancyManager;
use Workbench\App\Models\TenantChild;
use Workbench\App\Models\TenantChildren;
use Workbench\App\Models\TenantModel;

#[Group('database')]
class TenantRelatedModelsTest extends TestCase
{
    use WithWorkbench, RefreshDatabase;

    protected $enablesPackageDiscoveries = true;

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
        });
    }

    #[Test]
    public function populatesBelongsToTenantOnCreating(): void
    {
        $tenant = TenantModel::first();
        app(TenancyManager::class)->get()->setTenant($tenant);
        $child = TenantChild::create();

        $this->assertTrue($child->relationLoaded('tenant'));
        $this->assertTrue($child->tenant->is($tenant));
    }

    #[Test]
    public function hydratesBelongsToTenantOnRetrieved(): void
    {
        $tenant = TenantModel::first();
        app(TenancyManager::class)->get()->setTenant($tenant);
        $key   = TenantChild::create()->getKey();
        $child = TenantChild::find($key);

        $this->assertTrue($child->relationLoaded('tenant'));
        $this->assertTrue($child->tenant->is($tenant));
    }

    #[Test]
    public function populatesBelongsToManyTenantOnCreated(): void
    {
        $tenant = TenantModel::first();
        app(TenancyManager::class)->get()->setTenant($tenant);
        $child = TenantChildren::create();

        $this->assertTrue($child->relationLoaded('tenants'));
        $this->assertCount(1, $child->tenants);
        $this->assertTrue($child->tenants->first()->is($tenant));
    }

    #[Test]
    public function hydratesBelongsToManyTenantOnRetrieved(): void
    {
        $tenant = TenantModel::first();
        app(TenancyManager::class)->get()->setTenant($tenant);
        $key   = TenantChildren::create()->getKey();
        $child = TenantChildren::find($key);

        $this->assertTrue($child->relationLoaded('tenants'));
        $this->assertCount(1, $child->tenants);
        $this->assertTrue($child->tenants->first()->is($tenant));
    }
}
