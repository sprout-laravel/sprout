<?php
declare(strict_types=1);

namespace Sprout\Core\Overrides;

use Closure;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemManager;
use Sprout\Core\Contracts\BootableServiceOverride;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Contracts\Tenant;
use Sprout\Core\Overrides\Filesystem\SproutFilesystemDriverCreator;
use Sprout\Core\Sprout;

final class FilesystemOverride extends BaseOverride implements BootableServiceOverride
{
    /**
     * @var list<string>
     */
    protected array $drivers = [];

    /**
     * Get the drivers that have been resolved
     *
     * @return array<string>
     */
    public function getDrivers(): array
    {
        return $this->drivers;
    }

    /**
     * Boot a service override
     *
     * This method should perform any initial steps required for the service
     * override that take place during the booting of the framework.
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     * @param \Sprout\Core\Sprout                          $sprout
     *
     * @return void
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function boot(Application $app, Sprout $sprout): void
    {
        $tracker = fn (string $store) => $this->drivers[] = $store;

        // If the filesystem manager has been resolved, we can add the driver
        if ($app->resolved('filesystem')) {
            $this->addDriver($app->make('filesystem'), $sprout, $tracker);
        } else {
            // But if it hasn't, we'll add it once it is
            $app->afterResolving('filesystem', function (FilesystemManager $manager) use ($sprout, $tracker) {
                $this->addDriver($manager, $sprout, $tracker);
            });
        }
    }

    protected function addDriver(FilesystemManager $manager, Sprout $sprout, Closure $tracker): void
    {
        $manager->extend('sprout', function (Application $app, array $config) use ($manager, $sprout, $tracker): Filesystem {
            // If the config contains the disk name
            if (isset($config['name'])) {
                // Track it
                $tracker($config['name']);
            }

            /** @var array<string, mixed> $config */

            return (new SproutFilesystemDriverCreator($app, $manager, $config, $sprout))();
        });
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
     * @template TenantClass of \Sprout\Core\Contracts\Tenant
     *
     * @param \Sprout\Core\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Core\Contracts\Tenant               $tenant
     *
     * @phpstan-param TenantClass                         $tenant
     *
     * @return void
     */
    public function cleanup(Tenancy $tenancy, Tenant $tenant): void
    {
        if ($this->getApp()->resolved('filesystem')) {
            /** @var \Illuminate\Filesystem\FilesystemManager $filesystemManager */
            $filesystemManager = $this->getApp()->make(FilesystemManager::class);

            // If we're tracking some drivers we can simply forget those
            if (! empty($this->getDrivers())) {
                $filesystemManager->forgetDisk($this->getDrivers());
                $this->drivers = [];
            }

            /** @var array<string, array<string, mixed>> $diskConfig */
            $diskConfig = $this->getApp()->make('config')->get('filesystems.disks', []);

            // But if we don't, we have to cycle through the config and pick out
            // any that have the 'sprout' driver
            foreach ($diskConfig as $disk => $config) {
                if (($config['driver'] ?? null) === 'sprout') {
                    $filesystemManager->forgetDisk($disk);
                }
            }
        }
    }
}
