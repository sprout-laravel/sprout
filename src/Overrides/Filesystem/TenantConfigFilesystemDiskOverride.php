<?php
declare(strict_types=1);

namespace Sprout\Overrides\Filesystem;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemManager;
use LogicException;
use Sprout\Overrides\TenantConfigBaseOverride;
use Sprout\Sprout;
use Sprout\TenantConfig;

/**
 * Filesystem Disk Override
 *
 * This override specifically allows for the creation of filesystem disks
 * using a tenant config store.
 *
 * @extends TenantConfigBaseOverride<FilesystemManager>
 */
final class TenantConfigFilesystemDiskOverride extends TenantConfigBaseOverride
{
    /**
     * Get the name of the service being overridden.
     *
     * @return string
     */
    protected function serviceName(): string
    {
        return 'filesystem';
    }

    /**
     * Add a driver to the service.
     *
     * @param object       $service
     * @param TenantConfig $tenantConfig
     * @param Sprout       $sprout
     * @param Closure      $tracker
     *
     * @phpstan-param FilesystemManager $service
     *
     * @return void
     */
    protected function addDriver(object $service, TenantConfig $tenantConfig, Sprout $sprout, Closure $tracker): void
    {
        if (! $service instanceof SproutFilesystemManager) {
            throw new LogicException('Cannot override filesystem disks without the Sprout filesystem manager override');
        }

        // Add a tenant config driver.
        $service->extend('sprout:config', function (Application $app, array $config) use ($service, $tenantConfig, $sprout, $tracker) {
            // Track the connection name.
            /** @var array<string, mixed>&array{configStore?:string|null,name?:string|null} $config */
            // If the config contains the disk name
            if (isset($config['name'])) {
                // Track it
                $tracker($config['name']);
            }

            return (new TenantConfigFilesystemDiskCreator($service, $tenantConfig, $sprout, $config))();
        });
    }

    /**
     * Clean-up an overridden service.
     *
     * @param object $service
     * @param string $name
     *
     * @phpstan-param FilesystemManager $service
     *
     * @return void
     */
    protected function cleanupOverride(object $service, string $name): void
    {
        $service->purge($name);
    }
}
