<?php
declare(strict_types=1);

namespace Sprout\Overrides\Cache;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Exceptions\TenancyMissingException;
use Sprout\Exceptions\TenantMissingException;
use Sprout\Sprout;

final class SproutCacheDriverCreator
{
    /**
     * @var Application
     */
    private Application $app;

    /**
     * @var CacheManager
     */
    private CacheManager $manager;

    /**
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * @var Sprout
     */
    private Sprout $sprout;

    /**
     * Create a new instance
     *
     * @param Application          $app
     * @param CacheManager         $manager
     * @param array<string, mixed> $config
     * @param Sprout               $sprout
     */
    public function __construct(Application $app, CacheManager $manager, array $config, Sprout $sprout)
    {
        $this->app     = $app;
        $this->config  = $config;
        $this->manager = $manager;
        $this->sprout  = $sprout;
    }

    /**
     * Create the Sprout cache driver
     *
     * @return Repository
     *
     * @throws MisconfigurationException
     * @throws TenancyMissingException
     * @throws TenantMissingException
     */
    public function __invoke(): Repository
    {
        // If we're not within a multitenanted context, we need to error
        // out, as this driver shouldn't be hit without one
        if (! $this->sprout->withinContext()) {
            throw TenancyMissingException::make();
        }

        // Get the current active tenancy
        /** @var Tenancy<Tenant>|null $tenancy */
        $tenancy = $this->sprout->getCurrentTenancy();

        // If there isn't one, that's an issue as we need a tenancy
        if ($tenancy === null) {
            throw TenancyMissingException::make();
        }

        // If there is a tenancy, but it doesn't have a tenant, that's also
        // an issue
        if ($tenancy->check() === false) {
            throw TenantMissingException::make($tenancy->getName());
        }

        /** @var Tenant $tenant */
        $tenant = $tenancy->tenant();

        // We need to know which store we're overriding to make tenanted
        if (! isset($this->config['override']) || ! is_string($this->config['override'])) {
            throw MisconfigurationException::missingConfig('override', 'service override', 'cache');
        }

        // We need to get the config for that store
        /** @var array<string, mixed> $storeConfig */
        $storeConfig = $this->app->make('config')->get('cache.stores.' . $this->config['override']);

        if (empty($storeConfig)) {
            throw new InvalidArgumentException('Cache store [' . $this->config['override'] . '] is not defined');
        }

        // Get the prefix for the tenanted store based on the store config,
        // the tenancy and its current tenant
        $storeConfig['prefix'] = $this->getStorePrefix($storeConfig, $tenancy, $tenant);

        return $this->manager->build($storeConfig);
    }

    /**
     * Get the prefix for the store
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param array<string, mixed> $config
     * @param Tenancy<TenantClass> $tenancy
     * @param Tenant               $tenant
     *
     * @phpstan-param TenantClass                         $tenant
     *
     * @return string
     */
    private function getStorePrefix(array $config, Tenancy $tenancy, Tenant $tenant): string
    {
        return (isset($config['prefix']) && is_string($config['prefix']) ? $config['prefix'] . '_' : '')
               . $tenancy->getName()
               . '_'
               . $tenant->getTenantKey();
    }
}
