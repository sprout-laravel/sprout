<?php
declare(strict_types=1);

namespace Sprout\Overrides\Cache;

use Closure;
use Illuminate\Cache\CacheManager;
use Illuminate\Foundation\Application;
use Sprout\Bud;
use Sprout\Overrides\BudBaseOverride;
use Sprout\Sprout;

/**
 * @extends BudBaseOverride<CacheManager>
 */
final class BudCacheStoreOverride extends BudBaseOverride
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
     * @param object  $service
     * @param Bud     $bud
     * @param Sprout  $sprout
     * @param Closure $tracker
     *
     * @phpstan-param CacheManager $service
     *
     * @return void
     */
    protected function addDriver(object $service, Bud $bud, Sprout $sprout, Closure $tracker): void
    {
        $service->extend('sprout:bud', function (Application $app, array $config) use ($service, $bud, $sprout, $tracker) {
            /**
             * @var array<string, mixed>&array{budStore?:string|null,store:string} $config
             */

            // Track the cache store name.
            $tracker($config['store']);

            return (new BudCacheStoreCreator($service, $bud, $sprout, $config['store'], $config))();
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
