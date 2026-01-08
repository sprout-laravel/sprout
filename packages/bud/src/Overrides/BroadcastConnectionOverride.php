<?php
declare(strict_types=1);

namespace Sprout\Bud\Overrides;

use Closure;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Foundation\Application;
use LogicException;
use Sprout\Bud\Bud;
use Sprout\Bud\Overrides\Broadcast\BudBroadcastConnectionCreator;
use Sprout\Bud\Overrides\Broadcast\BudBroadcastManager;
use Sprout\Core\Sprout;

/**
 * Broadcast Connection Override
 *
 * This override specifically allows for the creation of broadcast connections
 * using Bud config store.
 *
 * @extends \Sprout\Bud\Overrides\BaseOverride<\Illuminate\Broadcasting\BroadcastManager>
 */
final class BroadcastConnectionOverride extends BaseOverride
{
    /**
     * Get the name of the service being overridden.
     *
     * @return string
     */
    protected function serviceName(): string
    {
        return BroadcastManager::class;
    }

    /**
     * Add a driver to the service.
     *
     * @param object                                            $service
     * @param \Sprout\Bud\Bud                              $bud
     * @param \Sprout\Core\Sprout                               $sprout
     * @param \Closure                                          $tracker
     *
     * @phpstan-param \Illuminate\Broadcasting\BroadcastManager $service
     *
     * @return void
     */
    protected function addDriver(object $service, Bud $bud, Sprout $sprout, Closure $tracker): void
    {
        if (! $service instanceof BudBroadcastManager) {
            throw new LogicException('Cannot override broadcast connections without the Bud broadcast manager override');
        }

        // Add a bud driver.
        $service->extend('bud', function (Application $app, array $config) use ($service, $bud, $sprout, $tracker) {
            /** @var array<string, mixed>&array{budStore?:string|null,name?:string|null} $config */
            // If the config contains the disk name
            if (isset($config['name'])) {
                // Track it
                $tracker($config['name']);
            }

            return (new BudBroadcastConnectionCreator($service, $bud, $sprout, $config))();
        });
    }

    /**
     * Clean-up an overridden service.
     *
     * @param object                                            $service
     * @param string                                            $name
     *
     * @phpstan-param \Illuminate\Broadcasting\BroadcastManager $service
     *
     * @return void
     */
    protected function cleanupOverride(object $service, string $name): void
    {
        $service->purge($name);
    }
}
