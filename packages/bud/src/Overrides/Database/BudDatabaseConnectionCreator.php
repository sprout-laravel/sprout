<?php
declare(strict_types=1);

namespace Sprout\Bud\Overrides\Database;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Sprout\Bud\Bud;
use Sprout\Bud\Overrides\BaseCreator;
use Sprout\Core\Sprout;

/**
 * Bud Database Connection Creator
 *
 * This class is an abstraction for the logic that creates a database connection
 * using a config store within Bud.
 */
final class BudDatabaseConnectionCreator extends BaseCreator
{
    private DatabaseManager $manager;

    private Bud $bud;

    private Sprout $sprout;

    private string $name;

    /**
     * @var array<string, mixed>&array{budStore?:string|null}
     */
    private array $config;

    /**
     * @param \Illuminate\Database\DatabaseManager              $manager
     * @param \Sprout\Bud\Bud                              $bud
     * @param \Sprout\Core\Sprout                               $sprout
     * @param string                                            $name
     * @param array<string, mixed>&array{budStore?:string|null} $config
     */
    public function __construct(
        DatabaseManager $manager,
        Bud             $bud,
        Sprout          $sprout,
        string          $name,
        array           $config
    )
    {
        $this->manager = $manager;
        $this->bud     = $bud;
        $this->sprout  = $sprout;
        $this->name    = $name;
        $this->config  = $config;
    }

    /**
     * Create the connection using Bud.
     *
     * @return \Illuminate\Database\ConnectionInterface
     *
     * @throws \Sprout\Core\Exceptions\MisconfigurationException
     * @throws \Sprout\Core\Exceptions\TenancyMissingException
     * @throws \Sprout\Core\Exceptions\TenantMissingException
     */
    public function __invoke(): ConnectionInterface
    {
        /** @var array<string, mixed>&array{driver?:string|null} $config */
        $config = $this->getConfig($this->sprout, $this->bud, $this->config, $this->name);

        // We need to make sure that this isn't going to recurse infinitely.
        $this->checkForCyclicDrivers(
            $config['driver'] ?? null,
            'database connection',
            $this->name
        );

        // If we're here, it's not cyclic, so we'll create a dynamic connection.
        // We're intentionally not using the methods for creating a dynamic
        // connection because it does funky stuff with the names.
        return $this->manager->connectUsing(
            $this->name,
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
        return 'database';
    }
}
