<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Managers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Managers\TenantProviderManager;
use Sprout\Support\CachingTenantProvider;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;
use function Sprout\sprout;

class TenantProviderManagerCachingTest extends UnitTestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
            $config->set('cache.default', 'array');
        });
    }

    protected function withCacheEnabled($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.providers.tenants.cache', [
                'ttl'   => 3600,
                'store' => null,
            ]);
        });
    }

    #[Test]
    public function providerWithoutCacheConfigurationIsNotWrapped(): void
    {
        /** @var \Sprout\Managers\TenantProviderManager $manager */
        $manager  = sprout()->providers();
        $provider = $manager->get('tenants');

        $this->assertNotInstanceOf(CachingTenantProvider::class, $provider);
    }

    #[Test]
    public function providerWithCacheConfigurationIsWrappedWithCachingProvider(): void
    {
        $this->withCacheEnabled($this->app);
        $this->app->forgetInstance(TenantProviderManager::class);

        /** @var \Sprout\Managers\TenantProviderManager $manager */
        $manager  = sprout()->providers();
        $provider = $manager->get('tenants');

        $this->assertInstanceOf(CachingTenantProvider::class, $provider);
    }

    #[Test]
    public function cachingProviderCachesTenantLookups(): void
    {
        $this->withCacheEnabled($this->app);
        $this->app->forgetInstance(TenantProviderManager::class);

        $tenant = TenantModel::factory()->create([
            'identifier' => 'test-tenant',
            'name'       => 'Test Tenant',
        ]);

        /** @var \Sprout\Managers\TenantProviderManager $manager */
        $manager  = sprout()->providers();
        $provider = $manager->get('tenants');

        $this->assertInstanceOf(CachingTenantProvider::class, $provider);

        // First lookup - hits database
        $result1 = $provider->retrieveByIdentifier('test-tenant');
        $this->assertNotNull($result1);
        $this->assertEquals('test-tenant', $result1->getTenantIdentifier());

        // Delete from database
        $tenant->delete();

        // Second lookup - should still return cached tenant even though deleted
        $result2 = $provider->retrieveByIdentifier('test-tenant');
        $this->assertNotNull($result2);
        $this->assertEquals('test-tenant', $result2->getTenantIdentifier());
    }

    #[Test]
    public function flushingCacheReloadsFromDatabase(): void
    {
        $this->withCacheEnabled($this->app);
        $this->app->forgetInstance(TenantProviderManager::class);

        $tenant = TenantModel::factory()->create([
            'identifier' => 'test-tenant',
            'name'       => 'Test Tenant',
        ]);

        /** @var \Sprout\Managers\TenantProviderManager $manager */
        $manager = sprout()->providers();

        /** @var \Sprout\Support\CachingTenantProvider $provider */
        $provider = $manager->get('tenants');

        // First lookup - caches tenant
        $result1 = $provider->retrieveByIdentifier('test-tenant');
        $this->assertNotNull($result1);

        // Delete from database
        $tenant->delete();

        // Flush cache
        $provider->flush();

        // Now lookup should return null (not cached)
        $result2 = $provider->retrieveByIdentifier('test-tenant');
        $this->assertNull($result2);
    }

    #[Test]
    public function invalidatingSpecificTenantRemovesFromCache(): void
    {
        $this->withCacheEnabled($this->app);
        $this->app->forgetInstance(TenantProviderManager::class);

        $tenant1 = TenantModel::factory()->create(['identifier' => 'tenant-1']);
        $tenant2 = TenantModel::factory()->create(['identifier' => 'tenant-2']);

        /** @var \Sprout\Managers\TenantProviderManager $manager */
        $manager = sprout()->providers();

        /** @var \Sprout\Support\CachingTenantProvider $provider */
        $provider = $manager->get('tenants');

        // Cache both tenants
        $provider->retrieveByIdentifier('tenant-1');
        $provider->retrieveByIdentifier('tenant-2');

        // Delete tenant1 from database
        $tenant1->delete();

        // Invalidate tenant1 only
        $provider->invalidate($tenant1);

        // tenant1 should be gone from cache
        $result1 = $provider->retrieveByIdentifier('tenant-1');
        $this->assertNull($result1);

        // tenant2 should still be in cache
        $tenant2->delete(); // Delete from database
        $result2 = $provider->retrieveByIdentifier('tenant-2');
        $this->assertNotNull($result2); // Still cached
    }

    #[Test]
    public function retrieveByKeyUsesCache(): void
    {
        $this->withCacheEnabled($this->app);
        $this->app->forgetInstance(TenantProviderManager::class);

        $tenant = TenantModel::factory()->create(['identifier' => 'test-tenant']);

        /** @var \Sprout\Managers\TenantProviderManager $manager */
        $manager = sprout()->providers();

        /** @var \Sprout\Support\CachingTenantProvider $provider */
        $provider = $manager->get('tenants');

        // First lookup by key - caches tenant
        $result1 = $provider->retrieveByKey($tenant->id);
        $this->assertNotNull($result1);

        // Delete from database
        $tenant->delete();

        // Second lookup should return cached tenant
        $result2 = $provider->retrieveByKey($tenant->id);
        $this->assertNotNull($result2);
    }

    #[Test]
    public function cachingWorksInRealTenancyFlow(): void
    {
        $this->withCacheEnabled($this->app);
        $this->app->forgetInstance(TenantProviderManager::class);

        $tenant = TenantModel::factory()->create([
            'identifier' => 'acme-corp',
            'name'       => 'Acme Corporation',
        ]);

        /** @var \Sprout\Contracts\Tenancy $tenancy */
        $tenancy = sprout()->tenancies()->get('tenants');

        // First identification - hits database
        $identified1 = $tenancy->identify('acme-corp');
        $this->assertTrue($identified1);
        $this->assertEquals('acme-corp', $tenancy->identifier());

        // Clear the tenant so we can identify again
        $tenancy->setTenant(null);

        // Delete from database
        $tenant->delete();

        // Second identification - should use cache and still work
        $identified2 = $tenancy->identify('acme-corp');
        $this->assertTrue($identified2);
        $this->assertEquals('acme-corp', $tenancy->identifier());
    }
}
