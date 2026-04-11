<?php
declare(strict_types=1);

namespace Sprout;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Managers\IdentityResolverManager;
use Sprout\Managers\ServiceOverrideManager;
use Sprout\Managers\TenancyManager;
use Sprout\Managers\TenantProviderManager;
use Sprout\Support\ResolutionHook;
use Sprout\Support\SettingsRepository;

/**
 * Sprout
 *
 * This is the core Sprout class.
 */
final class Sprout
{
    /**
     * @var Application
     */
    private Application $app;

    /**
     * @var array<int, Tenancy<Tenant>>
     */
    private array $currentTenancies = [];

    /**
     * @var ServiceOverrideManager
     */
    private ServiceOverrideManager $overrides;

    /**
     * @var TenantProviderManager
     */
    private TenantProviderManager $providers;

    /**
     * @var IdentityResolverManager
     */
    private IdentityResolverManager $resolvers;

    /**
     * @var TenancyManager
     */
    private TenancyManager $tenancies;

    /**
     * @var bool
     */
    private bool $withinContext = false;

    /**
     * @var SettingsRepository
     */
    private SettingsRepository $settings;

    /**
     * @var ResolutionHook|null
     */
    private ?ResolutionHook $currentHook = null;

    /**
     * Create a new instance
     *
     * @param Application                  $app
     * @param SettingsRepository           $settings
     * @param TenantProviderManager|null   $providers
     * @param IdentityResolverManager|null $resolvers
     * @param TenancyManager|null          $tenancies
     * @param ServiceOverrideManager|null  $overrides
     */
    public function __construct(
        Application              $app,
        SettingsRepository       $settings,
        ?TenantProviderManager   $providers = null,
        ?IdentityResolverManager $resolvers = null,
        ?TenancyManager          $tenancies = null,
        ?ServiceOverrideManager  $overrides = null,
    ) {
        $this->app       = $app;
        $this->settings  = $settings;
        $this->providers = $providers ?? new TenantProviderManager($app);
        $this->resolvers = $resolvers ?? new IdentityResolverManager($app);
        $this->tenancies = $tenancies ?? new TenancyManager($app, $this->providers);
        $this->overrides = $overrides ?? (new ServiceOverrideManager($app))->setSprout($this);
    }

    /**
     * Get a config item from the sprout config
     *
     * @param string     $key
     * @param mixed|null $default
     *
     * @return mixed
     *
     * @throws BindingResolutionException
     */
    public function config(string $key, mixed $default = null): mixed
    {
        return $this->app->make('config')->get('sprout.' . $key, $default);
    }

    /**
     * Get a config item from the sprout config
     *
     * @param string     $key
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings->get($key, $default);
    }

    /**
     * Get the Sprout settings repository
     *
     * @return SettingsRepository
     */
    public function settings(): SettingsRepository
    {
        return $this->settings;
    }

    /**
     * Set the current tenancy
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param Tenancy<TenantClass> $tenancy
     *
     * @return void
     */
    public function setCurrentTenancy(Tenancy $tenancy): void
    {
        if ($this->getCurrentTenancy() !== $tenancy) {
            $this->currentTenancies[] = $tenancy;

            // This is a bit of a cheat to enable the refreshing of the Tenancy
            $this->app->forgetExtenders(Tenancy::class);
            $this->app->extend(Tenancy::class, fn (?Tenancy $tenancy) => $tenancy);
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
        return count($this->currentTenancies) > 0;
    }

    /**
     * Get the current tenancy
     *
     * @return Tenancy<Tenant>|null
     */
    public function getCurrentTenancy(): ?Tenancy
    {
        if ($this->hasCurrentTenancy()) {
            return $this->currentTenancies[count($this->currentTenancies) - 1];
        }

        return null;
    }

    /**
     * Get all the current tenancies
     *
     * @return Tenancy<Tenant>[]
     */
    public function getAllCurrentTenancies(): array
    {
        return $this->currentTenancies;
    }

    /**
     * Reset all the current tenancies
     *
     * @return static
     */
    public function resetTenancies(): self
    {
        foreach (array_reverse($this->getAllCurrentTenancies()) as $tenancy) {
            if ($tenancy->check()) {
                $tenancy->setTenant(null);
            }
        }

        $this->currentTenancies = [];

        return $this;
    }

    /**
     * Get the identity resolver manager
     *
     * @return IdentityResolverManager
     */
    public function resolvers(): IdentityResolverManager
    {
        return $this->resolvers;
    }

    /**
     * Get the tenant providers manager
     *
     * @return TenantProviderManager
     */
    public function providers(): TenantProviderManager
    {
        return $this->providers;
    }

    /**
     * Get the tenancy manager
     *
     * @return TenancyManager
     */
    public function tenancies(): TenancyManager
    {
        return $this->tenancies;
    }

    /**
     * Get the service override manager
     *
     * @return ServiceOverrideManager
     */
    public function overrides(): ServiceOverrideManager
    {
        return $this->overrides;
    }

    /**
     * Check if a resolution hook is enabled
     *
     * @param ResolutionHook $hook
     *
     * @return bool
     *
     * @throws BindingResolutionException
     */
    public function supportsHook(ResolutionHook $hook): bool
    {
        /** @var array<ResolutionHook> $enabledHooks */
        $enabledHooks = $this->config('core.hooks', []);

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
    public function markAsOutsideContext(): self
    {
        $this->withinContext = false;

        return $this;
    }

    /**
     * Check if the current request is within multitenanted context
     *
     * @return bool
     */
    public function withinContext(): bool
    {
        return $this->withinContext;
    }

    /**
     * Generate a route for a tenant
     *
     * This method will proxy a call to {@see Contracts\IdentityResolver::route()}
     * to generate a URL for a tenanted route.
     *
     * If no tenancy name is provided, this method will use the current tenancy
     * or the default one.
     *
     * If no resolver name is provided, this method will use the resolver
     * currently linked with the tenancy, or the default one.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param string               $name
     * @param Tenant               $tenant
     * @param string|null          $resolver
     * @param string|null          $tenancy
     * @param array<string, mixed> $parameters
     * @param bool                 $absolute
     *
     * @phpstan-param TenantClass    $tenant
     *
     * @return string
     *
     * @throws Exceptions\MisconfigurationException
     */
    public function route(string $name, Tenant $tenant, ?string $resolver = null, ?string $tenancy = null, array $parameters = [], bool $absolute = true): string
    {
        if ($tenancy === null) {
            $tenancyInstance = $this->getCurrentTenancy() ?? $this->tenancies()->get();
        } else {
            $tenancyInstance = $this->tenancies()->get($tenancy);
        }

        /** @var Tenancy<TenantClass> $tenancyInstance */
        if ($resolver === null) {
            $resolverInstance = $tenancyInstance->resolver() ?? $this->resolvers()->get();
        } else {
            $resolverInstance = $this->resolvers()->get($resolver);
        }

        /** @var Contracts\IdentityResolver $resolverInstance */

        return $resolverInstance->route($name, $tenancyInstance, $tenant, $parameters, $absolute);
    }

    /**
     * Set the current resolution hook
     *
     * @param ResolutionHook|null $hook
     *
     * @return static
     */
    public function setCurrentHook(?ResolutionHook $hook): static
    {
        $this->currentHook = $hook;

        return $this;
    }

    /**
     * Get the current resolution hook
     *
     * @return ResolutionHook|null
     */
    public function getCurrentHook(): ?ResolutionHook
    {
        return $this->currentHook;
    }

    /**
     * Check if the current resolution hook is the provided
     *
     * @param ResolutionHook|null $hook
     *
     * @return bool
     */
    public function isCurrentHook(?ResolutionHook $hook): bool
    {
        return $this->currentHook === $hook;
    }
}
