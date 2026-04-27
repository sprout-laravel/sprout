<?php
declare(strict_types=1);

namespace Sprout\Overrides;

use RuntimeException;
use Sprout\TenantConfig;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Exceptions\CyclicOverrideException;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Exceptions\TenancyMissingException;
use Sprout\Exceptions\TenantMissingException;
use Sprout\Sprout;

abstract class BaseCreator
{
    /**
     * @param Sprout                                              $sprout
     * @param TenantConfig                                        $tenantConfig
     * @param array<string, mixed>&array{configStore?:string|null} $config
     * @param string                                              $name
     *
     * @return array<string, mixed>
     *
     * @throws MisconfigurationException
     * @throws TenancyMissingException
     * @throws TenantMissingException
     */
    public function getConfig(Sprout $sprout, TenantConfig $tenantConfig, array $config, string $name): array
    {
        // If we're not within a multitenanted context, we need to error
        // out, as this driver shouldn't be hit without one
        if (! $sprout->withinContext()) {
            throw TenancyMissingException::make();
        }

        // Get the current active tenancy
        /** @var Tenancy<Tenant>|null $tenancy */
        $tenancy = $sprout->getCurrentTenancy();

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

        // Get the default store, or the one specified in the config, if there
        // is one.
        $store = $tenantConfig->store($config['configStore'] ?? null);

        $service = $this->getService();

        // Get the config for the connection from the store.
        $storedConfig = $store->get(
            $tenancy,
            $tenant,
            $service,
            $name,
        );

        // If there isn't any config, it's an error.
        if ($storedConfig === null) {
            // TODO: Throw a better exception
            throw new RuntimeException(sprintf('Unable to find configuration for [%s] for tenant [%s] on tenancy [%s]', $service . '.' . $name, $tenant->getTenantIdentifier(), $tenancy->getName()));
        }

        return array_merge($config, $storedConfig);
    }

    /**
     * Get the name of the service for the creator.
     *
     * @return string
     */
    abstract protected function getService(): string;

    /**
     * Check if the driver is cyclic.
     *
     * @param string|null $driver
     * @param string      $term
     * @param string      $name
     *
     * @return void
     */
    protected function checkForCyclicDrivers(?string $driver, string $term, string $name): void
    {
        if ($driver === 'sprout:config') {
            throw CyclicOverrideException::make($term, $name);
        }
    }
}
