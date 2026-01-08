<?php
declare(strict_types=1);

namespace Sprout\Bud\Overrides\Cache;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Arr;
use Sprout\Bud\Bud;
use Sprout\Bud\Overrides\BaseCreator;
use Sprout\Core\Sprout;

final class BudCacheStoreCreator extends BaseCreator
{
    private CacheManager $manager;

    private Bud $bud;

    private Sprout $sprout;

    private string $name;

    /**
     * @var array<string, mixed>&array{budStore?:string|null}
     */
    private array $config;

    /**
     * @param \Illuminate\Cache\CacheManager                    $manager
     * @param \Sprout\Bud\Bud                              $bud
     * @param \Sprout\Core\Sprout                               $sprout
     * @param string                                            $name
     * @param array<string, mixed>&array{budStore?:string|null} $config
     */
    public function __construct(
        CacheManager $manager,
        Bud          $bud,
        Sprout       $sprout,
        string       $name,
        array        $config = []
    )
    {
        $this->manager = $manager;
        $this->bud     = $bud;
        $this->sprout  = $sprout;
        $this->name    = $name;
        $this->config  = $config;
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

    /**
     * @return \Illuminate\Contracts\Cache\Repository
     *
     * @throws \Sprout\Bud\Exceptions\CyclicOverrideException
     * @throws \Sprout\Core\Exceptions\MisconfigurationException
     * @throws \Sprout\Core\Exceptions\TenancyMissingException
     * @throws \Sprout\Core\Exceptions\TenantMissingException
     */
    public function __invoke(): Repository
    {
        /** @var array<string, mixed>&array{driver:string} $config */
        $config = $this->getConfig($this->sprout, $this->bud, $this->config, $this->name);

        // We need to make sure that this isn't going to recurse infinitely.
        $this->checkForCyclicDrivers(
            $config['driver'],
            'cache store',
            $this->name
        );

        return $this->manager->build(Arr::except($config, ['store']));
    }
}
