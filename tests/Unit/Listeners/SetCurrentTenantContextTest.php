<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Listeners;

use Illuminate\Support\Facades\Context;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Events\CurrentTenantChanged;
use Sprout\Listeners\SetCurrentTenantContext;
use Sprout\Tests\Unit\UnitTestCase;

class SetCurrentTenantContextTest extends UnitTestCase
{
    #[Test]
    public function addsTheCurrentTenantToContext(): void
    {
        $tenant = Mockery::mock(Tenant::class);
        $tenant->shouldReceive('getTenantKey')->andReturn(123)->once();

        $tenancy = Mockery::mock(Tenancy::class);
        $tenancy->shouldReceive('getName')->andReturn('tenants')->once();

        (new SetCurrentTenantContext())->handle(
            new CurrentTenantChanged($tenancy, current: $tenant),
        );

        $this->assertSame(['tenants' => 123], Context::get('sprout.tenants'));
    }
}
