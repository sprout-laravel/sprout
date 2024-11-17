<?php
declare(strict_types=1);

namespace Sprout;

use Illuminate\Contracts\Foundation\Application;
use Sprout\Contracts\ServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Managers\IdentityResolverManager;
use Sprout\Managers\ProviderManager;
use Sprout\Managers\TenancyManager;
use Sprout\Support\ResolutionHook;

/**
 * Sprout
 *
 * This is the core Sprout class.
 *
 * @package Core
 */
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

    /**
     * @var bool
     */
    private bool $withinContext = false;

    /**
     * Create a new instance
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get a config item from the sprout config
     *
     * @param string     $key
     * @param mixed|null $default
     *
     * @return mixed
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function config(string $key, mixed $default = null): mixed
    {
        return $this->app->make('config')->get('sprout.' . $key, $default);
    }

    /**
     * Set the current tenancy
     *
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

        $this->markAsInContext();
    }

    /**
     * Check if there is a current tenancy
     *
     * @return bool
     */
    public function hasCurrentTenancy(): bool
    {
        return count($this->tenancies) > 0;
    }

    /**
     * Get the current tenancy
     *
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
     * Get all the current tenancies
     *
     * @return \Sprout\Contracts\Tenancy<\Sprout\Contracts\Tenant>[]
     */
    public function getAllCurrentTenancies(): array
    {
        return $this->tenancies;
    }

    /**
     * Should Sprout listen for the routing event
     *
     * @return bool
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function shouldListenForRouting(): bool
    {
        return (bool)$this->config('listen_for_routing', true);
    }

    /**
     * Get the identity resolver manager
     *
     * @return \Sprout\Managers\IdentityResolverManager
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function resolvers(): IdentityResolverManager
    {
        return $this->app->make(IdentityResolverManager::class);
    }

    /**
     * Get the tenant providers manager
     *
     * @return \Sprout\Managers\ProviderManager
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function providers(): ProviderManager
    {
        return $this->app->make(ProviderManager::class);
    }

    /**
     * Get the tenancy manager
     *
     * @return \Sprout\Managers\TenancyManager
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function tenancies(): TenancyManager
    {
        return $this->app->make(TenancyManager::class);
    }

    /**
     * Is an override enabled
     *
     * @param string $class
     *
     * @return bool
     */
    public function hasOverride(string $class): bool
    {
        return isset($this->overrides[$class]);
    }

    /**
     * Add an override
     *
     * @param \Sprout\Contracts\ServiceOverride $override
     *
     * @return $this
     */
    public function addOverride(ServiceOverride $override): self
    {
        $this->overrides[$override::class] = $override;

        return $this;
    }

    /**
     * Get all overrides
     *
     * @return array<class-string<\Sprout\Contracts\ServiceOverride>, \Sprout\Contracts\ServiceOverride>
     */
    public function getOverrides(): array
    {
        return $this->overrides;
    }

    /**
     * Check if a resolution hook is enabled
     *
     * @param \Sprout\Support\ResolutionHook $hook
     *
     * @return bool
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function supportsHook(ResolutionHook $hook): bool
    {
        /** @var array<ResolutionHook> $enabledHooks */
        $enabledHooks = $this->config('hooks', []);

        return in_array($hook, $enabledHooks, true);
    }

    /**
     * Flag the current request as being within multitenanted context
     *
     * @return static
     */
    public function markAsInContext(): self
    {
        $this->withinContext = true;

        return $this;
    }

    /**
     * Flag the current request as being outside multitenanted context
     *
     * @return static
     */
    public function maskAsOutsideContext(): self
    {
        $this->withinContext = false;

        return $this;
    }

    /**
     * Check if the current request is within multitenanted context
     *
     * @return bool
     */
    public function withinContext():bool
    {
        return $this->withinContext;
    }
}
