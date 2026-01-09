<?php
declare(strict_types=1);

namespace Sprout\Bud;

use Illuminate\Contracts\Foundation\Application;
use Sprout\Bud\Contracts\ConfigStore;
use Sprout\Bud\Managers\ConfigStoreManager;
use Sprout\Core\Concerns\AwareOfTenant;
use Sprout\Core\Contracts\TenantAware;
use Sprout\Core\Exceptions\TenancyMissingException;
use Sprout\Core\Exceptions\TenantMissingException;

final class Bud implements TenantAware
{
    use AwareOfTenant;

    /**
     * @var \Illuminate\Contracts\Foundation\Application
     * @phpstan-ignore property.onlyWritten
     */
    private Application $app;

    /**
     * @var \Sprout\Bud\Managers\ConfigStoreManager
     */
    private ConfigStoreManager $stores;

    public function __construct(
        Application         $app,
        ?ConfigStoreManager $stores = null
    )
    {
        $this->app    = $app;
        $this->stores = $stores ?? new ConfigStoreManager($app);
    }

    /**
     * Get the config store manager
     *
     * @return \Sprout\Bud\Managers\ConfigStoreManager
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
     * @return \Sprout\Bud\Contracts\ConfigStore
     *
     * @throws \Sprout\Core\Exceptions\MisconfigurationException
     */
    public function store(?string $name = null): ConfigStore
    {
        if ($this->hasTenancy()) {
            /** @var \Sprout\Core\Contracts\Tenancy<*> $tenancy */
            $tenancy = $this->getTenancy();
            
            // If there's a tenancy, we will use their locked store, the store
            // that was provided, or the default store.
            $name = BudOptions::getLockedStore($tenancy)
                    ?? $name
                       ?? BudOptions::getDefaultStore($tenancy);
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
     * @throws \Sprout\Core\Exceptions\MisconfigurationException
     * @throws \Sprout\Core\Exceptions\TenancyMissingException
     * @throws \Sprout\Core\Exceptions\TenantMissingException
     */
    public function config(string $service, string $name, ?array $default = null, ?string $store = null): ?array
    {
        if (! $this->hasTenancy()) {
            throw TenancyMissingException::make();
        }

        /** @var \Sprout\Core\Contracts\Tenancy<\Sprout\Core\Contracts\Tenant> $tenancy */
        $tenancy = $this->getTenancy();

        if (! $this->hasTenant()) {
            throw TenantMissingException::make($tenancy->getName());
        }

        /** @var \Sprout\Core\Contracts\Tenant $tenant */
        $tenant = $this->getTenant();

        return $this->store($store)
                    ->get(
                        $tenancy,
                        $tenant,
                        $service,
                        $name,
                        $default
                    );
    }
}
