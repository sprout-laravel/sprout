<?php
declare(strict_types=1);

namespace Sprout\Tests\_Original;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Managers\TenancyManager;
use Sprout\TenancyOptions;
use Workbench\App\Models\TenantModel;

#[Group('core')]
class TenancyOptionsTest extends TestCase
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
    public function hydrateTenantRelationOption(): void
    {
        $this->assertSame('tenant-relation.hydrate', TenancyOptions::hydrateTenantRelation());
    }

    #[Test]
    public function throwIfNotRelatedOption(): void
    {
        $this->assertSame('tenant-relation.strict', TenancyOptions::throwIfNotRelated());
    }

    #[Test]
    public function correctlyReportsHydrateTenantRelationOptionPresence(): void
    {
        $tenancy = app(TenancyManager::class)->get('tenants');
        $tenancy->removeOption(TenancyOptions::hydrateTenantRelation());

        $this->assertFalse(TenancyOptions::shouldHydrateTenantRelation($tenancy));

        $tenancy->addOption(TenancyOptions::hydrateTenantRelation());

        $this->assertTrue(TenancyOptions::shouldHydrateTenantRelation($tenancy));
    }

    #[Test]
    public function correctlyReportsThrowIfNotRelatedOptionPresence(): void
    {
        $tenancy = app(TenancyManager::class)->get('tenants');
        $tenancy->removeOption(TenancyOptions::throwIfNotRelated());

        $this->assertFalse(TenancyOptions::shouldThrowIfNotRelated($tenancy));

        $tenancy->addOption(TenancyOptions::throwIfNotRelated());

        $this->assertTrue(TenancyOptions::shouldThrowIfNotRelated($tenancy));
    }
}
