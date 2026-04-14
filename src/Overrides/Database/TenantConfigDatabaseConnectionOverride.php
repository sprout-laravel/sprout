<?php
declare(strict_types=1);

namespace Sprout\Overrides\Database;

use Closure;
use Illuminate\Database\DatabaseManager;
use Sprout\TenantConfig;
use Sprout\Overrides\TenantConfigBaseOverride;
use Sprout\Sprout;

/**
 * Database Connection Override
 *
 * This override specifically allows for the creation of database connections
 * using a tenant config store.
 *
 * @extends TenantConfigBaseOverride<DatabaseManager>
 */
final class TenantConfigDatabaseConnectionOverride extends TenantConfigBaseOverride
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
     * @param object       $service
     * @param TenantConfig $tenantConfig
     * @param Sprout       $sprout
     * @param Closure      $tracker
     *
     * @phpstan-param DatabaseManager $service
     *
     * @return void
     */
    protected function addDriver(object $service, TenantConfig $tenantConfig, Sprout $sprout, Closure $tracker): void
    {
        // Add a tenant config driver.
        $service->extend('sprout:config', function ($config, $name) use ($service, $tenantConfig, $sprout, $tracker) {
            // Track the connection name.
            $tracker($name);

            /**
             * @var string                                               $name
             * @var array<string, mixed>&array{configStore?:string|null} $config
             */

            return (new TenantConfigDatabaseConnectionCreator($service, $tenantConfig, $sprout, $name, $config))();
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
