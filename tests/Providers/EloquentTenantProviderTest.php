<?php
declare(strict_types=1);

namespace Sprout\Tests\Providers;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Managers\ProviderManager;
use Sprout\Providers\EloquentTenantProvider;
use Sprout\Sprout;
use Workbench\App\Models\TenantModel;

class EloquentTenantProviderTest extends TestCase
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
    public function isRegisteredCorrectly(): void
    {
        $manager  = app(ProviderManager::class);
        $provider = $manager->get('tenants');

        $this->assertNotNull($provider);
        $this->assertInstanceOf(EloquentTenantProvider::class, $provider);
        $this->assertSame('tenants', $provider->getName());
        $this->assertSame(TenantModel::class, $provider->getModelClass());
    }

    #[Test]
    public function canRetrieveByIdentifier(): void
    {
        $newTenant = TenantModel::create(
            [
                'name'       => 'Test Tenant',
                'identifier' => 'the-big-test-boy',
                'active'     => true,
            ]
        );

        $provider = app(Sprout::class)->providers()->get('tenants');
        $tenant   = $provider->retrieveByIdentifier($newTenant->getTenantIdentifier());

        $this->assertNotNull($tenant);
        $this->assertTrue($newTenant->is($tenant));
    }

    #[Test]
    public function failsSilentlyWithInvalidIdentifier(): void
    {
        $provider = app(Sprout::class)->providers()->get('tenants');
        $tenant   = $provider->retrieveByIdentifier('i-do-not-exists-and-never-will');

        $this->assertNull($tenant);
    }

    #[Test]
    public function canRetrieveByKey(): void
    {
        $newTenant = TenantModel::create(
            [
                'name'       => 'Test Tenant',
                'identifier' => 'the-big-test-boy2',
                'active'     => true,
            ]
        );

        $provider = app(Sprout::class)->providers()->get('tenants');
        $tenant   = $provider->retrieveByKey($newTenant->getTenantKey());

        $this->assertNotNull($tenant);
        $this->assertTrue($newTenant->is($tenant));
    }

    #[Test]
    public function failsSilentlyWithInvalidKey(): void
    {
        $provider = app(Sprout::class)->providers()->get('tenants');
        $tenant   = $provider->retrieveByKey(889907);

        $this->assertNull($tenant);
    }
}
