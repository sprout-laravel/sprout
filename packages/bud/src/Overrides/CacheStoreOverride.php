<?php
declare(strict_types=1);

namespace Sprout\Bud\Overrides;

use Closure;
use Illuminate\Foundation\Application;
use Sprout\Bud\Bud;
use Sprout\Bud\Overrides\Cache\BudCacheStoreCreator;
use Sprout\Core\Sprout;

/**
 * @extends \Sprout\Bud\Overrides\BaseOverride<\Illuminate\Cache\CacheManager>
 */
final class CacheStoreOverride extends BaseOverride
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
     * @param object                                 $service
     * @param \Sprout\Bud\Bud                   $bud
     * @param \Sprout\Core\Sprout                    $sprout
     * @param \Closure                               $tracker
     *
     * @phpstan-param \Illuminate\Cache\CacheManager $service
     *
     * @return void
     */
    protected function addDriver(object $service, Bud $bud, Sprout $sprout, Closure $tracker): void
    {
        $service->extend('bud', function (Application $app, array $config) use ($service, $bud, $sprout, $tracker) {
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
     * @param object                                 $service
     * @param string                                 $name
     *
     * @phpstan-param \Illuminate\Cache\CacheManager $service
     *
     * @return void
     */
    protected function cleanupOverride(object $service, string $name): void
    {
        $service->purge($name);
    }
}
