<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit;

use Illuminate\Config\Repository;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Managers\TenancyManager;
use Sprout\TenancyOptions;

class TenancyOptionsTest extends UnitTestCase
{
    protected function setupSecondTenancy($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('multitenancy.providers.backup', [
                'driver' => 'database',
                'table'  => 'tenants',
            ]);

            $config->set('multitenancy.tenancies.backup', [
                'provider' => 'backup',
            ]);
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

    #[Test, DefineEnvironment('setupSecondTenancy')]
    public function correctlyReportsHydrateTenantRelationOptionPresence(): void
    {
        $tenancy = app(TenancyManager::class)->get('tenants');
        $tenancy->removeOption(TenancyOptions::hydrateTenantRelation());

        $this->assertFalse(TenancyOptions::shouldHydrateTenantRelation($tenancy));

        $tenancy->addOption(TenancyOptions::hydrateTenantRelation());

        $this->assertTrue(TenancyOptions::shouldHydrateTenantRelation($tenancy));

        $tenancy = app(TenancyManager::class)->get('backup');

        $this->assertFalse(TenancyOptions::shouldHydrateTenantRelation($tenancy));
    }

    #[Test, DefineEnvironment('setupSecondTenancy')]
    public function correctlyReportsThrowIfNotRelatedOptionPresence(): void
    {
        $tenancy = app(TenancyManager::class)->get('tenants');
        $tenancy->removeOption(TenancyOptions::throwIfNotRelated());

        $this->assertFalse(TenancyOptions::shouldThrowIfNotRelated($tenancy));

        $tenancy->addOption(TenancyOptions::throwIfNotRelated());

        $this->assertTrue(TenancyOptions::shouldThrowIfNotRelated($tenancy));

        $tenancy = app(TenancyManager::class)->get('backup');

        $this->assertFalse(TenancyOptions::shouldThrowIfNotRelated($tenancy));
    }
}
