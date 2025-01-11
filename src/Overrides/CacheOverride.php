<?php
declare(strict_types=1);

namespace Sprout\Overrides;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Overrides\Cache\SproutCacheDriverCreator;
use Sprout\Sprout;

final class CacheOverride extends BaseOverride implements BootableServiceOverride
{
    /**
     * @var list<string>
     */
    protected array $drivers = [];

    /**
     * Boot a service override
     *
     * This method should perform any initial steps required for the service
     * override that take place during the booting of the framework.
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     * @param \Sprout\Sprout                               $sprout
     *
     * @return void
     */
    public function boot(Application $app, Sprout $sprout): void
    {
        // We only want to add the driver if the filesystem service is
        // resolved at some point
        $app->afterResolving('cache', function (CacheManager $manager) use ($sprout) {
            $manager->extend('sprout', function (Application $app, array $config) use ($manager, $sprout): Repository {
                // The cache manager adds the store name to the config, so we'll
                // _STORE_ that ;)
                $this->drivers[] = $config['store'];

                return (new SproutCacheDriverCreator($app, $manager, $config, $sprout))();
            });
        });
    }

    /**
     * Clean up the service override
     *
     * This method should perform any necessary setup actions for the service
     * override.
     * It is called when the current tenant is unset, either to be replaced
     * by another tenant, or none.
     *
     * It will be called before {@see self::setup()}, but only if the previous
     * tenant was not null.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Contracts\Tenant               $tenant
     *
     * @phpstan-param TenantClass                    $tenant
     *
     * @return void
     */
    public function cleanup(Tenancy $tenancy, Tenant $tenant): void
    {
        if (! empty($this->drivers)) {
            app(CacheManager::class)->forgetDriver($this->drivers);
        }
    }
}
