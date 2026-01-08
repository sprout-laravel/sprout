<?php
declare(strict_types=1);

namespace Sprout\Bud\Overrides;

use RuntimeException;
use Sprout\Bud\Bud;
use Sprout\Bud\Exceptions\CyclicOverrideException;
use Sprout\Core\Exceptions\TenancyMissingException;
use Sprout\Core\Exceptions\TenantMissingException;
use Sprout\Core\Sprout;

abstract class BaseCreator
{
    /**
     * Get the name of the service for the creator.
     *
     * @return string
     */
    abstract protected function getService(): string;

    /**
     * @param \Sprout\Core\Sprout                               $sprout
     * @param \Sprout\Bud\Bud                              $bud
     * @param array<string, mixed>&array{budStore?:string|null} $config
     * @param string                                            $name
     *
     * @return array<string, mixed>
     *
     * @throws \Sprout\Core\Exceptions\MisconfigurationException
     * @throws \Sprout\Core\Exceptions\TenancyMissingException
     * @throws \Sprout\Core\Exceptions\TenantMissingException
     */
    public function getConfig(Sprout $sprout, Bud $bud, array $config, string $name): array
    {
        // If we're not within a multitenanted context, we need to error
        // out, as this driver shouldn't be hit without one
        if (! $sprout->withinContext()) {
            throw TenancyMissingException::make();
        }

        // Get the current active tenancy
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

        /** @var \Sprout\Core\Contracts\Tenant $tenant */
        $tenant = $tenancy->tenant();

        // Get the default store, or the one specified in the config, if there
        // is one.
        $store = $bud->store($config['budStore'] ?? null);

        $service = $this->getService();

        // Get the config for the connection from the store.
        $budConfig = $store->get(
            $tenancy,
            $tenant,
            $service,
            $name,
        );

        // If there isn't any config, it's an error.
        if ($budConfig === null) {
            // TODO: Throw a better exception
            throw new RuntimeException(sprintf(
                'Unable to find configuration for [%s] for tenant [%s] on tenancy [%s]',
                $service . '.' . $name,
                $tenant->getTenantIdentifier(),
                $tenancy->getName()
            ));
        }

        return array_merge($config, $budConfig);
    }

    /**
     * Check if the driver is cyclic.
     *
     * @param string|null $driver
     * @param string      $term
     * @param string      $name
     *
     * @return void
     *
     */
    protected function checkForCyclicDrivers(?string $driver, string $term, string $name): void
    {
        if ($driver === 'bud') {
            throw CyclicOverrideException::make($term, $name);
        }
    }
}
