<?php
declare(strict_types=1);

namespace Sprout\Managers;

use Illuminate\Contracts\Foundation\Application;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\ServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Events\ServiceOverrideBooted;
use Sprout\Events\ServiceOverrideRegistered;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Exceptions\ServiceOverrideException;
use Sprout\Sprout;
use Sprout\TenancyOptions;

final class ServiceOverrideManager
{
    /**
     * The Laravel application
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected Application $app;

    /**
     * @var array<string, \Sprout\Contracts\ServiceOverride>
     */
    protected array $overrides = [];

    /**
     * @var array<string, class-string<\Sprout\Contracts\ServiceOverride>>
     */
    protected array $overrideClasses = [];

    /**
     * @var list<string>
     */
    protected array $bootableOverrides = [];

    protected bool $overridesBooted = false;

    /**
     * @var array<string, array<class-string<\Sprout\Contracts\ServiceOverride>, string>>
     */
    protected array $setupOverrides = [];

    /**
     * Create a new factory instance
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * @param string $service
     *
     * @return array<string, mixed>|null
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function getServiceConfig(string $service): ?array
    {
        /** @var array<string, mixed>|null $config */
        $config = $this->app->make('config')->get('sprout.overrides.' . $service);

        return $config;
    }

    public function hasOverride(string $service): bool
    {
        return isset($this->overrides[$service]);
    }

    public function haveOverridesBooted(): bool
    {
        return $this->overridesBooted;
    }

    /**
     * Get all services whose overrides have been set up for a tenancy
     *
     * @param \Sprout\Contracts\Tenancy<*> $tenancy
     *
     * @return array<class-string<\Sprout\Contracts\ServiceOverride>, string>
     */
    public function getSetupOverrides(Tenancy $tenancy): array
    {
        return $this->setupOverrides[$tenancy->getName()] ?? [];
    }

    /**
     * Get an override for a service
     *
     * @param string $service
     *
     * @return \Sprout\Contracts\ServiceOverride|null
     */
    public function get(string $service): ?ServiceOverride
    {
        if ($this->hasOverride($service)) {
            return $this->overrides[$service];
        }

        return null;
    }

    /**
     * Register all the configured service overrides
     *
     * @return void
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Exceptions\MisconfigurationException
     * @throws \Sprout\Exceptions\ServiceOverrideException
     */
    public function registerOverrides(): void
    {
        /** @var array<string, array<string, mixed>> $services */
        $services = $this->app->make('config')->get('sprout.overrides', []);

        foreach ($services as $service => $config) {
            $this->register($service);
        }
    }

    /**
     * Boot all the registered service overrides
     *
     * @return void
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     * @throws \Sprout\Exceptions\ServiceOverrideException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function bootOverrides(): void
    {
        foreach ($this->bootableOverrides as $service) {
            $this->boot($service);
        }
    }

    /**
     * Setup all the registered and enabled service overrides
     *
     * @template TenantClass of Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Contracts\Tenant               $tenant
     *
     * @phpstan-param TenantClass                    $tenant
     *
     * @return void
     */
    public function setupOverrides(Tenancy $tenancy, Tenant $tenant): void
    {
        // Get the overrides enabled for this tenancy
        $enabled = TenancyOptions::enabledOverrides($tenancy) ?? [];

        // Loop through all registered overrides
        foreach ($this->overrides as $service => $override) {
            // If the override is enabled
            if (in_array($service, $enabled, true)) {
                // Perform the setup action
                $override->setup($tenancy, $tenant);
                // Keep track of the fact the override was set up
                $this->setupOverrides[$tenancy->getName()][$this->overrideClasses[$service]] = $service;
            }
        }
    }

    /**
     * Clean-up all registered and setup service overrides
     *
     * @template TenantClass of Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Contracts\Tenant               $tenant
     *
     * @phpstan-param TenantClass                    $tenant
     *
     * @return void
     *
     * @throws \Sprout\Exceptions\ServiceOverrideException
     */
    public function cleanupOverrides(Tenancy $tenancy, Tenant $tenant): void
    {
        // Get the overrides enabled for this tenancy
        $enabled        = TenancyOptions::enabledOverrides($tenancy) ?? [];
        $setupOverrides = $this->getSetupOverrides($tenancy);

        // Loop through all registered overrides
        foreach ($setupOverrides as $driver => $service) {
            // If the override is enabled
            if (in_array($service, $enabled, true)) {
                // Perform the setup action
                $this->overrides[$service]->cleanup($tenancy, $tenant);

                unset($this->setupOverrides[$tenancy->getName()][$driver]);
            } else {
                throw ServiceOverrideException::setupButNotEnabled($service, $tenancy->getName());
            }
        }
    }

    /**
     * Register a service override
     *
     * @param string $service
     *
     * @return static
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Exceptions\MisconfigurationException
     * @throws \Sprout\Exceptions\ServiceOverrideException
     */
    protected function register(string $service): self
    {
        // If the override already exists, we'll error out, because it should
        // we'd just load the same config again
        if ($this->hasOverride($service)) {
            return $this;
        }

        // Get the config for this service override
        $config = $this->getServiceConfig($service);

        // If there isn't config, there's an issue
        if ($config === null) {
            throw MisconfigurationException::notFound('service override', $service);
        }

        // If there is config, but it's missing a driver, that's also an issue
        if (! isset($config['driver'])) {
            throw MisconfigurationException::missingConfig('driver', 'service override', $service);
        }

        /** @var array{driver:string} $config */

        // If there is a driver, but it doesn't implement the correct interface,
        // that's also an issue
        if (! is_subclass_of($config['driver'], ServiceOverride::class)) {
            throw MisconfigurationException::invalidConfig('driver', 'service override', $service);
        }

        /** @var class-string<\Sprout\Contracts\ServiceOverride> $driver */
        $driver = $config['driver'];

        // Create a new instance of the service override with the service name
        // and config, as we know the constructor signature
        $override = $this->app->make($driver, compact('service', 'config'));

        // Store the override
        $this->overrides[$service] = $override;

        // And map it to its driver, rather than the final class
        $this->overrideClasses[$service] = $driver;

        ServiceOverrideRegistered::dispatch($service, $override);

        // If the service override is bootable, we'll keep track of that too
        if ($override instanceof BootableServiceOverride) {
            $this->bootableOverrides[] = $service;

            // If the overrides have already booted, we'll boot it
            if ($this->haveOverridesBooted()) {
                $this->boot($service);
            }
        }

        return $this;
    }

    /**
     * Boot a service override
     *
     * @param string $service
     *
     * @return static
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Exceptions\MisconfigurationException
     * @throws \Sprout\Exceptions\ServiceOverrideException
     */
    protected function boot(string $service): self
    {
        // If the override doesn't exist, that's an issue
        if ($this->hasOverride($service)) {
            throw MisconfigurationException::notFound('service override', $service);
        }

        $override = $this->overrides[$service];

        // If the override exists, but isn't bootable, that's also an issue
        if (! ($override instanceof BootableServiceOverride)) {
            throw ServiceOverrideException::notBootable($service);
        }

        $override->boot($this->app, $this->app->make(Sprout::class));

        ServiceOverrideBooted::dispatch($service, $override);

        return $this;
    }
}
