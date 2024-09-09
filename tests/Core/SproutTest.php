<?php
declare(strict_types=1);

namespace Sprout\Tests\Core;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Sprout;
use Workbench\App\Models\TenantModel;

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

        $this->assertTrue($sprout->config('listen_for_routing'));
        $this->assertTrue(config('sprout.listen_for_routing'));
        $this->assertNotNull($sprout->config('context'));
        $this->assertNotNull(config('sprout.context'));

        app()['config']->set('sprout.listen_for_routing', false);

        $this->assertFalse($sprout->config('listen_for_routing'));
        $this->assertFalse(config('sprout.listen_for_routing'));
    }

    #[Test]
    public function hasHelperForListeningToRoutingEvents(): void
    {
        $sprout = app()->make(Sprout::class);

        app()['config']->set('sprout.listen_for_routing', false);

        $this->assertFalse($sprout->config('listen_for_routing'));
        $this->assertFalse(config('sprout.listen_for_routing'));
        $this->assertFalse($sprout->shouldListenForRouting());

        app()['config']->set('sprout.listen_for_routing', true);

        $this->assertTrue($sprout->config('listen_for_routing'));
        $this->assertTrue(config('sprout.listen_for_routing'));
        $this->assertTrue($sprout->shouldListenForRouting());
    }

    #[Test]
    public function canProvideContextKeyForTenancy(): void
    {
        $sprout = app()->make(Sprout::class);
        $tenancy = $sprout->tenancies()->get('tenants');

        app()['config']->set('sprout.context.key', '{tenancy}_key');

        $this->assertSame('tenants_key', $sprout->contextKey($tenancy));

        app()['config']->set('sprout.context.key', 'the_key_for_the_{tenancy}');

        $this->assertSame('the_key_for_the_tenants', $sprout->contextKey($tenancy));
    }

    #[Test]
    public function canProvideContextValueForTenant(): void
    {
        $sprout = app()->make(Sprout::class);
        $tenant = TenantModel::first();

        app()['config']->set('sprout.context.use', 'key');

        $this->assertSame($tenant->getTenantKey(), $sprout->contextValue($tenant));

        app()['config']->set('sprout.context.use', 'identifier');

        $this->assertSame($tenant->getTenantIdentifier(), $sprout->contextValue($tenant));
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
