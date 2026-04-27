<?php
declare(strict_types=1);

namespace Sprout\Overrides\Cache;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Arr;
use Sprout\Exceptions\CyclicOverrideException;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Exceptions\TenancyMissingException;
use Sprout\Exceptions\TenantMissingException;
use Sprout\Overrides\BaseCreator;
use Sprout\Sprout;
use Sprout\TenantConfig;

final class TenantConfigCacheStoreCreator extends BaseCreator
{
    private CacheManager $manager;

    private TenantConfig $tenantConfig;

    private Sprout $sprout;

    private string $name;

    /**
     * @var array<string, mixed>&array{configStore?:string|null}
     */
    private array $config;

    /**
     * @param CacheManager                                         $manager
     * @param TenantConfig                                         $tenantConfig
     * @param Sprout                                               $sprout
     * @param string                                               $name
     * @param array<string, mixed>&array{configStore?:string|null} $config
     */
    public function __construct(
        CacheManager $manager,
        TenantConfig $tenantConfig,
        Sprout       $sprout,
        string       $name,
        array        $config = [],
    ) {
        $this->manager      = $manager;
        $this->tenantConfig = $tenantConfig;
        $this->sprout       = $sprout;
        $this->name         = $name;
        $this->config       = $config;
    }

    /**
     * @return Repository
     *
     * @throws CyclicOverrideException
     * @throws MisconfigurationException
     * @throws TenancyMissingException
     * @throws TenantMissingException
     */
    public function __invoke(): Repository
    {
        /** @var array<string, mixed>&array{driver:string} $config */
        $config = $this->getConfig($this->sprout, $this->tenantConfig, $this->config, $this->name);

        // We need to make sure that this isn't going to recurse infinitely.
        $this->checkForCyclicDrivers(
            $config['driver'],
            'cache store',
            $this->name,
        );

        return $this->manager->build(Arr::except($config, ['store']));
    }

    /**
     * Get the name of the service for the creator.
     *
     * @return string
     */
    protected function getService(): string
    {
        return 'cache';
    }
}
