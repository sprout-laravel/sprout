<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Attributes;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Mockery;
use Mockery\MockInterface;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Attributes\CurrentTenant;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Managers\TenancyManager;
use Sprout\Managers\TenantProviderManager;
use Sprout\Support\GenericTenant;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;
use function Sprout\sprout;
use function Sprout\tenancy;

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
        $tenancy = tenancy('tenants');

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
        $tenancy = tenancy('backup');

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

    #[Test]
    public function resolveDelegatesToTheTenancyManagerAndReturnsTheTenant(): void
    {
        $expected = Mockery::mock(Tenant::class);

        $tenancy = Mockery::mock(Tenancy::class, function (MockInterface $mock) use ($expected) {
            $mock->shouldReceive('tenant')->andReturn($expected)->once();
        });

        // TenancyManager is `final`; partial-mock a real instance. The
        // TenantProviderManager dependency is passed as a real instance
        // because TenancyManager's constructor type-hints the concrete
        // (final) class which Mockery cannot subclass.
        $app     = Mockery::mock(Application::class);
        $manager = Mockery::mock(new TenancyManager($app, new TenantProviderManager($app)));
        $manager->shouldReceive('get')->with('backup')->andReturn($tenancy)->once();

        $container = Mockery::mock(Container::class, function (MockInterface $mock) use ($manager) {
            $mock->shouldReceive('make')->with(TenancyManager::class)->andReturn($manager)->once();
        });

        $attribute = new CurrentTenant('backup');

        $this->assertSame($expected, $attribute->resolve($attribute, $container));
    }
}
