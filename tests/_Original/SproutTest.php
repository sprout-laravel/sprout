<?php
declare(strict_types=1);

namespace Sprout\Tests\_Original;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Sprout;
use Workbench\App\Models\TenantModel;

#[Group('core')]
class SproutTest extends TestCase
{
    use WithWorkbench, RefreshDatabase;

    protected $enablesPackageDiscoveries = true;

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
        });
    }

    #[Test]
    public function makesCoreConfigAccessible(): void
    {
        $sprout = app()->make(Sprout::class);

        $this->assertNotNull($sprout->config('hooks'));
        $this->assertNotNull(config('sprout.hooks'));
        $this->assertSame($sprout->config('hooks'), config('sprout.hooks'));

        app()['config']->set('sprout.hooks', null);

        $this->assertNull($sprout->config('hooks'));
        $this->assertNull(config('sprout.hooks'));
        $this->assertSame($sprout->config('hooks'), config('sprout.hooks'));
    }

    #[Test]
    public function keepsTrackOfCurrentTenancies(): void
    {
        $sprout = app()->make(Sprout::class);

        $this->assertFalse($sprout->hasCurrentTenancy());
        $this->assertNull($sprout->getCurrentTenancy());
        $this->assertEmpty($sprout->getAllCurrentTenancies());

        $tenancy = $sprout->tenancies()->get('tenants');
        $sprout->setCurrentTenancy($tenancy);

        $this->assertTrue($sprout->hasCurrentTenancy());
        $this->assertNotNull($sprout->getCurrentTenancy());
        $this->assertSame($tenancy, $sprout->getCurrentTenancy());
        $this->assertNotEmpty($sprout->getAllCurrentTenancies());
        $this->assertCount(1, $sprout->getAllCurrentTenancies());

        $sprout->setCurrentTenancy($tenancy);

        $this->assertCount(1, $sprout->getAllCurrentTenancies());
    }
}
