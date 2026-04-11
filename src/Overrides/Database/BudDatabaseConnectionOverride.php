<?php
declare(strict_types=1);

namespace Sprout\Overrides\Database;

use Closure;
use Illuminate\Database\DatabaseManager;
use Sprout\Bud;
use Sprout\Overrides\BudBaseOverride;
use Sprout\Sprout;

/**
 * Database Connection Override
 *
 * This override specifically allows for the creation of database connections
 * using Bud config store.
 *
 * @extends BudBaseOverride<DatabaseManager>
 */
final class BudDatabaseConnectionOverride extends BudBaseOverride
{
    /**
     * Get the name of the service being overridden.
     *
     * @return string
     */
    protected function serviceName(): string
    {
        return 'db';
    }

    /**
     * Add a driver to the service.
     *
     * @param object  $service
     * @param Bud     $bud
     * @param Sprout  $sprout
     * @param Closure $tracker
     *
     * @phpstan-param DatabaseManager $service
     *
     * @return void
     */
    protected function addDriver(object $service, Bud $bud, Sprout $sprout, Closure $tracker): void
    {
        // Add a bud driver.
        $service->extend('sprout:bud', function ($config, $name) use ($service, $bud, $sprout, $tracker) {
            // Track the connection name.
            $tracker($name);

            /**
             * @var string                                            $name
             * @var array<string, mixed>&array{budStore?:string|null} $config
             */

            return (new BudDatabaseConnectionCreator($service, $bud, $sprout, $name, $config))();
        });
    }

    /**
     * Clean-up an overridden service.
     *
     * @param object $service
     * @param string $name
     *
     * @phpstan-param DatabaseManager $service
     *
     * @return void
     */
    protected function cleanupOverride(object $service, string $name): void
    {
        $service->purge($name);
    }
}
