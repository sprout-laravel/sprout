<?php
declare(strict_types=1);

namespace Sprout\Managers;

use Illuminate\Database\Eloquent\Model;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantProvider;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Providers\DatabaseTenantProvider;
use Sprout\Providers\EloquentTenantProvider;
use Sprout\Support\BaseFactory;
use Sprout\Support\CachingTenantProvider;
use Sprout\Support\GenericTenant;

/**
 * Tenant Provider Manager
 *
 * This is a manager and factory, responsible for creating and storing
 * implementations of {@see \Sprout\Contracts\TenantProvider}.
 *
 * @extends \Sprout\Support\BaseFactory<\Sprout\Contracts\TenantProvider>
 *
 * @package Core
 */
final class TenantProviderManager extends BaseFactory
{
    /**
     * Get the name used by this factory
     *
     * @return string
     */
    public function getFactoryName(): string
    {
        return 'provider';
    }

    /**
     * Get the config key for the given name
     *
     * @param string $name
     *
     * @return string
     */
    public function getConfigKey(string $name): string
    {
        return 'multitenancy.providers.' . $name;
    }

    /**
     * Create the eloquent tenant provider
     *
     * @param array<string, mixed>                             $config
     * @param string                                           $name
     *
     * @return \Sprout\Providers\EloquentTenantProvider
     *
     * @template TenantModel of \Illuminate\Database\Eloquent\Model&\Sprout\Contracts\Tenant
     *
     * @phpstan-param array{model?: class-string<TenantModel>} $config
     *
     * @phpstan-return \Sprout\Providers\EloquentTenantProvider<TenantModel>
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     */
    protected function createEloquentProvider(array $config, string $name): EloquentTenantProvider
    {
        if (! isset($config['model'])) {
            throw MisconfigurationException::missingConfig('model', 'provider', $name);
        }

        if (
            ! class_exists($config['model'])
            || ! is_subclass_of($config['model'], Model::class)
            || ! is_subclass_of($config['model'], Tenant::class)
        ) {
            throw MisconfigurationException::invalidConfig('model', 'provider', $name, $config['model']);
        }

        return new EloquentTenantProvider($name, $config['model']);
    }

    /**
     * Create the database tenant provider
     *
     * @param array<string, mixed>                                                                                                                      $config
     * @param string                                                                                                                                    $name
     *
     * @return \Sprout\Providers\DatabaseTenantProvider
     *
     * @template TenantEntity of \Sprout\Contracts\Tenant
     *
     * @phpstan-param array{entity?: class-string<TenantEntity>, table?: string|class-string<\Illuminate\Database\Eloquent\Model>, connection?: string} $config
     *
     * @phpstan-return \Sprout\Providers\DatabaseTenantProvider<TenantEntity>
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     */
    protected function createDatabaseProvider(array $config, string $name): DatabaseTenantProvider
    {
        if (
            isset($config['entity'])
            && (
                ! class_exists($config['entity'])
                || ! is_subclass_of($config['entity'], Tenant::class)
            )
        ) {
            throw MisconfigurationException::invalidConfig('entity', 'provider', $name, $config['entity']);
        }

        if (! isset($config['table'])) {
            throw MisconfigurationException::missingConfig('table', 'provider', $name);
        }

        // This allows users to provide a model name for retrieval of table and
        // connection name, in case they need a backup without Eloquent
        if (class_exists($config['table'])) {
            // It's worth checking that the provided value is in fact a model,
            // otherwise things are going to get awkward
            if (! is_subclass_of($config['table'], Model::class)) {
                throw MisconfigurationException::invalidConfig('table', 'provider', $name, $config['table']);
            }

            $model      = new $config['table']();
            $table      = $model->getTable();
            $connection = $model->getConnectionName();
        } else {
            $table      = $config['table'];
            $connection = $config['connection'] ?? null;
        }

        /**
         * @var \Illuminate\Database\ConnectionInterface $connection
         * @phpstan-ignore-next-line
         */
        $connection = $this->app['db']->connection($connection);

        return new DatabaseTenantProvider(
            $name,
            $connection,
            $table,
            $config['entity'] ?? GenericTenant::class
        );
    }

    /**
     * Resolve a tenant provider by name
     *
     * This method wraps the base resolve method to add optional caching support.
     *
     * @param string $name
     *
     * @return \Sprout\Contracts\TenantProvider<*>
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function resolve(string $name): object
    {
        /** @var \Sprout\Contracts\TenantProvider<*> $provider */
        $provider = parent::resolve($name);

        // Check if caching is configured for this provider
        $config = $this->getConfig($name);

        if (isset($config['cache']) && is_array($config['cache'])) {
            /** @phpstan-ignore argument.type, argument.templateType */
            $provider = $this->wrapWithCaching($provider, $config['cache']);
        }

        return $provider;
    }

    /**
     * Wrap a tenant provider with caching
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\TenantProvider<TenantClass>      $provider
     * @param array<string, mixed>                               $cacheConfig
     *
     * @phpstan-param array{ttl?: int|null, store?: string|null} $cacheConfig
     *
     * @return \Sprout\Support\CachingTenantProvider<TenantClass>
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Exceptions\MisconfigurationException
     */
    private function wrapWithCaching(TenantProvider $provider, array $cacheConfig): CachingTenantProvider
    {
        $ttl       = $cacheConfig['ttl'] ?? null;
        $storeName = $cacheConfig['store'] ?? null;

        /** @var \Illuminate\Cache\CacheManager $cacheManager */
        $cacheManager = $this->app->make('cache');

        /** @var \Illuminate\Contracts\Cache\Repository $cache */
        $cache = $cacheManager->store($storeName);

        return new CachingTenantProvider($provider, $cache, $ttl);
    }

    /**
     * Get all resolved tenant providers
     *
     * This returns all providers that have been instantiated by this manager.
     *
     * @return array<string, \Sprout\Contracts\TenantProvider<*>>
     */
    public function getResolvedProviders(): array
    {
        return $this->objects;
    }
}
