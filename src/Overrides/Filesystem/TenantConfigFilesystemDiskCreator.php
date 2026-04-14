<?php
declare(strict_types=1);

namespace Sprout\Overrides\Filesystem;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use InvalidArgumentException;
use Sprout\TenantConfig;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Exceptions\TenancyMissingException;
use Sprout\Exceptions\TenantMissingException;
use Sprout\Overrides\BaseCreator;
use Sprout\Sprout;

/**
 * Tenant Config Filesystem Disk Creator
 *
 * This class is an abstraction for the logic that creates a filesystem disk
 * using a config store within tenant config.
 */
final class TenantConfigFilesystemDiskCreator extends BaseCreator
{
    private FilesystemManager $manager;

    private TenantConfig $tenantConfig;

    private Sprout $sprout;

    /**
     * @var array<string, mixed>&array{configStore?:string|null,name?:mixed}
     */
    private array $config;

    /**
     * @param FilesystemManager                                                $manager
     * @param TenantConfig                                                     $tenantConfig
     * @param Sprout                                                           $sprout
     * @param array<string, mixed>&array{configStore?:string|null,name?:mixed} $config
     */
    public function __construct(
        FilesystemManager $manager,
        TenantConfig      $tenantConfig,
        Sprout            $sprout,
        array             $config,
    ) {
        $this->manager      = $manager;
        $this->tenantConfig = $tenantConfig;
        $this->sprout       = $sprout;
        $this->config       = $config;
    }

    /**
     * Create the connection using tenant config.
     *
     * @return Filesystem
     *
     * @throws MisconfigurationException
     * @throws TenancyMissingException
     * @throws TenantMissingException
     */
    public function __invoke(): Filesystem
    {
        if (! isset($this->config['name']) || ! is_string($this->config['name'])) {
            throw new InvalidArgumentException('Filesystem disk name must be provided.');
        }

        /** @var array<string, mixed>&array{driver?:string|null,name:string} $config */
        $config = $this->getConfig($this->sprout, $this->tenantConfig, $this->config, $this->config['name']);

        // We need to make sure that this isn't going to recurse infinitely.
        $this->checkForCyclicDrivers(
            $config['driver'] ?? null,
            'filesystem disk',
            $config['name'],
        );

        return $this->manager->build(
            array_merge([
                'name' => $config['name'],
            ], $config),
        );
    }

    /**
     * Get the name of the service for the creator.
     *
     * @return string
     */
    protected function getService(): string
    {
        return 'filesystem';
    }
}
