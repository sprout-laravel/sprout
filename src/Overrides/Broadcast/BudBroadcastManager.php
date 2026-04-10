<?php
declare(strict_types=1);

namespace Sprout\Bud\Overrides\Broadcast;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use InvalidArgumentException;

class BudBroadcastManager extends BroadcastManager
{
    protected bool $syncedFromOriginal = false;

    public function __construct($app, ?BroadcastManager $original = null)
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
     * @param \Illuminate\Broadcasting\BroadcastManager $original
     *
     * @return void
     */
    private function syncOriginal(BroadcastManager $original): void
    {
        $this->drivers            = array_merge($original->drivers, $this->drivers);
        $this->customCreators     = array_merge($original->customCreators, $this->customCreators);
        $this->syncedFromOriginal = true;
    }

    /**
     * Get a driver instance using the given config.
     *
     * @param string                                    $name
     * @param array<string, mixed>&array{driver:string} $config
     * @param bool                                      $force
     *
     * @return \Illuminate\Contracts\Broadcasting\Broadcaster
     */
    public function connectUsing(string $name, array $config, bool $force = false): Broadcaster
    {
        if ($force) {
            $this->purge($name);
        }

        $config['name'] = $name;

        /** @var \Illuminate\Contracts\Broadcasting\Broadcaster */
        return $this->drivers[$name] ?? ($this->drivers[$name] = $this->resolveUsing($config));
    }

    /**
     * Resolve the given broadcaster.
     *
     * @param string $name
     *
     * @return \Illuminate\Contracts\Broadcasting\Broadcaster
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve($name): Broadcaster
    {
        /** @var (array<string, mixed>&array{driver:string})|null $config */
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new InvalidArgumentException("Broadcast connection [{$name}] is not defined.");
        }

        $config['name'] = $name;

        return $this->resolveUsing($config);
    }

    /**
     * Resolve the given broadcaster.
     *
     * @param array<string, mixed>&array{driver:string,name:string} $config
     *
     * @return \Illuminate\Contracts\Broadcasting\Broadcaster
     *
     */
    public function resolveUsing(array $config): Broadcaster
    {
        if (empty($config['driver'])) {
            throw new InvalidArgumentException("Broadcast connection [{$config['name']}] does not have a configured driver.");
        }

        if (isset($this->customCreators[$config['driver']])) {
            /** @var \Illuminate\Contracts\Broadcasting\Broadcaster */
            return $this->callCustomCreator($config);
        }

        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

        if (! method_exists($this, $driverMethod)) {
            throw new InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
        }

        /** @var \Illuminate\Contracts\Broadcasting\Broadcaster */
        return $this->{$driverMethod}($config);
    }
}
