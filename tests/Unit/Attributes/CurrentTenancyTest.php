<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Attributes;

use Illuminate\Config\Repository;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Attributes\CurrentTenancy;
use Sprout\Attributes\CurrentTenant;
use Sprout\Contracts\Tenancy;
use Sprout\Support\GenericTenant;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;
use function Sprout\sprout;
use function Sprout\tenancy;

class CurrentTenancyTest extends UnitTestCase
{
    protected function setsUpTenancy($app)
    {
        tap($app['config'], function ($config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
        });
    }

    #[Test]
    public function resolvesCurrentTenant(): void
    {
        /** @var \Sprout\Contracts\Tenancy $tenancy */
        $tenancy = tenancy('tenants');

        sprout()->setCurrentTenancy($tenancy);

        $tenant = TenantModel::factory()->createOne();

        $tenancy->setTenant($tenant);

        $callback = static function (#[CurrentTenancy] Tenancy $tenancy) {
            return $tenancy;
        };

        $currentTenant = $this->app->call($callback);

        $this->assertSame($tenancy, $currentTenant);
    }
}
