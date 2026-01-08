<?php
declare(strict_types=1);

namespace Sprout\Core\Tests\Unit\Attributes;

use PHPUnit\Framework\Attributes\Test;
use Sprout\Core\Attributes\Tenancy;
use Sprout\Core\Managers\TenancyManager;
use Sprout\Core\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;

class TenancyTest extends UnitTestCase
{
    protected function defineEnvironment($app)
    {
        tap($app['config'], function ($config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);

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
    public function resolvesTenancy(): void
    {
        $manager = $this->app->make(TenancyManager::class);

        $callback1 = static function (#[Tenancy] \Sprout\Core\Contracts\Tenancy $tenancy) {
            return $tenancy;
        };

        $callback2 = static function (#[Tenancy('backup')] \Sprout\Core\Contracts\Tenancy $tenancy) {
            return $tenancy;
        };

        $this->assertSame($manager->get(), $this->app->call($callback1));
        $this->assertSame($manager->get('backup'), $this->app->call($callback2));
    }
}
