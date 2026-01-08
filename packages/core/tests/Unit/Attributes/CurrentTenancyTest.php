<?php
declare(strict_types=1);

namespace Sprout\Core\Tests\Unit\Attributes;

use PHPUnit\Framework\Attributes\Test;
use Sprout\Core\Attributes\CurrentTenancy;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;
use function Sprout\Core\sprout;
use function Sprout\Core\tenancy;

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
        /** @var \Sprout\Core\Contracts\Tenancy $tenancy */
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
