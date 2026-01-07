<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Listeners;

use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Context;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Listeners\SetCurrentTenantForJob;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;
use function Sprout\sprout;

class SetCurrentTenantForJobTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
        });
    }

    #[Test]
    public function setsTenantsForJobs(): void
    {
        $tenant = TenantModel::factory()->createOne();

        Context::add('sprout.tenants', [
            'tenants' => $tenant->getTenantKey(),
        ]);

        $sprout = sprout();

        $this->assertFalse($sprout->hasCurrentTenancy());

        $listener = new SetCurrentTenantForJob($sprout, $sprout->tenancies());

        $listener->handle(Mockery::mock(JobProcessing::class));

        $this->assertTrue($sprout->hasCurrentTenancy());
        $this->assertSame('tenants', sprout()->getCurrentTenancy()->getName());
        $this->assertTrue(sprout()->getCurrentTenancy()->check());
    }
}
