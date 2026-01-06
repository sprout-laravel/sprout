<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Support;

use Illuminate\Contracts\Cache\Repository;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantHasResources;
use Sprout\Contracts\TenantProvider;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Support\CachingTenantProvider;
use Sprout\Tests\Unit\UnitTestCase;
use stdClass;

class CachingTenantProviderTest extends UnitTestCase
{
    private TenantProvider&MockInterface $mockProvider;
    private Repository&MockInterface     $mockCache;
    private Tenant&MockInterface         $mockTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockProvider = Mockery::mock(TenantProvider::class);
        $this->mockCache    = Mockery::mock(Repository::class);
        $this->mockTenant   = Mockery::mock(Tenant::class);

        $this->mockProvider->shouldReceive('getName')->andReturn('test-provider');
    }

    /**
     * Create a mock cache store that supports tags
     */
    private function createCacheStoreWithTags(): object
    {
        return new class {
            public function tags(): void
            {
                // Mock tags method
            }
        };
    }

    #[Test]
    public function throwsExceptionWhenCacheStoreDoesNotSupportTags(): void
    {
        $cacheStore = Mockery::mock(stdClass::class);
        $this->mockCache->shouldReceive('getStore')->andReturn($cacheStore);

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('does not support tags');

        new CachingTenantProvider($this->mockProvider, $this->mockCache, 3600);
    }

    #[Test]
    public function allowsCacheStoreWithTagsMethod(): void
    {
        $this->mockCache->shouldReceive('getStore')
                        ->andReturn($this->createCacheStoreWithTags());

        $provider = new CachingTenantProvider($this->mockProvider, $this->mockCache, 3600);

        $this->assertInstanceOf(CachingTenantProvider::class, $provider);
    }

    #[Test]
    public function allowsNullCache(): void
    {
        $provider = new CachingTenantProvider($this->mockProvider, null, 3600);

        $this->assertInstanceOf(CachingTenantProvider::class, $provider);
    }

    #[Test]
    public function returnsProviderNameFromWrappedProvider(): void
    {
        $provider = new CachingTenantProvider($this->mockProvider, null);

        $this->assertSame('test-provider', $provider->getName());
    }

    #[Test]
    public function retrieveByIdentifierWithoutCacheCallsProviderDirectly(): void
    {
        $provider = new CachingTenantProvider($this->mockProvider, null);

        $this->mockProvider->shouldReceive('retrieveByIdentifier')
                           ->once()
                           ->with('tenant-123')
                           ->andReturn($this->mockTenant);

        $result = $provider->retrieveByIdentifier('tenant-123');

        $this->assertSame($this->mockTenant, $result);
    }

    #[Test]
    public function retrieveByIdentifierWithCacheUsesCache(): void
    {
        $this->mockCache->shouldReceive('getStore')
                        ->andReturn($this->createCacheStoreWithTags());

        $provider = new CachingTenantProvider($this->mockProvider, $this->mockCache, 3600);

        $tags = ['sprout:tenants', 'sprout:provider:test-provider'];
        $key  = 'sprout:provider:test-provider:identifier:tenant-123';

        $this->mockCache->shouldReceive('tags')
                        ->once()
                        ->with($tags)
                        ->andReturnSelf();

        $this->mockCache->shouldReceive('remember')
                        ->once()
                        ->with($key, 3600, Mockery::type('Closure'))
                        ->andReturn($this->mockTenant);

        $result = $provider->retrieveByIdentifier('tenant-123');

        $this->assertSame($this->mockTenant, $result);
    }

    #[Test]
    public function retrieveByKeyWithoutCacheCallsProviderDirectly(): void
    {
        $provider = new CachingTenantProvider($this->mockProvider, null);

        $this->mockProvider->shouldReceive('retrieveByKey')
                           ->once()
                           ->with(456)
                           ->andReturn($this->mockTenant);

        $result = $provider->retrieveByKey(456);

        $this->assertSame($this->mockTenant, $result);
    }

    #[Test]
    public function retrieveByKeyWithCacheUsesCache(): void
    {
        $this->mockCache->shouldReceive('getStore')
                        ->andReturn($this->createCacheStoreWithTags());

        $provider = new CachingTenantProvider($this->mockProvider, $this->mockCache, 1800);

        $tags = ['sprout:tenants', 'sprout:provider:test-provider'];
        $key  = 'sprout:provider:test-provider:key:789';

        $this->mockCache->shouldReceive('tags')
                        ->once()
                        ->with($tags)
                        ->andReturnSelf();

        $this->mockCache->shouldReceive('remember')
                        ->once()
                        ->with($key, 1800, Mockery::type('Closure'))
                        ->andReturn($this->mockTenant);

        $result = $provider->retrieveByKey(789);

        $this->assertSame($this->mockTenant, $result);
    }

    #[Test]
    public function retrieveByResourceKeyWithoutCacheCallsProviderDirectly(): void
    {
        $provider = new CachingTenantProvider($this->mockProvider, null);

        $mockTenantWithResources = Mockery::mock(Tenant::class, TenantHasResources::class);

        $this->mockProvider->shouldReceive('retrieveByResourceKey')
                           ->once()
                           ->with('resource-key')
                           ->andReturn($mockTenantWithResources);

        $result = $provider->retrieveByResourceKey('resource-key');

        $this->assertSame($mockTenantWithResources, $result);
    }

    #[Test]
    public function retrieveByResourceKeyWithCacheUsesCache(): void
    {
        $this->mockCache->shouldReceive('getStore')
                        ->andReturn($this->createCacheStoreWithTags());

        $provider = new CachingTenantProvider($this->mockProvider, $this->mockCache, 3600);

        $mockTenantWithResources = Mockery::mock(Tenant::class, TenantHasResources::class);

        $tags = ['sprout:tenants', 'sprout:provider:test-provider'];
        $key  = 'sprout:provider:test-provider:resource:resource-key';

        $this->mockCache->shouldReceive('tags')
                        ->once()
                        ->with($tags)
                        ->andReturnSelf();

        $this->mockCache->shouldReceive('remember')
                        ->once()
                        ->with($key, 3600, Mockery::type('Closure'))
                        ->andReturn($mockTenantWithResources);

        $result = $provider->retrieveByResourceKey('resource-key');

        $this->assertSame($mockTenantWithResources, $result);
    }

    #[Test]
    public function flushWithoutCacheDoesNothing(): void
    {
        $provider = new CachingTenantProvider($this->mockProvider, null);

        // Should not throw an exception
        $provider->flush();

        $this->assertTrue(true);
    }

    #[Test]
    public function flushWithCacheClearsAllCachedTenants(): void
    {
        $this->mockCache->shouldReceive('getStore')
                        ->andReturn($this->createCacheStoreWithTags());

        $provider = new CachingTenantProvider($this->mockProvider, $this->mockCache, 3600);

        $tags = ['sprout:tenants', 'sprout:provider:test-provider'];

        $this->mockCache->shouldReceive('tags')
                        ->once()
                        ->with($tags)
                        ->andReturnSelf();

        $this->mockCache->shouldReceive('flush')
                        ->once();

        $provider->flush();
    }

    #[Test]
    public function invalidateWithoutCacheDoesNothing(): void
    {
        $provider = new CachingTenantProvider($this->mockProvider, null);

        $this->mockTenant->shouldReceive('getTenantIdentifier')->andReturn('tenant-123');
        $this->mockTenant->shouldReceive('getTenantKey')->andReturn(456);

        // Should not throw an exception
        $provider->invalidate($this->mockTenant);

        $this->assertTrue(true);
    }

    #[Test]
    public function invalidateWithCacheForgetsTenantKeys(): void
    {
        $this->mockCache->shouldReceive('getStore')
                        ->andReturn($this->createCacheStoreWithTags());

        $provider = new CachingTenantProvider($this->mockProvider, $this->mockCache, 3600);

        $this->mockTenant->shouldReceive('getTenantIdentifier')->andReturn('tenant-123');
        $this->mockTenant->shouldReceive('getTenantKey')->andReturn(456);

        $tags = ['sprout:tenants', 'sprout:provider:test-provider'];

        $this->mockCache->shouldReceive('tags')
                        ->times(2)
                        ->with($tags)
                        ->andReturnSelf();

        $this->mockCache->shouldReceive('forget')
                        ->once()
                        ->with('sprout:provider:test-provider:identifier:tenant-123');

        $this->mockCache->shouldReceive('forget')
                        ->once()
                        ->with('sprout:provider:test-provider:key:456');

        $provider->invalidate($this->mockTenant);
    }

    #[Test]
    public function invalidateWithCacheAlsoForgetsResourceKeyForTenantWithResources(): void
    {
        $this->mockCache->shouldReceive('getStore')
                        ->andReturn($this->createCacheStoreWithTags());

        $provider = new CachingTenantProvider($this->mockProvider, $this->mockCache, 3600);

        $mockTenantWithResources = Mockery::mock(Tenant::class, TenantHasResources::class);
        $mockTenantWithResources->shouldReceive('getTenantIdentifier')->andReturn('tenant-123');
        $mockTenantWithResources->shouldReceive('getTenantKey')->andReturn(456);
        $mockTenantWithResources->shouldReceive('getTenantResourceKey')->andReturn('resource-key');

        $tags = ['sprout:tenants', 'sprout:provider:test-provider'];

        $this->mockCache->shouldReceive('tags')
                        ->times(3)
                        ->with($tags)
                        ->andReturnSelf();

        $this->mockCache->shouldReceive('forget')
                        ->once()
                        ->with('sprout:provider:test-provider:identifier:tenant-123');

        $this->mockCache->shouldReceive('forget')
                        ->once()
                        ->with('sprout:provider:test-provider:key:456');

        $this->mockCache->shouldReceive('forget')
                        ->once()
                        ->with('sprout:provider:test-provider:resource:resource-key');

        $provider->invalidate($mockTenantWithResources);
    }

    #[Test]
    public function getWrappedProviderReturnsOriginalProvider(): void
    {
        $provider = new CachingTenantProvider($this->mockProvider, null);

        $this->assertSame($this->mockProvider, $provider->getWrappedProvider());
    }

    #[Test]
    public function isCachingEnabledReturnsFalseWhenCacheIsNull(): void
    {
        $provider = new CachingTenantProvider($this->mockProvider, null);

        $this->assertFalse($provider->isCachingEnabled());
    }

    #[Test]
    public function isCachingEnabledReturnsTrueWhenCacheIsProvided(): void
    {
        $this->mockCache->shouldReceive('getStore')
                        ->andReturn($this->createCacheStoreWithTags());

        $provider = new CachingTenantProvider($this->mockProvider, $this->mockCache, 3600);

        $this->assertTrue($provider->isCachingEnabled());
    }

    #[Test]
    public function cacheTtlCanBeNull(): void
    {
        $this->mockCache->shouldReceive('getStore')
                        ->andReturn($this->createCacheStoreWithTags());

        $provider = new CachingTenantProvider($this->mockProvider, $this->mockCache, null);

        $tags = ['sprout:tenants', 'sprout:provider:test-provider'];

        $this->mockCache->shouldReceive('tags')
                        ->once()
                        ->with($tags)
                        ->andReturnSelf();

        $this->mockCache->shouldReceive('remember')
                        ->once()
                        ->with('sprout:provider:test-provider:identifier:tenant-123', null, Mockery::type('Closure'))
                        ->andReturn($this->mockTenant);

        $provider->retrieveByIdentifier('tenant-123');
    }

    #[Test]
    public function retrieveByIdentifierWithCacheCachesNullResults(): void
    {
        $this->mockCache->shouldReceive('getStore')
                        ->andReturn($this->createCacheStoreWithTags());

        $provider = new CachingTenantProvider($this->mockProvider, $this->mockCache, 3600);

        $tags = ['sprout:tenants', 'sprout:provider:test-provider'];
        $key  = 'sprout:provider:test-provider:identifier:nonexistent';

        $this->mockCache->shouldReceive('tags')
                        ->once()
                        ->with($tags)
                        ->andReturnSelf();

        $this->mockCache->shouldReceive('remember')
                        ->once()
                        ->with($key, 3600, Mockery::type('Closure'))
                        ->andReturn(null);

        $result = $provider->retrieveByIdentifier('nonexistent');

        $this->assertNull($result);
    }

    #[Test]
    public function retrieveByKeyWithCacheCachesNullResults(): void
    {
        $this->mockCache->shouldReceive('getStore')
                        ->andReturn($this->createCacheStoreWithTags());

        $provider = new CachingTenantProvider($this->mockProvider, $this->mockCache, 3600);

        $tags = ['sprout:tenants', 'sprout:provider:test-provider'];
        $key  = 'sprout:provider:test-provider:key:999';

        $this->mockCache->shouldReceive('tags')
                        ->once()
                        ->with($tags)
                        ->andReturnSelf();

        $this->mockCache->shouldReceive('remember')
                        ->once()
                        ->with($key, 3600, Mockery::type('Closure'))
                        ->andReturn(null);

        $result = $provider->retrieveByKey(999);

        $this->assertNull($result);
    }

    #[Test]
    public function retrieveByResourceKeyWithCacheCachesNullResults(): void
    {
        $this->mockCache->shouldReceive('getStore')
                        ->andReturn($this->createCacheStoreWithTags());

        $provider = new CachingTenantProvider($this->mockProvider, $this->mockCache, 3600);

        $tags = ['sprout:tenants', 'sprout:provider:test-provider'];
        $key  = 'sprout:provider:test-provider:resource:nonexistent';

        $this->mockCache->shouldReceive('tags')
                        ->once()
                        ->with($tags)
                        ->andReturnSelf();

        $this->mockCache->shouldReceive('remember')
                        ->once()
                        ->with($key, 3600, Mockery::type('Closure'))
                        ->andReturn(null);

        $result = $provider->retrieveByResourceKey('nonexistent');

        $this->assertNull($result);
    }

    #[Test]
    public function cachingDoesNotInterfereWithProviderRetrieval(): void
    {
        $this->mockCache->shouldReceive('getStore')
                        ->andReturn($this->createCacheStoreWithTags());

        $provider = new CachingTenantProvider($this->mockProvider, $this->mockCache, 3600);

        $tags = ['sprout:tenants', 'sprout:provider:test-provider'];
        $key  = 'sprout:provider:test-provider:identifier:tenant-123';

        // Set up cache to execute the closure
        $this->mockCache->shouldReceive('tags')
                        ->once()
                        ->with($tags)
                        ->andReturnSelf();

        $this->mockCache->shouldReceive('remember')
                        ->once()
                        ->with($key, 3600, Mockery::type('Closure'))
                        ->andReturnUsing(function ($key, $ttl, $closure) {
                            return $closure();
                        });

        $this->mockProvider->shouldReceive('retrieveByIdentifier')
                           ->once()
                           ->with('tenant-123')
                           ->andReturn($this->mockTenant);

        $result = $provider->retrieveByIdentifier('tenant-123');

        $this->assertSame($this->mockTenant, $result);
    }
}
