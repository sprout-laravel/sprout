<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Console\Commands;

use Illuminate\Contracts\Cache\Repository;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Sprout\Console\Commands\ClearTenantCache;
use Sprout\Contracts\TenantProvider;
use Sprout\Managers\TenantProviderManager;
use Sprout\Support\CachingTenantProvider;
use Sprout\Support\TenantCacheInvalidator;
use Sprout\Tests\Unit\UnitTestCase;

class ClearTenantCacheTest extends UnitTestCase
{
    private TenantCacheInvalidator   $invalidator;
    private Repository&MockInterface $mockCache;
    private TenantProviderManager    $providerManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->providerManager = $this->app->make(TenantProviderManager::class);
        $this->mockCache       = Mockery::mock(Repository::class);
        $this->invalidator     = new TenantCacheInvalidator($this->providerManager, $this->mockCache);

        $this->app->instance(TenantCacheInvalidator::class, $this->invalidator);
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
    public function clearsAllCachesWhenNoProviderSpecified(): void
    {
        $this->mockCache->shouldReceive('getStore')
                        ->once()
                        ->andReturn($this->createCacheStoreWithTags());

        $this->mockCache->shouldReceive('tags')
                        ->once()
                        ->with(['sprout:tenants'])
                        ->andReturnSelf();

        $this->mockCache->shouldReceive('flush')
                        ->once();

        $this->artisan(ClearTenantCache::class)
             ->expectsOutputToContain('Cleared all tenant caches')
             ->assertExitCode(0);
    }

    #[Test]
    public function clearsSpecificProviderCacheWhenProviderSpecified(): void
    {
        $mockBaseProvider = Mockery::mock(TenantProvider::class);
        $mockBaseProvider->shouldReceive('getName')->andReturn('test');

        // getStore() is called in constructor for validation
        $this->mockCache->shouldReceive('getStore')->andReturn($this->createCacheStoreWithTags());

        $cachingProvider = new CachingTenantProvider($mockBaseProvider, $this->mockCache, 3600);

        // Inject the provider into the manager using reflection
        $reflection = new ReflectionClass($this->providerManager);
        $property   = $reflection->getProperty('objects');
        $property->setAccessible(true);
        $property->setValue($this->providerManager, ['tenants' => $cachingProvider]);

        $this->mockCache->shouldReceive('tags')->once()->andReturnSelf();
        $this->mockCache->shouldReceive('flush')->once();

        $this->artisan(ClearTenantCache::class, ['provider' => 'tenants'])
             ->expectsOutputToContain('Cleared tenant cache for provider: tenants')
             ->assertExitCode(0);
    }
}
