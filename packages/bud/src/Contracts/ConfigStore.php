<?php

namespace Sprout\Bud\Contracts;

use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Contracts\Tenant;

interface ConfigStore
{
    /**
     * Get the registered name of the config store
     *
     * @return string
     */
    public function getName(): string;

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
     * @phpstan-param TenantClass                         $tenant
     *
     * @return array<string, mixed>|null
     */
    public function get(
        Tenancy $tenancy,
        Tenant  $tenant,
        string  $service,
        string  $name,
        ?array  $default = null
    ): ?array;

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
     * @phpstan-param TenantClass                         $tenant
     *
     * @return bool
     */
    public function has(
        Tenancy $tenancy,
        Tenant  $tenant,
        string  $service,
        string  $name,
    ): bool;

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
     * @phpstan-param TenantClass                         $tenant
     *
     * @return bool
     */
    public function set(
        Tenancy $tenancy,
        Tenant  $tenant,
        string  $service,
        string  $name,
        array   $config
    ): bool;

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
     * @phpstan-param TenantClass                         $tenant
     *
     * @return bool
     */
    public function add(
        Tenancy $tenancy,
        Tenant  $tenant,
        string  $service,
        string  $name,
        array   $config
    ): bool;
}
