<?php
declare(strict_types=1);

namespace Sprout\Overrides\Cache;

use Closure;
use Illuminate\Cache\CacheManager;
use Illuminate\Foundation\Application;
use Sprout\Overrides\TenantConfigBaseOverride;
use Sprout\Sprout;
use Sprout\TenantConfig;

/**
 * @extends TenantConfigBaseOverride<CacheManager>
 */
final class TenantConfigCacheStoreOverride extends TenantConfigBaseOverride
{
    /**
     * Get the name of the service being overridden.
     *
     * @return string
     */
    protected function serviceName(): string
    {
        return 'cache';
    }

    /**
     * Add a driver to the service.
     *
     * @param object       $service
     * @param TenantConfig $tenantConfig
     * @param Sprout       $sprout
     * @param Closure      $tracker
     *
     * @phpstan-param CacheManager $service
     *
     * @return void
     */
    protected function addDriver(object $service, TenantConfig $tenantConfig, Sprout $sprout, Closure $tracker): void
    {
        $service->extend('sprout:config', function (Application $app, array $config) use ($service, $tenantConfig, $sprout, $tracker) {
            /**
             * @var array<string, mixed>&array{configStore?:string|null,store:string} $config
             */

            // Track the cache store name.
            $tracker($config['store']);

            return (new TenantConfigCacheStoreCreator($service, $tenantConfig, $sprout, $config['store'], $config))();
        });
    }

    /**
     * Clean-up an overridden service.
     *
     * @param object $service
     * @param string $name
     *
     * @phpstan-param CacheManager $service
     *
     * @return void
     */
    protected function cleanupOverride(object $service, string $name): void
    {
        $service->purge($name);
    }
}
