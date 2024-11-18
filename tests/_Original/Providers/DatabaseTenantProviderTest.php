<?php
declare(strict_types=1);

namespace Sprout\Tests\_Original\Providers;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Managers\ProviderManager;
use Sprout\Providers\DatabaseTenantProvider;
use Sprout\Sprout;
use Sprout\Support\GenericTenant;

class DatabaseTenantProviderTest extends TestCase
{
    use WithWorkbench, RefreshDatabase;

    protected $enablesPackageDiscoveries = true;

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('multitenancy.providers.backup', [
                'driver' => 'database',
                'table'  => 'tenants',
            ]);
        });
    }

    #[Test]
    public function isRegisteredCorrectly(): void
    {
        $manager  = app(ProviderManager::class);
        $provider = $manager->get('backup');

        $this->assertNotNull($provider);
        $this->assertInstanceOf(DatabaseTenantProvider::class, $provider);
        $this->assertSame('backup', $provider->getName());
        $this->assertSame('tenants', $provider->getTable());
        $this->assertSame(GenericTenant::class, $provider->getEntityClass());
    }

    #[Test]
    public function canRetrieveByIdentifier(): void
    {
        $id = DB::table('tenants')->insertGetId(
            [
                'name'         => 'Test Tenant',
                'identifier'   => 'the-big-test-boy',
                'resource_key' => Str::uuid()->toString(),
                'active'       => true,
            ]
        );

        $provider = app(Sprout::class)->providers()->get('backup');
        $tenant   = $provider->retrieveByIdentifier('the-big-test-boy');

        $this->assertNotNull($tenant);
        $this->assertInstanceOf(GenericTenant::class, $tenant);
        $this->assertSame('the-big-test-boy', $tenant->getTenantIdentifier());
        $this->assertSame($id, $tenant->getTenantKey());
    }

    #[Test]
    public function failsSilentlyWithInvalidIdentifier(): void
    {
        $provider = app(Sprout::class)->providers()->get('backup');
        $tenant   = $provider->retrieveByIdentifier('i-do-not-exists-and-never-will');

        $this->assertNull($tenant);
    }

    #[Test]
    public function canRetrieveByKey(): void
    {
        $id = DB::table('tenants')->insertGetId(
            [
                'name'         => 'Test Tenant',
                'identifier'   => 'the-big-test-boy2',
                'resource_key' => Str::uuid()->toString(),
                'active'       => true,
            ]
        );

        $provider = app(Sprout::class)->providers()->get('backup');
        $tenant   = $provider->retrieveByKey($id);

        $this->assertNotNull($tenant);
        $this->assertInstanceOf(GenericTenant::class, $tenant);
        $this->assertSame('the-big-test-boy2', $tenant->getTenantIdentifier());
        $this->assertSame($id, $tenant->getTenantKey());
    }

    #[Test]
    public function failsSilentlyWithInvalidKey(): void
    {
        $provider = app(Sprout::class)->providers()->get('backup');
        $tenant   = $provider->retrieveByKey(889907);

        $this->assertNull($tenant);
    }
}
