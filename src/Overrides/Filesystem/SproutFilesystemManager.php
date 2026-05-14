<?php
declare(strict_types=1);

namespace Sprout\Overrides\Filesystem;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemManager;
use InvalidArgumentException;

class SproutFilesystemManager extends FilesystemManager
{
    protected bool $syncedFromOriginal = false;

    public function __construct(Application $app, ?FilesystemManager $original = null)
    {
        parent::__construct($app);

        if ($original) {
            $this->syncOriginal($original);
        }
    }

    /**
     * Check if this manager override was synced from the original
     *
     * @return bool
     */
    public function wasSyncedFromOriginal(): bool
    {
        return $this->syncedFromOriginal;
    }

    /**
     * Sync the original manager in case things have been registered
     *
     * @param \Illuminate\Filesystem\FilesystemManager $original
     *
     * @return void
     */
    private function syncOriginal(FilesystemManager $original): void
    {
        $this->disks              = array_merge($original->disks, $this->disks);
        $this->customCreators     = array_merge($original->customCreators, $this->customCreators);
        $this->syncedFromOriginal = true;
    }

    /**
     * Resolve the given disk.
     *
     * @param string                    $name
     * @param array<string, mixed>|null $config
     *
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve($name, $config = null): Filesystem
    {
        $config ??= $this->getConfig($name);

        $driver = $config['driver'] ?? null;

        if (! is_string($driver) || $driver === '') {
            throw new InvalidArgumentException("Disk [{$name}] does not have a configured driver.");
        }

        $config['name'] = $name;

        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($config);
        }

        $driverMethod = 'create' . ucfirst($driver) . 'Driver';

        if (! method_exists($this, $driverMethod)) {
            throw new InvalidArgumentException("Driver [{$driver}] is not supported.");
        }

        $result = $this->{$driverMethod}($config, $name);

        if (! $result instanceof Filesystem) {
            throw new InvalidArgumentException("Driver [{$driver}] did not return a Filesystem instance.");
        }

        return $result;
    }
}
