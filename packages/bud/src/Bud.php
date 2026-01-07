<?php
declare(strict_types=1);

namespace Sprout\Bud;

use Illuminate\Contracts\Foundation\Application;
use Sprout\Bud\Contracts\ConfigStore;
use Sprout\Bud\Managers\ConfigStoreManager;
use Sprout\Concerns\AwareOfTenant;
use Sprout\Contracts\TenantAware;
use Sprout\Exceptions\TenancyMissingException;
use Sprout\Exceptions\TenantMissingException;

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
     * @throws \Sprout\Exceptions\MisconfigurationException
     */
    public function store(?string $name = null): ConfigStore
    {
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
     * @throws \Sprout\Exceptions\MisconfigurationException
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    public function config(string $service, string $name, ?array $default = null, ?string $store = null): ?array
    {
        if (! $this->hasTenancy()) {
            throw TenancyMissingException::make();
        }

        /** @var \Sprout\Contracts\Tenancy<\Sprout\Contracts\Tenant> $tenancy */
        $tenancy = $this->getTenancy();

        if (! $this->hasTenant()) {
            throw TenantMissingException::make($tenancy->getName());
        }

        /** @var \Sprout\Contracts\Tenant $tenant */
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
