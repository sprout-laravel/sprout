<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Attributes;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Attributes\CurrentTenancy;
use Sprout\Contracts\Tenancy;
use Sprout\Sprout;
use Sprout\Support\SettingsRepository;
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

    #[Test]
    public function resolveDelegatesToSproutGetCurrentTenancy(): void
    {
        $expected = Mockery::mock(Tenancy::class);

        // Sprout is `final`, so build a real instance and partial-mock it.
        $sprout = Mockery::mock(new Sprout(
            Mockery::mock(Application::class),
            new SettingsRepository(),
        ));
        $sprout->shouldReceive('getCurrentTenancy')->andReturn($expected)->once();

        $container = Mockery::mock(Container::class, function (MockInterface $mock) use ($sprout) {
            $mock->shouldReceive('make')->with(Sprout::class)->andReturn($sprout)->once();
        });

        $attribute = new CurrentTenancy();

        $this->assertSame($expected, $attribute->resolve($attribute, $container));
    }
}
