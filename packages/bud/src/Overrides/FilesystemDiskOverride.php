<?php
declare(strict_types=1);

namespace Sprout\Bud\Overrides;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use LogicException;
use Sprout\Bud\Bud;
use Sprout\Bud\Overrides\Filesystem\BudFilesystemDiskCreator;
use Sprout\Overrides\Filesystem\SproutFilesystemManager;
use Sprout\Sprout;

/**
 * Filesystem Disk Override
 *
 * This override specifically allows for the creation of filesystem disks
 * using the Bud config store.
 *
 * @extends \Sprout\Bud\Overrides\BaseOverride<\Illuminate\Filesystem\FilesystemManager>
 */
final class FilesystemDiskOverride extends BaseOverride
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
     * @param object                                           $service
     * @param \Sprout\Bud\Bud                                  $bud
     * @param \Sprout\Sprout                                   $sprout
     * @param \Closure                                         $tracker
     *
     * @phpstan-param \Illuminate\Filesystem\FilesystemManager $service
     *
     * @return void
     */
    protected function addDriver(object $service, Bud $bud, Sprout $sprout, Closure $tracker): void
    {
        if (! $service instanceof SproutFilesystemManager) {
            throw new LogicException('Cannot override filesystem disks without the Sprout filesystem manager override');
        }

        // Add a bud driver.
        $service->extend('bud', function (Application $app, array $config) use ($service, $bud, $sprout, $tracker) {
            // Track the connection name.
            /** @var array<string, mixed>&array{budStore?:string|null,name?:string|null} $config */
            // If the config contains the disk name
            if (isset($config['name'])) {
                // Track it
                $tracker($config['name']);
            }

            return (new BudFilesystemDiskCreator($service, $bud, $sprout, $config))();
        });
    }

    /**
     * Clean-up an overridden service.
     *
     * @param object                                           $service
     * @param string                                           $name
     *
     * @phpstan-param \Illuminate\Filesystem\FilesystemManager $service
     *
     * @return void
     */
    protected function cleanupOverride(object $service, string $name): void
    {
        $service->purge($name);
    }
}
