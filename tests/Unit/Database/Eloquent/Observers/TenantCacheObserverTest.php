<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Database\Eloquent\Observers;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantProvider;
use Sprout\Database\Eloquent\Observers\TenantCacheObserver;
use Sprout\Managers\TenantProviderManager;
use Sprout\Support\CachingTenantProvider;
use Sprout\Support\TenantCacheInvalidator;
use Sprout\Tests\Unit\UnitTestCase;

class TenantCacheObserverTest extends UnitTestCase
{
    private TenantCacheInvalidator   $invalidator;
    private Repository&MockInterface $mockCache;
    private TenantProviderManager    $providerManager;
    private Tenant&MockInterface     $mockTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $mockApp = Mockery::mock(Application::class);
        $mockApp->shouldReceive('get')->with('config')->andReturn($mockApp);
        $mockApp->shouldReceive('get')->with('multitenancy', Mockery::any())->andReturn([]);

        $this->providerManager = new TenantProviderManager($mockApp, 'config');
        $this->mockCache       = Mockery::mock(Repository::class);
        $this->invalidator     = new TenantCacheInvalidator($this->providerManager, $this->mockCache);
        $this->mockTenant      = Mockery::mock(Tenant::class);

        $this->mockTenant->shouldReceive('getTenantIdentifier')->andReturn('test-123');
        $this->mockTenant->shouldReceive('getTenantKey')->andReturn(456);
    }

    /**
     * Create a mock cache store that supports tags
     */
    private function createCacheStoreWithTags(): object
    {
        return new class {
            public function tags(): void {
                // Mock tags method
            }
        };
    }

    #[Test]
    public function savedEventInvalidatesTenantCache(): void
    {
        $mockBaseProvider = Mockery::mock(TenantProvider::class);
        $mockBaseProvider->shouldReceive('getName')->andReturn('test');

        // getStore() is called in constructor for validation
        $this->mockCache->shouldReceive('getStore')->andReturn($this->createCacheStoreWithTags());

        $cachingProvider = new CachingTenantProvider($mockBaseProvider, $this->mockCache, 3600);

        // Inject the provider into the manager using reflection
        $reflection = new \ReflectionClass($this->providerManager);
        $property   = $reflection->getProperty('objects');
        $property->setAccessible(true);
        $property->setValue($this->providerManager, ['tenants' => $cachingProvider]);

        $this->mockCache->shouldReceive('tags')->times(2)->andReturnSelf();
        $this->mockCache->shouldReceive('forget')->twice();

        $observer = new TenantCacheObserver($this->invalidator);
        $observer->saved($this->mockTenant);
    }

    #[Test]
    public function deletedEventInvalidatesTenantCache(): void
    {
        $mockBaseProvider = Mockery::mock(TenantProvider::class);
        $mockBaseProvider->shouldReceive('getName')->andReturn('test');

        // getStore() is called in constructor for validation
        $this->mockCache->shouldReceive('getStore')->andReturn($this->createCacheStoreWithTags());

        $cachingProvider = new CachingTenantProvider($mockBaseProvider, $this->mockCache, 3600);

        // Inject the provider into the manager using reflection
        $reflection = new \ReflectionClass($this->providerManager);
        $property   = $reflection->getProperty('objects');
        $property->setAccessible(true);
        $property->setValue($this->providerManager, ['tenants' => $cachingProvider]);

        $this->mockCache->shouldReceive('tags')->times(2)->andReturnSelf();
        $this->mockCache->shouldReceive('forget')->twice();

        $observer = new TenantCacheObserver($this->invalidator);
        $observer->deleted($this->mockTenant);
    }

    #[Test]
    public function restoredEventInvalidatesTenantCache(): void
    {
        $mockBaseProvider = Mockery::mock(TenantProvider::class);
        $mockBaseProvider->shouldReceive('getName')->andReturn('test');

        // getStore() is called in constructor for validation
        $this->mockCache->shouldReceive('getStore')->andReturn($this->createCacheStoreWithTags());

        $cachingProvider = new CachingTenantProvider($mockBaseProvider, $this->mockCache, 3600);

        // Inject the provider into the manager using reflection
        $reflection = new \ReflectionClass($this->providerManager);
        $property   = $reflection->getProperty('objects');
        $property->setAccessible(true);
        $property->setValue($this->providerManager, ['tenants' => $cachingProvider]);

        $this->mockCache->shouldReceive('tags')->times(2)->andReturnSelf();
        $this->mockCache->shouldReceive('forget')->twice();

        $observer = new TenantCacheObserver($this->invalidator);
        $observer->restored($this->mockTenant);
    }
}
