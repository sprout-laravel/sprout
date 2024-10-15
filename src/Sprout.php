<?php
declare(strict_types=1);

namespace Sprout;

use Illuminate\Contracts\Foundation\Application;
use Sprout\Contracts\ServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Managers\IdentityResolverManager;
use Sprout\Managers\ProviderManager;
use Sprout\Managers\TenancyManager;

final class Sprout
{
    /**
     * @var \Illuminate\Contracts\Foundation\Application
     */
    private Application $app;

    /**
     * @var array<int, \Sprout\Contracts\Tenancy<\Sprout\Contracts\Tenant>>
     */
    private array $tenancies = [];

    /**
     * @var array<class-string<\Sprout\Contracts\ServiceOverride>, \Sprout\Contracts\ServiceOverride>
     */
    private array $overrides = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->app->make('config')->get('sprout.' . $key, $default);
    }

    /**
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     *
     * @return void
     */
    public function setCurrentTenancy(Tenancy $tenancy): void
    {
        if ($this->getCurrentTenancy() !== $tenancy) {
            $this->tenancies[] = $tenancy;
        }
    }

    public function hasCurrentTenancy(): bool
    {
        return count($this->tenancies) > 0;
    }

    /**
     * @return \Sprout\Contracts\Tenancy<\Sprout\Contracts\Tenant>|null
     */
    public function getCurrentTenancy(): ?Tenancy
    {
        if ($this->hasCurrentTenancy()) {
            return $this->tenancies[count($this->tenancies) - 1];
        }

        return null;
    }

    /**
     * @return \Sprout\Contracts\Tenancy<\Sprout\Contracts\Tenant>[]
     */
    public function getAllCurrentTenancies(): array
    {
        return $this->tenancies;
    }

    public function shouldListenForRouting(): bool
    {
        return (bool)$this->config('listen_for_routing', true);
    }

    public function resolvers(): IdentityResolverManager
    {
        return $this->app->make(IdentityResolverManager::class);
    }

    public function providers(): ProviderManager
    {
        return $this->app->make(ProviderManager::class);
    }

    public function tenancies(): TenancyManager
    {
        return $this->app->make(TenancyManager::class);
    }

    public function hasOverride(string $class): bool
    {
        return isset($this->overrides[$class]);
    }

    public function addOverride(ServiceOverride $override): self
    {
        $this->overrides[$override::class] = $override;

        return $this;
    }

    /**
     * @return array<class-string<\Sprout\Contracts\ServiceOverride>, \Sprout\Contracts\ServiceOverride>
     */
    public function getOverrides(): array
    {
        return $this->overrides;
    }
}
