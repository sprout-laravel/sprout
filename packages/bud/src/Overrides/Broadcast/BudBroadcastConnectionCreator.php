<?php
declare(strict_types=1);

namespace Sprout\Bud\Overrides\Broadcast;

use Illuminate\Contracts\Broadcasting\Broadcaster;
use InvalidArgumentException;
use Sprout\Bud\Bud;
use Sprout\Bud\Overrides\BaseCreator;
use Sprout\Sprout;

/**
 * Bud Broadcast Connection Creator
 *
 * This class is an abstraction for the logic that creates a broadcast connection
 * using a config store within Bud.
 */
final class BudBroadcastConnectionCreator extends BaseCreator
{
    private BudBroadcastManager $manager;

    private Bud $bud;

    private Sprout $sprout;

    /**
     * @var array<string, mixed>&array{budStore?:string|null,name?:mixed}
     */
    private array $config;

    /**
     * @param \Sprout\Bud\Overrides\Broadcast\BudBroadcastManager           $manager
     * @param \Sprout\Bud\Bud                                               $bud
     * @param \Sprout\Sprout                                                $sprout
     * @param array<string, mixed>&array{budStore?:string|null,name?:mixed} $config
     */
    public function __construct(
        BudBroadcastManager $manager,
        Bud                 $bud,
        Sprout              $sprout,
        array               $config
    )
    {
        $this->manager = $manager;
        $this->bud     = $bud;
        $this->sprout  = $sprout;
        $this->config  = $config;
    }

    /**
     * Create the connection using Bud.
     *
     * @return \Illuminate\Contracts\Broadcasting\Broadcaster
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    public function __invoke(): Broadcaster
    {
        if (! isset($this->config['name']) || ! is_string($this->config['name'])) {
            throw new InvalidArgumentException('Broadcast connection name must be provided.');
        }

        /** @var array<string, mixed>&array{driver:string,name:string} $config */
        $config = $this->getConfig($this->sprout, $this->bud, $this->config, $this->config['name']);

        // We need to make sure that this isn't going to recurse infinitely.
        $this->checkForCyclicDrivers(
            $config['driver'],
            'broadcast connection',
            $this->config['name']
        );

        // If we're here, it's not cyclic, so we'll create a dynamic connection,
        // using the methods from our manager override.
        return $this->manager->connectUsing(
            $config['name'],
            $config,
            true // This is important, it needs to be here to avoid side-effect errors.
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
