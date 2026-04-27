<?php
declare(strict_types=1);

namespace Sprout\Overrides\Database;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Exceptions\TenancyMissingException;
use Sprout\Exceptions\TenantMissingException;
use Sprout\Overrides\BaseCreator;
use Sprout\Sprout;
use Sprout\TenantConfig;

/**
 * Tenant Config Database Connection Creator
 *
 * This class is an abstraction for the logic that creates a database connection
 * using a config store within tenant config.
 */
final class TenantConfigDatabaseConnectionCreator extends BaseCreator
{
    private DatabaseManager $manager;

    private TenantConfig $tenantConfig;

    private Sprout $sprout;

    private string $name;

    /**
     * @var array<string, mixed>&array{configStore?:string|null}
     */
    private array $config;

    /**
     * @param DatabaseManager                                      $manager
     * @param TenantConfig                                         $tenantConfig
     * @param Sprout                                               $sprout
     * @param string                                               $name
     * @param array<string, mixed>&array{configStore?:string|null} $config
     */
    public function __construct(
        DatabaseManager $manager,
        TenantConfig    $tenantConfig,
        Sprout          $sprout,
        string          $name,
        array           $config,
    ) {
        $this->manager      = $manager;
        $this->tenantConfig = $tenantConfig;
        $this->sprout       = $sprout;
        $this->name         = $name;
        $this->config       = $config;
    }

    /**
     * Create the connection using tenant config.
     *
     * @return ConnectionInterface
     *
     * @throws MisconfigurationException
     * @throws TenancyMissingException
     * @throws TenantMissingException
     */
    public function __invoke(): ConnectionInterface
    {
        /** @var array<string, mixed>&array{driver?:string|null} $config */
        $config = $this->getConfig($this->sprout, $this->tenantConfig, $this->config, $this->name);

        // We need to make sure that this isn't going to recurse infinitely.
        $this->checkForCyclicDrivers(
            $config['driver'] ?? null,
            'database connection',
            $this->name,
        );

        // If we're here, it's not cyclic, so we'll create a dynamic connection.
        // We're intentionally not using the methods for creating a dynamic
        // connection because it does funky stuff with the names.
        return $this->manager->connectUsing(
            $this->name,
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
        return 'database';
    }
}
