<?php
declare(strict_types=1);

namespace Sprout\Managers;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Sprout\Contracts\Tenant;
use Sprout\Providers\DatabaseTenantProvider;
use Sprout\Providers\EloquentTenantProvider;
use Sprout\Support\BaseFactory;
use Sprout\Support\GenericTenant;

/**
 * @extends \Sprout\Support\BaseFactory<\Sprout\Contracts\TenantProvider>
 *
 * @package Providers
 */
final class ProviderManager extends BaseFactory
{
    /**
     * Get the name used by this factory
     *
     * @return string
     */
    protected function getFactoryName(): string
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
    protected function getConfigKey(string $name): string
    {
        return 'tenanted.providers.' . $name;
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
     * @psalm-param array{model?: class-string<TenantModel>}   $config
     * @phpstan-param array{model?: class-string<TenantModel>} $config
     *
     * @psalm-return \Sprout\Providers\EloquentTenantProvider<TenantModel>
     * @phpstan-return \Sprout\Providers\EloquentTenantProvider<TenantModel>
     */
    protected function createEloquentProvider(array $config, string $name): EloquentTenantProvider
    {
        if (! isset($config['model'])) {
            throw new InvalidArgumentException(
                'No model provided for provider [' . $name . ']'
            );
        }

        if (
            ! class_exists($config['model'])
            || ! is_subclass_of($config['model'], Model::class)
            || ! is_subclass_of($config['model'], Tenant::class)
        ) {
            throw new InvalidArgumentException(
                'Invalid model provided for provider [' . $name . ']'
            );
        }

        return new EloquentTenantProvider($name,  $config['model']);
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
     * @psalm-param array{entity?: class-string<TenantEntity>, table?: string|class-string<\Illuminate\Database\Eloquent\Model>, connection?: string}   $config
     * @phpstan-param array{entity?: class-string<TenantEntity>, table?: string|class-string<\Illuminate\Database\Eloquent\Model>, connection?: string} $config
     *
     * @psalm-return \Sprout\Providers\DatabaseTenantProvider<TenantEntity>
     * @phpstan-return \Sprout\Providers\DatabaseTenantProvider<TenantEntity>
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
            throw new InvalidArgumentException(
                'Invalid entity provided for provider [' . $name . ']'
            );
        }

        if (! isset($config['table'])) {
            throw new InvalidArgumentException(
                'No table provided for provider [' . $name . ']'
            );
        }

        // This allows users to provide a model name for retrieval of table and
        // connection name, in case they need a backup without Eloquent
        if (class_exists($config['table'])) {
            // It's worth checking that the provided value is in fact a model,
            // otherwise things are going to get awkward
            if (! is_subclass_of($config['table'], Model::class)) {
                throw new InvalidArgumentException(
                    'Invalid table provided for provider [' . $name . ']'
                );
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
}
