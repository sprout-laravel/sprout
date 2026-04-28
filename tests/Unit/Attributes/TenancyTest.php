<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Attributes;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Attributes\Tenancy;
use Sprout\Contracts\Tenancy as TenancyContract;
use Sprout\Managers\TenancyManager;
use Sprout\Managers\TenantProviderManager;
use Sprout\Tests\Unit\UnitTestCase;
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

        $callback1 = static function (#[Tenancy] \Sprout\Contracts\Tenancy $tenancy) {
            return $tenancy;
        };

        $callback2 = static function (#[Tenancy('backup')] \Sprout\Contracts\Tenancy $tenancy) {
            return $tenancy;
        };

        $this->assertSame($manager->get(), $this->app->call($callback1));
        $this->assertSame($manager->get('backup'), $this->app->call($callback2));
    }

    #[Test]
    public function resolveDelegatesToTheTenancyManager(): void
    {
        $expected = Mockery::mock(TenancyContract::class);

        // TenancyManager is `final`; partial-mock a real instance. Its
        // TenantProviderManager dependency is also final, so we use a real
        // instance there.
        $app     = Mockery::mock(Application::class);
        $manager = Mockery::mock(new TenancyManager($app, new TenantProviderManager($app)));
        $manager->shouldReceive('get')->with('backup')->andReturn($expected)->once();

        $container = Mockery::mock(Container::class, function (MockInterface $mock) use ($manager) {
            $mock->shouldReceive('make')->with(TenancyManager::class)->andReturn($manager)->once();
        });

        $attribute = new Tenancy('backup');

        $this->assertSame($expected, $attribute->resolve($attribute, $container));
    }
}
