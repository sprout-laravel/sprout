<?php
declare(strict_types=1);

namespace Sprout\Overrides\Broadcast;

use Illuminate\Contracts\Broadcasting\Broadcaster;
use InvalidArgumentException;
use Sprout\TenantConfig;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Exceptions\TenancyMissingException;
use Sprout\Exceptions\TenantMissingException;
use Sprout\Overrides\BaseCreator;
use Sprout\Sprout;

/**
 * Tenant Config Broadcast Connection Creator
 *
 * This class is an abstraction for the logic that creates a broadcast connection
 * using a config store within tenant config.
 */
final class TenantConfigBroadcastConnectionCreator extends BaseCreator
{
    private TenantConfigBroadcastManager $manager;

    private TenantConfig $tenantConfig;

    private Sprout $sprout;

    /**
     * @var array<string, mixed>&array{configStore?:string|null,name?:mixed}
     */
    private array $config;

    /**
     * @param TenantConfigBroadcastManager                                     $manager
     * @param TenantConfig                                                     $tenantConfig
     * @param Sprout                                                           $sprout
     * @param array<string, mixed>&array{configStore?:string|null,name?:mixed} $config
     */
    public function __construct(
        TenantConfigBroadcastManager $manager,
        TenantConfig                 $tenantConfig,
        Sprout                       $sprout,
        array                        $config,
    ) {
        $this->manager      = $manager;
        $this->tenantConfig = $tenantConfig;
        $this->sprout       = $sprout;
        $this->config       = $config;
    }

    /**
     * Create the connection using tenant config.
     *
     * @return Broadcaster
     *
     * @throws MisconfigurationException
     * @throws TenancyMissingException
     * @throws TenantMissingException
     */
    public function __invoke(): Broadcaster
    {
        if (! isset($this->config['name']) || ! is_string($this->config['name'])) {
            throw new InvalidArgumentException('Broadcast connection name must be provided.');
        }

        /** @var array<string, mixed>&array{driver:string,name:string} $config */
        $config = $this->getConfig($this->sprout, $this->tenantConfig, $this->config, $this->config['name']);

        // We need to make sure that this isn't going to recurse infinitely.
        $this->checkForCyclicDrivers(
            $config['driver'],
            'broadcast connection',
            $this->config['name'],
        );

        // If we're here, it's not cyclic, so we'll create a dynamic connection,
        // using the methods from our manager override.
        return $this->manager->connectUsing(
            $config['name'],
            $config,
            true, // This is important, it needs to be here to avoid side-effect errors.
        );
    }

    /**
     * Get the name of the service for the creator.
     *
     * @return string
     */
    protected function getService(): string
    {
        return 'broadcast';
    }
}
