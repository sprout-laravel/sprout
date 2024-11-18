<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Attributes;

use Illuminate\Config\Repository;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Attributes\CurrentTenant;
use Sprout\Contracts\Tenant;
use Sprout\Managers\TenancyManager;
use Sprout\Support\GenericTenant;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;
use function Sprout\sprout;

class CurrentTenantTest extends UnitTestCase
{
    protected function setsUpTenancy($app)
    {
        tap($app['config'], function ($config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
        });
    }

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
    public function resolvesCurrentTenant(): void
    {
        /** @var \Sprout\Contracts\Tenancy $tenancy */
        $tenancy = app(TenancyManager::class)->get('tenants');

        sprout()->setCurrentTenancy($tenancy);

        $tenant = TenantModel::factory()->createOne();

        $tenancy->setTenant($tenant);

        $callback = static function (#[CurrentTenant] TenantModel $tenant) {
            return $tenant;
        };

        $currentTenant = $this->app->call($callback);

        $this->assertSame($tenant, $currentTenant);
        $this->assertSame($tenancy->tenant(), $currentTenant);
    }

    #[Test, DefineEnvironment('setupSecondTenancy')]
    public function resolvesCurrentTenantForSpecificTenancy(): void
    {
        /** @var \Sprout\Contracts\Tenancy $tenancy */
        $tenancy = app(TenancyManager::class)->get('backup');

        sprout()->setCurrentTenancy($tenancy);

        $tenant = new GenericTenant(TenantModel::factory()->createOne()->toArray());

        $tenancy->setTenant($tenant);

        $callback = static function (#[CurrentTenant('backup')] GenericTenant $tenant) {
            return $tenant;
        };

        $currentTenant = $this->app->call($callback);

        $this->assertSame($tenant, $currentTenant);
        $this->assertSame($tenancy->tenant(), $currentTenant);
    }
}
