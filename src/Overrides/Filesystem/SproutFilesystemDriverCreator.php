<?php
declare(strict_types=1);

namespace Sprout\Overrides\Filesystem;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemManager;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\TenantHasResources;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Exceptions\TenancyMissingException;
use Sprout\Exceptions\TenantMissingException;
use Sprout\Sprout;
use Sprout\Support\PlaceholderHelper;

/**
 * Sprout Filesystem Driver Creator
 *
 * This class is an abstraction of the logic used to create the 'sprout' driver
 * with Laravel's filesystem service.
 */
final readonly class SproutFilesystemDriverCreator
{
    /**
     * @var Application
     */
    private Application $app;

    /**
     * @var FilesystemManager
     */
    private FilesystemManager $manager;

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
     * @param FilesystemManager    $manager
     * @param array<string, mixed> $config
     * @param Sprout               $sprout
     */
    public function __construct(Application $app, FilesystemManager $manager, array $config, Sprout $sprout)
    {
        $this->app     = $app;
        $this->config  = $config;
        $this->manager = $manager;
        $this->sprout  = $sprout;
    }

    /**
     * Create the sprout filesystem driver
     *
     * @return Filesystem
     *
     * @throws MisconfigurationException
     * @throws TenancyMissingException
     * @throws TenantMissingException
     */
    public function __invoke(): Filesystem
    {
        // If we're not within a multitenanted context, we need to error
        // out, as this driver shouldn't be hit without one
        if (! $this->sprout->withinContext()) {
            throw TenancyMissingException::make();
        }

        // Get the current active tenancy
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

        $tenant = $tenancy->tenant();

        // If the tenant isn't configured for resources, this is another issue
        if (! $tenant instanceof TenantHasResources) {
            throw MisconfigurationException::misconfigured('tenant', $tenancy->getName(), 'resources');
        }

        // Get a tenant-specific version of the store config
        $tenantConfig = $this->getTenantSpecificDiskConfig($tenancy, $tenant);

        // Create a scoped driver for the new path
        return $this->manager->createScopedDriver($tenantConfig);
    }

    /**
     * Make the disk config tenant-specific
     *
     * @param \Sprout\Contracts\Tenancy<*>         $tenancy
     * @param TenantHasResources $tenant
     *
     * @return array<string, mixed>
     */
    private function getTenantSpecificDiskConfig(Tenancy $tenancy, TenantHasResources $tenant): array
    {
        /** @var string $pathPrefix */
        $pathPrefix = $this->config['path'] ?? ('{tenancy}' . DIRECTORY_SEPARATOR . '{tenant}');

        // Create the empty tenant config
        $tenantConfig = [];

        // Build up the path prefix with the tenant resource key
        $tenantConfig['prefix'] = $this->createTenantedPrefix($tenancy, $tenant, $pathPrefix);

        // Set the disk config on the newly created tenant config, so that the
        // filesystem manager uses this, rather gets it straight from the config
        $tenantConfig['disk'] = $this->getTrueDiskConfig();

        return $tenantConfig;
    }

    /**
     * Create a storage prefix using the current tenant
     *
     * @param \Sprout\Contracts\Tenancy<*>         $tenancy
     * @param TenantHasResources $tenant
     * @param string             $pathPrefix
     *
     * @return string
     */
    private function createTenantedPrefix(Tenancy $tenancy, TenantHasResources $tenant, string $pathPrefix): string
    {
        return PlaceholderHelper::replace(
            $pathPrefix,
            [
                'tenancy' => $tenancy->getName(),
                'tenant'  => $tenant->getTenantResourceKey(),
            ],
        );
    }

    /**
     * Get the true disk config
     *
     * @return array<string, mixed>
     */
    private function getTrueDiskConfig(): array
    {
        if (isset($this->config['disk']) && is_array($this->config['disk'])) {
            $diskConfig = $this->config['disk'];
        } else {
            $config = $this->app->make('config');
            /** @var string $diskName */
            $diskName   = $this->config['disk'] ?? $config->get('filesystems.default');
            $diskConfig = $config->get('filesystems.disks.' . $diskName);
        }

        /** @var array<string, mixed> $diskConfig */

        // This is where we'd do anything like load config overrides for
        // the tenant, like say they have their own S3 setup, etc.

        return $diskConfig;
    }
}
