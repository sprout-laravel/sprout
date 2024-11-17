<?php
declare(strict_types=1);

namespace Sprout\Overrides;

use Illuminate\Cache\ApcWrapper;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\FileStore;
use Illuminate\Cache\MemcachedStore;
use Illuminate\Cache\NullStore;
use Illuminate\Cache\RedisStore;
use Illuminate\Contracts\Foundation\Application;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\DeferrableServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Exceptions\TenantMissing;
use Sprout\Sprout;

/**
 * Cache Override
 *
 * This class provides the override/multitenancy extension/features for Laravels
 * cache service.
 *
 * @package Overrides
 */
final class CacheOverride implements BootableServiceOverride, DeferrableServiceOverride
{
    /**
     * Cache stores that can be purged
     *
     * @var list<string>
     */
    private static array $purgableStores = [];

    /**
     * Get the service to watch for before overriding
     *
     * @return string
     */
    public static function service(): string
    {
        return CacheManager::class;
    }

    /**
     * Boot a service override
     *
     * This method should perform any initial steps required for the service
     * override that take place during the booting of the framework.
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     * @param \Sprout\Sprout                               $sprout
     *
     * @return void
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function boot(Application $app, Sprout $sprout): void
    {
        $cacheManager = app(CacheManager::class);

        $cacheManager->extend('sprout',
            /**
             * @param array<string, mixed> $config
             *
             * @throws \Sprout\Exceptions\TenantMissing
             */
            function (Application $app, array $config) use ($sprout, $cacheManager) {
                $tenancy = $sprout->tenancies()->get($config['tenancy'] ?? null);

                // If there's no tenant, error out
                if (! $tenancy->check()) {
                    throw TenantMissing::make($tenancy->getName());
                }

                $tenant = $tenancy->tenant();

                if (! isset($config['override'])) {
                    throw MisconfigurationException::missingConfig('override', self::class, 'override');
                }

                /** @var array<string, mixed> $storeConfig */
                $storeConfig = config('caches.store.' . $config['override']);
                $prefix      = (
                               isset($storeConfig['prefix'])
                                   ? $storeConfig['prefix'] . '_'
                                   : ''
                               )
                               . $tenancy->getName()
                               . '_'
                               . $tenant->getTenantKey();

                /** @var array{driver:string,serialize?:bool,path:string,permission?:int|null,lock_path?:string|null} $storeConfig */

                /** @var string $storeName */
                $storeName = config('store');

                if (! in_array($storeName, self::$purgableStores, true)) {
                    self::$purgableStores[] = $storeName;
                }

                return $cacheManager->repository(match ($storeConfig['driver']) {
                    'apc'       => new ApcStore(new ApcWrapper(), $prefix),
                    'array'     => new ArrayStore($storeConfig['serialize'] ?? false),
                    'file'      => (new FileStore(app('files'), $storeConfig['path'], $storeConfig['permission'] ?? null))
                        ->setLockDirectory($storeConfig['lock_path'] ?? null),
                    'null'      => new NullStore(),
                    'memcached' => $this->createTenantedMemcachedStore($prefix, $storeConfig),
                    'redis'     => $this->createTenantedRedisStore($prefix, $storeConfig),
                    'database'  => $this->createTenantedDatabaseStore($prefix, $storeConfig),
                    default     => throw MisconfigurationException::invalidConfig('driver', 'override', CacheOverride::class)
                }, array_merge($config, $storeConfig));

            }
        );
    }

    /**
     * Create a memcache cache store that's tenanted
     *
     * @param string               $prefix
     * @param array<string, mixed> $config
     *
     * @return \Illuminate\Cache\MemcachedStore
     */
    private function createTenantedMemcachedStore(string $prefix, array $config): MemcachedStore
    {
        /** @var array{servers:array<string, mixed>,persistent_id?:string|null, options?:array<string,mixed>|null,sasl?:array<mixed>|null} $config */

        $memcached = app('memcached.connector')->connect(
            $config['servers'],
            $config['persistent_id'] ?? null,
            $config['options'] ?? [],
            array_filter($config['sasl'] ?? [])
        );

        return new MemcachedStore($memcached, $prefix);
    }

    /**
     * Create a Redis cache store that's tenanted
     *
     * @param string               $prefix
     * @param array<string, mixed> $config
     *
     * @return \Illuminate\Cache\RedisStore
     */
    private function createTenantedRedisStore(string $prefix, array $config): RedisStore
    {
        /** @var array{connection?:string|null, lock_connection?:string|null} $config */
        $redis = app('redis');

        $connection = $config['connection'] ?? 'default';

        return (new RedisStore($redis, $prefix, $connection))->setLockConnection($config['lock_connection'] ?? $connection);
    }

    /**
     * Create a database cache store that's tenanted
     *
     * @param string               $prefix
     * @param array<string, mixed> $config
     *
     * @return \Illuminate\Cache\DatabaseStore
     */
    private function createTenantedDatabaseStore(string $prefix, array $config): DatabaseStore
    {
        /** @var array{table:string,lock_table?:string|null,lock_lottery?:array<int>|null,lock_timeout?:int|null,connection?:string|null, lock_connection?:string|null} $config */
        $connection = app('db')->connection($config['connection'] ?? null);

        $store = new DatabaseStore(
            $connection,
            $config['table'],
            $prefix,
            $config['lock_table'] ?? 'cache_locks',
            $config['lock_lottery'] ?? [2, 100],
            $config['lock_timeout'] ?? 86400,
        );

        if (isset($config['lock_connection'])) {
            $store->setLockConnection(app('db')->connection($config['lock_connection']));
        } else {
            $store->setLockConnection($connection);
        }

        return $store;
    }

    /**
     * Set up the service override
     *
     * This method should perform any necessary setup actions for the service
     * override.
     * It is called when a new tenant is marked as the current tenant.
     *
     * @param \Sprout\Contracts\Tenancy<*> $tenancy
     * @param \Sprout\Contracts\Tenant $tenant
     *
     * @return void
     */
    public function setup(Tenancy $tenancy, Tenant $tenant): void
    {
        // This is intentionally empty, nothing to do here
    }

    /**
     * Clean up the service override
     *
     * This method should perform any necessary setup actions for the service
     * override.
     * It is called when the current tenant is unset, either to be replaced
     * by another tenant, or none.
     *
     * It will be called before {@see self::setup()}, but only if the previous
     * tenant was not null.
     *
     * @param \Sprout\Contracts\Tenancy<*> $tenancy
     * @param \Sprout\Contracts\Tenant $tenant
     *
     * @return void
     */
    public function cleanup(Tenancy $tenancy, Tenant $tenant): void
    {
        app(CacheManager::class)->forgetDriver(self::$purgableStores);
    }
}
