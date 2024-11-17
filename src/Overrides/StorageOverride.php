<?php
declare(strict_types=1);

namespace Sprout\Overrides;

use Closure;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemManager;
use RuntimeException;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\DeferrableServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantHasResources;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Exceptions\TenantMissing;
use Sprout\Sprout;

/**
 * Storage Override
 *
 * This class provides the override/multitenancy extension/features for Laravels
 * storage service.
 *
 * @package Overrides
 */
final class StorageOverride implements BootableServiceOverride, DeferrableServiceOverride
{
    /**
     * Get the service to watch for before overriding
     *
     * @return string
     */
    public static function service(): string
    {
        return 'filesystem';
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
        $filesystemManager = $app->make(FilesystemManager::class);
        $filesystemManager->extend('sprout', self::creator($sprout, $filesystemManager));
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
        /** @var array<string, array<string, mixed>> $diskConfig */
        $diskConfig = config('filesystems.disks', []);

        /** @var \Illuminate\Filesystem\FilesystemManager $filesystemManager */
        $filesystemManager = app(FilesystemManager::class);

        // If any of the disks have the 'sprout' driver, we need to purge them
        // if they exist, so we don't end up leaking tenant information
        foreach ($diskConfig as $disk => $config) {
            if (($config['driver'] ?? null) === 'sprout') {
                $filesystemManager->forgetDisk($disk);
            }
        }
    }

    /**
     * Create a driver creator
     *
     * @param \Sprout\Sprout                           $sprout
     * @param \Illuminate\Filesystem\FilesystemManager $manager
     *
     * @return \Closure
     */
    private static function creator(Sprout $sprout, FilesystemManager $manager): Closure
    {
        return static function (Application $app, array $config) use ($sprout, $manager): Filesystem {
            $tenancy = $sprout->tenancies()->get($config['tenancy'] ?? null);

            // If there's no tenant, error out
            if (! $tenancy->check()) {
                throw TenantMissing::make($tenancy->getName());
            }

            $tenant = $tenancy->tenant();

            // If the tenant isn't configured for resources, also error out
            if (! ($tenant instanceof TenantHasResources)) {
                throw MisconfigurationException::misconfigured('tenant', $tenant::class, 'resources');
            }

            $tenantConfig = self::getTenantStorageConfig($manager, $tenant, $config);

            // Create a scoped driver for the new path
            return $manager->createScopedDriver($tenantConfig);
        };
    }

    /**
     * Tenantise the storage config
     *
     * @param \Illuminate\Filesystem\FilesystemManager $manager
     * @param \Sprout\Contracts\TenantHasResources     $tenant
     * @param array<string, mixed>                     $config
     *
     * @return array<string, mixed>
     */
    private static function getTenantStorageConfig(FilesystemManager $manager, TenantHasResources $tenant, array $config): array
    {
        /** @var string $pathPrefix */
        $pathPrefix = $config['path'] ?? '{tenant}';

        // Create the empty tenant config
        $tenantConfig = [];

        // Build up the path prefix with the tenant resource key
        $tenantConfig['prefix'] = self::createTenantedPrefix($tenant, $pathPrefix);

        // Set the disk config on the newly created tenant config, so that the
        // filesystem manager uses this, rather gets it straight from the config
        $tenantConfig['disk'] = self::getDiskConfig($config);

        return $tenantConfig;
    }

    /**
     * Create a storage prefix using the current tenant
     *
     * @param \Sprout\Contracts\TenantHasResources $tenant
     * @param string                               $pathPrefix
     *
     * @return string
     */
    private static function createTenantedPrefix(TenantHasResources $tenant, string $pathPrefix): string
    {
        return str_replace('{tenant}', $tenant->getTenantResourceKey(), $pathPrefix);
    }

    /**
     * Get the config of the disk being tenantised
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private static function getDiskConfig(array $config): array
    {
        if (is_array($config['disk'])) {
            $diskConfig = $config['disk'];
        } else {
            /** @var string $diskName */
            $diskName   = $config['disk'] ?? config('filesystems.default');
            $diskConfig = config('filesystems.disks.' . $diskName);
        }

        /** @var array<string, mixed> $diskConfig */

        // This is where we'd do anything like load config overrides for
        // the tenant, like say they have their own S3 setup, etc.

        return $diskConfig;
    }
}
