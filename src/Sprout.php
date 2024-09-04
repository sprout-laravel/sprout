<?php
declare(strict_types=1);

namespace Sprout;

use Illuminate\Contracts\Foundation\Application;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
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

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function config(string $key, mixed $default): mixed
    {
        /** @phpstan-ignore-next-line */
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
        return (bool) $this->config('listen_for_routing', true);
    }

    /**
     * @param \Sprout\Contracts\Tenancy<\Sprout\Contracts\Tenant> $tenancy
     *
     * @return string
     */
    public function contextKey(Tenancy $tenancy): string
    {
        /** @phpstan-ignore-next-line */
        return str_replace(['{tenancy}'], [$tenancy->getName()], $this->config('context.key', '{tenancy}_key'));
    }

    public function contextValue(Tenant $tenant): int|string
    {
        return $this->config('context.user', 'key') === 'key'
            ? $tenant->getTenantKey()
            : $tenant->getTenantIdentifier();
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
}
