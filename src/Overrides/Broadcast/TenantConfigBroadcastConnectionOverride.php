<?php
declare(strict_types=1);

namespace Sprout\Overrides\Broadcast;

use Closure;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Foundation\Application;
use LogicException;
use Sprout\Overrides\TenantConfigBaseOverride;
use Sprout\Sprout;
use Sprout\TenantConfig;

/**
 * Broadcast Connection Override
 *
 * This override specifically allows for the creation of broadcast connections
 * using a tenant config store.
 *
 * @extends TenantConfigBaseOverride<BroadcastManager>
 */
final class TenantConfigBroadcastConnectionOverride extends TenantConfigBaseOverride
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
     * @param object       $service
     * @param TenantConfig $tenantConfig
     * @param Sprout       $sprout
     * @param Closure      $tracker
     *
     * @phpstan-param BroadcastManager $service
     *
     * @return void
     */
    protected function addDriver(object $service, TenantConfig $tenantConfig, Sprout $sprout, Closure $tracker): void
    {
        if (! $service instanceof TenantConfigBroadcastManager) {
            throw new LogicException('Cannot override broadcast connections without the tenant config broadcast manager override');
        }

        // Add a tenant config driver.
        $service->extend('sprout:config', function (Application $app, array $config) use ($service, $tenantConfig, $sprout, $tracker) {
            /** @var array<string, mixed>&array{configStore?:string|null,name?:string|null} $config */
            // If the config contains the disk name
            if (isset($config['name'])) {
                // Track it
                $tracker($config['name']);
            }

            return (new TenantConfigBroadcastConnectionCreator($service, $tenantConfig, $sprout, $config))();
        });
    }

    /**
     * Clean-up an overridden service.
     *
     * @param object $service
     * @param string $name
     *
     * @phpstan-param BroadcastManager $service
     *
     * @return void
     */
    protected function cleanupOverride(object $service, string $name): void
    {
        $service->purge($name);
    }
}
