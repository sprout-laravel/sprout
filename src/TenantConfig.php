<?php
declare(strict_types=1);

namespace Sprout;

use Illuminate\Contracts\Foundation\Application;
use Sprout\Concerns\AwareOfTenant;
use Sprout\Contracts\ConfigStore;
use Sprout\Contracts\TenantAware;
use Sprout\Exceptions\TenancyMissingException;
use Sprout\Exceptions\TenantMissingException;
use Sprout\Managers\ConfigStoreManager;

final class TenantConfig implements TenantAware
{
    use AwareOfTenant;

    /**
     * @var Application
     *
     * @phpstan-ignore property.onlyWritten
     */
    private Application $app;

    /**
     * @var ConfigStoreManager
     */
    private ConfigStoreManager $stores;

    public function __construct(
        Application         $app,
        ?ConfigStoreManager $stores = null,
    ) {
        $this->app    = $app;
        $this->stores = $stores ?? new ConfigStoreManager($app);
    }

    /**
     * Get the config store manager
     *
     * @return ConfigStoreManager
     */
    public function stores(): ConfigStoreManager
    {
        return $this->stores;
    }

    /**
     * Get a config store
     *
     * @param string|null $name
     *
     * @return ConfigStore
     *
     * @throws Exceptions\MisconfigurationException
     */
    public function store(?string $name = null): ConfigStore
    {
        if ($this->hasTenancy()) {
            /** @var \Sprout\Contracts\Tenancy<*> $tenancy */
            $tenancy = $this->getTenancy();

            // If there's a tenancy, we will use their locked store, the store
            // that was provided, or the default store.
            $name = TenancyOptions::getLockedStore($tenancy)
                       ?? $name
                       ?? TenancyOptions::getDefaultStore($tenancy);
        }

        return $this->stores->get($name);
    }

    /**
     * Get a config value for the current tenancy and tenant
     *
     * @param string                    $service
     * @param string                    $name
     * @param array<string, mixed>|null $default
     * @param string|null               $store
     *
     * @return array<string, mixed>|null
     *
     * @throws Exceptions\MisconfigurationException
     * @throws TenancyMissingException
     * @throws TenantMissingException
     */
    public function config(string $service, string $name, ?array $default = null, ?string $store = null): ?array
    {
        if (! $this->hasTenancy()) {
            throw TenancyMissingException::make();
        }

        /** @var Contracts\Tenancy<Contracts\Tenant> $tenancy */
        $tenancy = $this->getTenancy();

        if (! $this->hasTenant()) {
            throw TenantMissingException::make($tenancy->getName());
        }

        /** @var Contracts\Tenant $tenant */
        $tenant = $this->getTenant();

        return $this->store($store)
                    ->get(
                        $tenancy,
                        $tenant,
                        $service,
                        $name,
                        $default,
                    );
    }
}
