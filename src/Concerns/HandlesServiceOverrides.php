<?php
declare(strict_types=1);

namespace Sprout\Concerns;

use InvalidArgumentException;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\DeferrableServiceOverride;
use Sprout\Contracts\ServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Overrides\StorageOverride;

trait HandlesServiceOverrides
{
    /**
     * @var array<class-string<\Sprout\Contracts\ServiceOverride>>
     */
    private array $registeredOverrides = [];

    /**
     * @var array<class-string<\Sprout\Contracts\ServiceOverride>, \Sprout\Contracts\ServiceOverride>
     */
    private array $overrides = [];

    /**
     * @var array<class-string<\Sprout\Contracts\ServiceOverride>, string|class-string>
     */
    private array $deferredOverrides = [];

    /**
     * @var array<class-string<\Sprout\Contracts\ServiceOverride>, BootableServiceOverride>
     */
    private array $bootableOverrides = [];

    /**
     * @var array<class-string<\Sprout\Contracts\ServiceOverride>, bool>
     */
    private array $bootedOverrides = [];

    /**
     * @var array<string, array<class-string<\Sprout\Contracts\ServiceOverride>>
     */
    private array $setupOverrides = [];

    /**
     * @var bool
     */
    private bool $hasBooted = false;

    /**
     * Register a service override
     *
     * @param class-string<\Sprout\Contracts\ServiceOverride> $overrideClass
     *
     * @return static
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function registerOverride(string $overrideClass): static
    {
        if (! is_subclass_of($overrideClass, ServiceOverride::class)) {
            throw new InvalidArgumentException('Provided service override [' . $overrideClass . '] does not implement ' . ServiceOverride::class);
        }

        // Flag the service override as being registered
        $this->registeredOverrides[] = $overrideClass;

        if (is_subclass_of($overrideClass, DeferrableServiceOverride::class)) {
            $this->registerDeferrableOverride($overrideClass);
        } else {
            $this->processOverride($overrideClass);
        }

        return $this;
    }

    /**
     * Process the registration of a service override
     *
     * This method is an abstraction of the service override registration
     * processing, which exists entirely to make deferrable overrides easier.
     *
     * @param class-string<\Sprout\Contracts\ServiceOverride> $overrideClass
     *
     * @return static
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function processOverride(string $overrideClass): static
    {
        // Create a new instance of the override
        $override = $this->app->make($overrideClass);

        // Register the instance
        $this->overrides[$overrideClass] = $override;

        // The override is bootable
        if ($override instanceof BootableServiceOverride) {
            // So register it as one
            $this->bootableOverrides[$overrideClass] = $override;
            $this->bootedOverrides[$overrideClass]   = false;

            // If the boot phase has already happened, we'll boot it now
            if ($this->haveOverridesBooted()) {
                $this->bootOverride($overrideClass);
            }
        }

        return $this;
    }

    /**
     * Register a deferrable service override
     *
     * @param class-string<\Sprout\Contracts\DeferrableServiceOverride> $overrideClass
     *
     * @return static
     */
    protected function registerDeferrableOverride(string $overrideClass): static
    {
        // Register the deferred override and its service
        $this->deferredOverrides[$overrideClass] = $overrideClass::service();

        $this->app->afterResolving($overrideClass::service(), function () use ($overrideClass) {
            $this->processOverride($overrideClass);

            // Get the current tenancy
            $tenancy = $this->getCurrentTenancy();

            // If there's a current tenancy WITH a tenant, we can set up the
            // override
            if ($tenancy !== null && $tenancy->check()) {
                $this->setupOverride($overrideClass, $tenancy, $tenancy->tenant());
            }
        });

        return $this;
    }

    /**
     * Check if a service override is bootable
     *
     * @param class-string<\Sprout\Contracts\ServiceOverride> $overrideClass
     *
     * @return bool
     */
    public function isBootableOverride(string $overrideClass): bool
    {
        return isset($this->bootableOverrides[$overrideClass]);
    }

    /**
     * Check if a service override has been booted
     *
     * This method returns true if the service override has been booted, or
     * false if either it hasn't, or it isn't bootable.
     *
     * @param class-string<\Sprout\Contracts\ServiceOverride> $overrideClass
     *
     * @return bool
     */
    public function hasBootedOverride(string $overrideClass): bool
    {
        return $this->bootedOverrides[$overrideClass] ?? false;
    }

    /**
     * Check if the boot phase has already happened
     *
     * @return bool
     */
    public function haveOverridesBooted(): bool
    {
        return $this->hasBooted;
    }

    /**
     * Boot all bootable overrides
     *
     * @return void
     */
    public function bootOverrides(): void
    {
        // If the boot phase for the override has already happened, skip it
        if ($this->haveOverridesBooted()) {
            return;
        }

        foreach ($this->bootableOverrides as $overrideClass => $override) {
            // It's possible this is being called a second time, so we don't
            // want to do it again
            if (! $this->hasBootedOverride($overrideClass)) {
                // Boot the override
                $this->bootOverride($overrideClass);
            }
        }

        // Mark the override boot phase as having completed
        $this->hasBooted = true;
    }

    /**
     * Boot a service override
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param class-string<\Sprout\Contracts\BootableServiceOverride> $overrideClass
     *
     * @phpstan-param TenantClass                                     $tenant
     *
     * @return void
     */
    protected function bootOverride(string $overrideClass): void
    {
        $this->overrides[$overrideClass]->boot($this->app, $this);
        $this->bootedOverrides[$overrideClass] = true;
    }

    /**
     * Check if a service override has been set up
     *
     * @param \Sprout\Contracts\Tenancy<*>                       $tenancy
     * @param class-string<\Sprout\Contracts\ServiceOverride> $overrideClass
     *
     * @return bool
     */
    public function hasSetupOverride(Tenancy $tenancy, string $overrideClass): bool
    {
        return $this->setupOverrides[$tenancy->getName()][$overrideClass] ?? false;
    }

    /**
     * Set-up all available service overrides
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
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
        foreach ($this->overrides as $overrideClass => $override) {
            if (! $this->hasSetupOverride($tenancy, $overrideClass)) {
                $this->setupOverride($overrideClass, $tenancy, $tenant);
            }
        }
    }

    /**
     * Set up a service override
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param class-string<\Sprout\Contracts\ServiceOverride> $overrideClass
     * @param \Sprout\Contracts\Tenancy<TenantClass>          $tenancy
     * @param \Sprout\Contracts\Tenant                        $tenant
     *
     * @phpstan-param TenantClass                             $tenant
     *
     * @return void
     */
    protected function setupOverride(string $overrideClass, Tenancy $tenancy, Tenant $tenant): void
    {
        $this->overrides[$overrideClass]->setup($tenancy, $tenant);
        $this->setupOverrides[$tenancy->getName()][$overrideClass] = true;
    }

    /**
     * Clean-up all service overrides
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Contracts\Tenant               $tenant
     *
     * @phpstan-param TenantClass                    $tenant
     *
     * @return void
     */
    public function cleanupOverrides(Tenancy $tenancy, Tenant $tenant): void
    {
        $overrides = $this->setupOverrides[$tenancy->getName()] ?? [];

        foreach ($overrides as $overrideClass => $status) {
            if ($status === true) {
                $this->cleanupOverride($overrideClass, $tenancy, $tenant);
            }
        }
    }

    /**
     * Clean-up a service override
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param class-string<\Sprout\Contracts\ServiceOverride> $overrideClass
     * @param \Sprout\Contracts\Tenancy<TenantClass>          $tenancy
     * @param \Sprout\Contracts\Tenant                        $tenant
     *
     * @phpstan-param TenantClass                             $tenant
     *
     * @return void
     */
    protected function cleanupOverride(string $overrideClass, Tenancy $tenancy, Tenant $tenant): void
    {
        $this->overrides[$overrideClass]->cleanup($tenancy, $tenant);
        unset($this->setupOverrides[$tenancy->getName()][$overrideClass]);
    }

    /**
     * Get all service overrides
     *
     * @return array<class-string<\Sprout\Contracts\ServiceOverride>, \Sprout\Contracts\ServiceOverride>
     */
    public function getOverrides(): array
    {
        return $this->overrides;
    }

    /**
     * Get all registered service overrides
     *
     * @return array<class-string<\Sprout\Contracts\ServiceOverride>>
     */
    public function getRegisteredOverrides(): array
    {
        return $this->registeredOverrides;
    }

    /**
     * Check if a service override is present
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
     * Check if a service override has been registered
     *
     * @param string $class
     *
     * @return bool
     */
    public function hasRegisteredOverride(string $class): bool
    {
        return in_array($class, $this->registeredOverrides, true);
    }

    /**
     * Get all service overrides for a tenancy
     *
     * @param \Sprout\Contracts\Tenancy|null $tenancy
     *
     * @return \Sprout\Contracts\ServiceOverride[]|void
     */
    public function getCurrentOverrides(?Tenancy $tenancy = null)
    {
        $tenancy ??= $this->getCurrentTenancy();

        if ($tenancy !== null) {
            return array_filter(
                $this->overrides,
                function (string $overrideClass) use ($tenancy) {
                    return $this->hasSetupOverride($tenancy, $overrideClass);
                },
                ARRAY_FILTER_USE_KEY
            );
        }
    }
}
