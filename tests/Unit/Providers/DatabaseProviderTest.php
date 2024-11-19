<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Providers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Providers\DatabaseTenantProvider;
use Sprout\Support\GenericTenant;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\CustomTenantEntity;
use function Sprout\provider;
use function Sprout\sprout;

class DatabaseProviderTest extends UnitTestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.providers.tenants.driver', 'database');
            $config->set('multitenancy.providers.tenants.table', 'tenants');
        });
    }

    protected function withCustomTenantEntity($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.providers.tenants.entity', CustomTenantEntity::class);
        });
    }

    #[Test]
    public function hasARegisteredName(): void
    {
        $provider = provider('tenants');

        $this->assertInstanceOf(DatabaseTenantProvider::class, $provider);
        $this->assertSame('tenants', $provider->getName());
    }

    #[Test]
    public function hasATable(): void
    {
        $provider = provider('tenants');

        $this->assertInstanceOf(DatabaseTenantProvider::class, $provider);
        $this->assertSame('tenants', $provider->getTable());
    }

    #[Test]
    public function hasATenantEntity(): void
    {
        $provider = provider('tenants');

        $this->assertInstanceOf(DatabaseTenantProvider::class, $provider);
        $this->assertSame(GenericTenant::class, $provider->getEntityClass());
    }

    #[Test]
    public function retrievesTenantsByTheirIdentifier(): void
    {
        $provider = provider('tenants');

        $tenantData = [
            'name'       => 'Test Tenant',
            'identifier' => 'tenant-test',
            'active'     => true,
        ];

        $tenantData['id'] = DB::table('tenants')->insertGetId($tenantData);

        $found = $provider->retrieveByIdentifier($tenantData['identifier']);

        $this->assertNotNull($found);
        $this->assertInstanceOf(GenericTenant::class, $found);
        $this->assertSame($tenantData['identifier'], $found->getTenantIdentifier());
        $this->assertSame($tenantData['id'], $found->getTenantKey());

        $this->assertNull($provider->retrieveByIdentifier('fake-identifier'));
    }

    #[Test]
    public function retrievesTenantsByTheirKey(): void
    {
        $provider = provider('tenants');

        $tenantData = [
            'name'       => 'Test Tenant',
            'identifier' => 'tenant-test',
            'active'     => true,
        ];

        $tenantData['id'] = DB::table('tenants')->insertGetId($tenantData);

        $found = $provider->retrieveByKey($tenantData['id']);

        $this->assertNotNull($found);
        $this->assertInstanceOf(GenericTenant::class, $found);
        $this->assertSame($tenantData['identifier'], $found->getTenantIdentifier());
        $this->assertSame($tenantData['id'], $found->getTenantKey());

        $this->assertNull($provider->retrieveByKey(-999));
    }

    #[Test, DefineEnvironment('withCustomTenantEntity')]
    public function canHaveCustomTenantEntity(): void
    {
        // This is necessary as the provider has already been resolved
        sprout()->providers()->flushResolved();

        $provider = provider('tenants');

        $this->assertInstanceOf(DatabaseTenantProvider::class, $provider);
        $this->assertSame(CustomTenantEntity::class, $provider->getEntityClass());
    }
}
