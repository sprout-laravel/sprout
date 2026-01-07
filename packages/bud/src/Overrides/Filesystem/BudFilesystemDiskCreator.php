<?php
declare(strict_types=1);

namespace Sprout\Bud\Overrides\Filesystem;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use InvalidArgumentException;
use Sprout\Bud\Bud;
use Sprout\Bud\Overrides\BaseCreator;
use Sprout\Sprout;

/**
 * Bud Filesystem Disk Creator
 *
 * This class is an abstraction for the logic that creates a filesystem disk
 * using a config store within Bud.
 */
final class BudFilesystemDiskCreator extends BaseCreator
{
    private FilesystemManager $manager;

    private Bud $bud;

    private Sprout $sprout;

    /**
     * @var array<string, mixed>&array{budStore?:string|null,name?:mixed}
     */
    private array $config;

    /**
     * @param \Illuminate\Filesystem\FilesystemManager                      $manager
     * @param \Sprout\Bud\Bud                                               $bud
     * @param \Sprout\Sprout                                                $sprout
     * @param array<string, mixed>&array{budStore?:string|null,name?:mixed} $config
     */
    public function __construct(
        FilesystemManager $manager,
        Bud               $bud,
        Sprout            $sprout,
        array             $config
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
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    public function __invoke(): Filesystem
    {
        if (! isset($this->config['name']) || ! is_string($this->config['name'])) {
            throw new InvalidArgumentException('Filesystem disk name must be provided.');
        }

        /** @var array<string, mixed>&array{driver?:string|null,name:string} $config */
        $config = $this->getConfig($this->sprout, $this->bud, $this->config, $this->config['name']);

        // We need to make sure that this isn't going to recurse infinitely.
        $this->checkForCyclicDrivers(
            $config['driver'] ?? null,
            'filesystem disk',
            $config['name']
        );

        return $this->manager->build(
            array_merge([
                'name' => $config['name'],
            ], $config)
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
