<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Listeners;

use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\IdentityResolver;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Events\CurrentTenantChanged;
use Sprout\Listeners\PerformIdentityResolverSetup;
use Sprout\Tests\Unit\UnitTestCase;

class PerformIdentityResolverSetupTest extends UnitTestCase
{
    #[Test]
    public function callsSetupOnTheTenancyResolver(): void
    {
        $tenant   = Mockery::mock(Tenant::class);
        $resolver = Mockery::mock(IdentityResolver::class);

        $tenancy = Mockery::mock(Tenancy::class);
        $tenancy->shouldReceive('resolver')->andReturn($resolver)->once();

        $event = new CurrentTenantChanged($tenancy, current: $tenant);

        $resolver->shouldReceive('setup')->with($tenancy, $tenant)->once();

        (new PerformIdentityResolverSetup())->handle($event);
    }
}
