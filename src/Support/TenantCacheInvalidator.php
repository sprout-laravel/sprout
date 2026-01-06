<?php
declare(strict_types=1);

namespace Sprout\Support;

use Illuminate\Contracts\Cache\Repository;
use ReflectionClass;
use Sprout\Contracts\Tenant;
use Sprout\Managers\TenantProviderManager;

/**
 * Tenant Cache Invalidator
 *
 * This helper class provides methods for invalidating tenant caches across
 * providers.
 *
 * @package Core
 */
final class TenantCacheInvalidator
{
    /**
     * The tenant provider manager
     *
     * @var \Sprout\Managers\TenantProviderManager
     */
    private TenantProviderManager $providerManager;

    /**
     * The cache repository
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    private Repository $cache;

    /**
     * Create a new tenant cache invalidator
     *
     * @param \Sprout\Managers\TenantProviderManager $providerManager
     * @param \Illuminate\Contracts\Cache\Repository $cache
     */
    public function __construct(TenantProviderManager $providerManager, Repository $cache)
    {
        $this->providerManager = $providerManager;
        $this->cache           = $cache;
    }

    /**
     * Invalidate cache for a specific tenant in a specific provider
     *
     * @param string                   $providerName
     * @param \Sprout\Contracts\Tenant $tenant
     *
     * @return void
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     */
    public function invalidate(string $providerName, Tenant $tenant): void
    {
        $provider = $this->providerManager->get($providerName);

        if ($provider instanceof CachingTenantProvider) {
            $provider->invalidate($tenant);
        }
    }

    /**
     * Invalidate cache for a specific tenant across all providers
     *
     * @param \Sprout\Contracts\Tenant $tenant
     *
     * @return void
     */
    public function invalidateAcrossProviders(Tenant $tenant): void
    {
        // Get all resolved providers
        foreach ($this->getResolvedProviders() as $provider) {
            if ($provider instanceof CachingTenantProvider) {
                $provider->invalidate($tenant);
            }
        }
    }

    /**
     * Flush all cached tenants for a specific provider
     *
     * @param string $providerName
     *
     * @return void
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     */
    public function flushProvider(string $providerName): void
    {
        $provider = $this->providerManager->get($providerName);

        if ($provider instanceof CachingTenantProvider) {
            $provider->flush();
        }
    }

    /**
     * Flush all cached tenants across all providers
     *
     * @return void
     */
    public function flushAll(): void
    {
        // Use cache tags to flush all tenant caches at once
        if (method_exists($this->cache->getStore(), 'tags')) {
            $this->cache->tags(['sprout:tenants'])->flush();
        }
    }

    /**
     * Get all resolved tenant providers
     *
     * @return array<\Sprout\Contracts\TenantProvider<*>>
     */
    private function getResolvedProviders(): array
    {
        $providers = [];

        // Use reflection to access the protected $objects property
        $reflection = new ReflectionClass($this->providerManager);
        $property   = $reflection->getProperty('objects');
        $property->setAccessible(true);

        /** @var array<string, \Sprout\Contracts\TenantProvider<*>> $objects */
        $objects = $property->getValue($this->providerManager);

        return $objects;
    }
}
