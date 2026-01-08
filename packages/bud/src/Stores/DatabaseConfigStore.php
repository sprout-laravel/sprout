<?php
declare(strict_types=1);

namespace Sprout\Bud\Stores;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Contracts\Tenant;

final class DatabaseConfigStore extends BaseConfigStore
{
    /**
     * @var \Illuminate\Database\ConnectionInterface
     */
    private ConnectionInterface $connection;

    private string $table;

    public function __construct(
        string              $name,
        Encrypter           $encrypter,
        ConnectionInterface $connection,
        string              $table
    )
    {
        parent::__construct($name, $encrypter);

        $this->connection = $connection;
        $this->table      = $table;
    }

    /**
     * Get the table name the store uses
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get a query builder for the config store
     *
     * @template TenantClass of \Sprout\Core\Contracts\Tenant
     *
     * @param \Sprout\Core\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Core\Contracts\Tenant               $tenant
     * @param string                                      $service
     * @param string                                      $name
     *
     * @phpstan-param TenantClass                         $tenant
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function getQuery(Tenancy $tenancy, Tenant $tenant, string $service, string $name): Builder
    {
        return $this->connection->table($this->table)
                                ->where('tenancy', '=', $tenancy->getName())
                                ->where('tenant_id', '=', $tenant->getTenantKey())
                                ->where('service', '=', $service)
                                ->where('name', '=', $name);
    }

    /**
     * Get a config value from the store
     *
     * @template TenantClass of \Sprout\Core\Contracts\Tenant
     *
     * @param \Sprout\Core\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Core\Contracts\Tenant               $tenant
     * @param string                                      $service
     * @param string                                      $name
     * @param array<string, mixed>|null                   $default
     *
     * @phpstan-param Tenant                              $tenant
     *
     * @return array<string, mixed>|null
     */
    public function get(Tenancy $tenancy, Tenant $tenant, string $service, string $name, ?array $default = null): ?array
    {
        $value = $this->getQuery($tenancy, $tenant, $service, $name)
                      ->value('config');

        /** @var string|null $value */

        if ($value === null) {
            return $default;
        }

        return $this->decryptConfig($value);
    }

    /**
     * Check if the config store has a value
     *
     * @template TenantClass of \Sprout\Core\Contracts\Tenant
     *
     * @param \Sprout\Core\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Core\Contracts\Tenant               $tenant
     * @param string                                      $service
     * @param string                                      $name
     *
     * @phpstan-param Tenant                              $tenant
     *
     * @return bool
     */
    public function has(Tenancy $tenancy, Tenant $tenant, string $service, string $name): bool
    {
        return $this->getQuery($tenancy, $tenant, $service, $name)
                    ->whereNotNull('config')
                    ->exists();
    }

    /**
     * Set a config value in the store
     *
     * Setting a config value ensures that the config is present within the
     * store for the given tenant, either by adding the entry if there wasn't
     * one, or overwriting one if it already existed.
     *
     * @template TenantClass of \Sprout\Core\Contracts\Tenant
     *
     * @param \Sprout\Core\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Core\Contracts\Tenant               $tenant
     * @param string                                      $service
     * @param string                                      $name
     * @param array<string, mixed>                        $config
     *
     * @phpstan-param Tenant                              $tenant
     *
     * @return bool
     *
     * @throws \JsonException
     */
    public function set(Tenancy $tenancy, Tenant $tenant, string $service, string $name, array $config): bool
    {
        return $this->connection->table($this->table)
                                ->upsert([
                                    'tenancy'   => $tenancy->getName(),
                                    'tenant_id' => $tenant->getTenantKey(),
                                    'service'   => $service,
                                    'name'      => $name,
                                    'config'    => $this->encryptConfig($config),
                                ], [
                                    'tenancy'   => $tenancy->getName(),
                                    'tenant_id' => $tenant->getTenantKey(),
                                    'service'   => $service,
                                    'name'      => $name,
                                ]) !== 0;
    }

    /**
     * Add a config value to the store
     *
     * Adding a config value will create a new entry within the store for the
     * given tenant if one doesn't already exist. If an entry already exists,
     * this method will return false.
     *
     * @template TenantClass of \Sprout\Core\Contracts\Tenant
     *
     * @param \Sprout\Core\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Core\Contracts\Tenant               $tenant
     * @param string                                      $service
     * @param string                                      $name
     * @param array<string, mixed>                        $config
     *
     * @phpstan-param Tenant                              $tenant
     *
     * @return bool
     *
     * @throws \JsonException
     */
    public function add(Tenancy $tenancy, Tenant $tenant, string $service, string $name, array $config): bool
    {
        if ($this->has($tenancy, $tenant, $service, $name)) {
            return false;
        }

        /**
         * This is here because Laravel doesn't honour its own return type
         * @phpstan-ignore notIdentical.alwaysTrue
         */
        return $this->connection->table($this->table)
                                ->insert([
                                    'tenancy'   => $tenancy->getName(),
                                    'tenant_id' => $tenant->getTenantKey(),
                                    'service'   => $service,
                                    'name'      => $name,
                                    'config'    => $this->encryptConfig($config),
                                ]) !== 0;
    }
}
