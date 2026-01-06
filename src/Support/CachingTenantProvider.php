<?php
declare(strict_types=1);

namespace Sprout\Support;

use Illuminate\Contracts\Cache\Repository;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantHasResources;
use Sprout\Contracts\TenantProvider;
use Sprout\Exceptions\MisconfigurationException;

/**
 * Caching Tenant Provider
 *
 * This is a decorator for {@see \Sprout\Contracts\TenantProvider} that adds
 * an optional caching layer to reduce database queries.
 *
 * @template TenantClass of \Sprout\Contracts\Tenant
 *
 * @implements \Sprout\Contracts\TenantProvider<TenantClass>
 *
 * @package Core
 */
final class CachingTenantProvider implements TenantProvider
{
    /**
     * The wrapped tenant provider
     *
     * @var \Sprout\Contracts\TenantProvider<TenantClass>
     */
    private TenantProvider $provider;

    /**
     * The cache repository
     *
     * @var \Illuminate\Contracts\Cache\Repository|null
     */
    private ?Repository $cache;

    /**
     * Cache TTL in seconds
     *
     * @var int|null
     */
    private ?int $ttl;

    /**
     * Create a new caching tenant provider
     *
     * @param \Sprout\Contracts\TenantProvider<TenantClass> $provider
     * @param \Illuminate\Contracts\Cache\Repository|null   $cache
     * @param int|null                                      $ttl
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     */
    public function __construct(TenantProvider $provider, ?Repository $cache = null, ?int $ttl = null)
    {
        $this->provider = $provider;
        $this->cache    = $cache;
        $this->ttl      = $ttl;

        // Validate that the cache repository supports tags if caching is enabled
        if ($this->cache !== null && ! method_exists($this->cache->getStore(), 'tags')) {
            throw MisconfigurationException::invalidConfig(
                'cache.store',
                'tenant provider',
                $this->provider->getName(),
                'The configured cache store does not support tags. Please use a cache driver that supports tagging (redis, memcached, dynamodb, or array).'
            );
        }
    }

    /**
     * Get the registered name of the provider
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->provider->getName();
    }

    /**
     * Retrieve a tenant by its identifier
     *
     * Gets an instance of the tenant implementation the provider represents,
     * using an identifier.
     *
     * @param string $identifier
     *
     * @return \Sprout\Contracts\Tenant|null
     *
     * @phpstan-return TenantClass|null
     */
    public function retrieveByIdentifier(string $identifier): ?Tenant
    {
        if ($this->cache === null) {
            return $this->provider->retrieveByIdentifier($identifier);
        }

        $key = $this->getCacheKey('identifier', $identifier);

        return $this->cache->tags($this->getCacheTags())
                           ->remember($key, $this->ttl, fn () => $this->provider->retrieveByIdentifier($identifier));
    }

    /**
     * Retrieve a tenant by its key
     *
     * Gets an instance of the tenant implementation the provider represents,
     * using a key.
     *
     * @param int|string $key
     *
     * @return \Sprout\Contracts\Tenant|null
     *
     * @phpstan-return TenantClass|null
     */
    public function retrieveByKey(int|string $key): ?Tenant
    {
        if ($this->cache === null) {
            return $this->provider->retrieveByKey($key);
        }

        $cacheKey = $this->getCacheKey('key', (string)$key);

        return $this->cache->tags($this->getCacheTags())
                           ->remember($cacheKey, $this->ttl, fn () => $this->provider->retrieveByKey($key));
    }

    /**
     * Retrieve a tenant by its resource key
     *
     * Gets an instance of the tenant implementation the provider represents,
     * using a resource key.
     * The tenant class must implement the {@see \Sprout\Contracts\TenantHasResources}
     * interface for this method to work.
     *
     * @param string $resourceKey
     *
     * @return (\Sprout\Contracts\Tenant&\Sprout\Contracts\TenantHasResources)|null
     *
     * @phpstan-return (TenantClass&\Sprout\Contracts\TenantHasResources)|null
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     */
    public function retrieveByResourceKey(string $resourceKey): (Tenant&TenantHasResources)|null
    {
        if ($this->cache === null) {
            return $this->provider->retrieveByResourceKey($resourceKey);
        }

        $key = $this->getCacheKey('resource', $resourceKey);

        return $this->cache->tags($this->getCacheTags())
                           ->remember($key, $this->ttl, fn () => $this->provider->retrieveByResourceKey($resourceKey));
    }

    /**
     * Flush all cached tenants for this provider
     *
     * @return void
     */
    public function flush(): void
    {
        if ($this->cache !== null) {
            $this->cache->tags($this->getCacheTags())->flush();
        }
    }

    /**
     * Invalidate cache for a specific tenant
     *
     * @param \Sprout\Contracts\Tenant $tenant
     *
     * @phpstan-param TenantClass      $tenant
     *
     * @return void
     */
    public function invalidate(Tenant $tenant): void
    {
        if ($this->cache === null) {
            return;
        }

        $keys = [
            $this->getCacheKey('identifier', $tenant->getTenantIdentifier()),
            $this->getCacheKey('key', (string)$tenant->getTenantKey()),
        ];

        if ($tenant instanceof TenantHasResources) {
            $keys[] = $this->getCacheKey('resource', $tenant->getTenantResourceKey());
        }

        foreach ($keys as $key) {
            $this->cache->tags($this->getCacheTags())->forget($key);
        }
    }

    /**
     * Get the cache key for a lookup type and value
     *
     * @param string $type
     * @param string $value
     *
     * @return string
     */
    private function getCacheKey(string $type, string $value): string
    {
        return sprintf('sprout:provider:%s:%s:%s', $this->provider->getName(), $type, $value);
    }

    /**
     * Get the cache tags for this provider
     *
     * @return array<string>
     */
    private function getCacheTags(): array
    {
        return [
            'sprout:tenants',
            sprintf('sprout:provider:%s', $this->provider->getName()),
        ];
    }

    /**
     * Get the wrapped provider
     *
     * @return \Sprout\Contracts\TenantProvider<TenantClass>
     */
    public function getWrappedProvider(): TenantProvider
    {
        return $this->provider;
    }

    /**
     * Check if caching is enabled
     *
     * @return bool
     */
    public function isCachingEnabled(): bool
    {
        return $this->cache !== null;
    }
}
