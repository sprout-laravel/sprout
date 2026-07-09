<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Listeners;

use Closure;
use Illuminate\Foundation\Application;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Events\CurrentTenantChanged;
use Sprout\Listeners\RefreshTenantAwareDependencies;
use Sprout\Tests\Unit\UnitTestCase;

class RefreshTenantAwareDependenciesTest extends UnitTestCase
{
    #[Test]
    public function refreshesTheTenantBindingWhenATenantIsSet(): void
    {
        $tenancy = Mockery::mock(Tenancy::class);
        $tenant  = Mockery::mock(Tenant::class);

        $app = Mockery::mock(Application::class);
        $app->shouldReceive('forgetExtenders')->with(Tenant::class)->once();
        $app->shouldReceive('extend')->with(Tenant::class, Mockery::type(Closure::class))->once();

        (new RefreshTenantAwareDependencies($app))->handle(
            new CurrentTenantChanged($tenancy, current: $tenant),
        );
    }

    #[Test]
    public function doesNothingWhenThereIsNoTenant(): void
    {
        $tenancy = Mockery::mock(Tenancy::class);

        $app = Mockery::mock(Application::class);
        $app->shouldNotReceive('forgetExtenders');
        $app->shouldNotReceive('extend');

        (new RefreshTenantAwareDependencies($app))->handle(
            new CurrentTenantChanged($tenancy),
        );
    }
}
