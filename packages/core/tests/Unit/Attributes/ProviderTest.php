<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Attributes;

use PHPUnit\Framework\Attributes\Test;
use Sprout\Attributes\Provider;
use Sprout\Attributes\Tenancy;
use Sprout\Contracts\TenantProvider;
use Sprout\Managers\TenancyManager;
use Sprout\Managers\TenantProviderManager;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;

class ProviderTest extends UnitTestCase
{
    protected function defineEnvironment($app)
    {
        tap($app['config'], function ($config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);

            $config->set('multitenancy.providers.backup', [
                'driver' => 'database',
                'table'  => 'tenants',
            ]);
        });
    }

    #[Test]
    public function resolvesTenantProvider(): void
    {
        $manager = $this->app->make(TenantProviderManager::class);

        $callback1 = static function (#[Provider] TenantProvider $provider) {
            return $provider;
        };

        $callback2 = static function (#[Provider('backup')] TenantProvider $provider) {
            return $provider;
        };

        $this->assertSame($manager->get(), $this->app->call($callback1));
        $this->assertSame($manager->get('backup'), $this->app->call($callback2));
    }
}
