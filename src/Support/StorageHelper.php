<?php
declare(strict_types=1);

namespace Sprout\Support;

use Closure;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Application;
use RuntimeException;
use Sprout\Contracts\TenantHasResources;
use Sprout\Exceptions\TenantMissing;
use Sprout\Sprout;

final class StorageHelper
{
    public static function creator(Sprout $sprout, FilesystemManager $manager): Closure
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
                // TODO: Better exception
                throw new RuntimeException('Current tenant isn\t configured for resources');
            }

            $tenantConfig = self::getTenantStorageConfig($manager, $tenant, $config);

            // Create a scoped driver for the new path
            return $manager->createScopedDriver($tenantConfig);
        };
    }

    /**
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

    private static function createTenantedPrefix(TenantHasResources $tenant, string $pathPrefix): string
    {
        return str_replace('{tenant}', $tenant->getTenantResourceKey(), $pathPrefix);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private static function getDiskConfig(array $config): array
    {
        // Get the disk we're overriding and its config
        /** @var string $diskName */
        $diskName = $config['disk'] ?? config('filesystems.default');
        /** @var array<string, mixed> $diskConfig */
        $diskConfig = config('filesystems.disks.' . $diskName);

        // This is where we'd do anything like load config overrides for
        // the tenant, like say they have their own S3 setup, etc.

        return $diskConfig;
    }
}
