<?php
declare(strict_types=1);

namespace Sprout\Core\Overrides;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Sprout\Core\Contracts\BootableServiceOverride;
use Sprout\Core\Contracts\ServiceOverride;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Contracts\Tenant;
use Sprout\Core\Exceptions\MisconfigurationException;
use Sprout\Core\Sprout;

/**
 * @phpstan-type OverrideClass = \Sprout\Core\Contracts\ServiceOverride|(\Sprout\Core\Contracts\ServiceOverride&\Sprout\Core\Contracts\BootableServiceOverride)
 */
final class StackedOverride extends BaseOverride implements BootableServiceOverride
{
    /**
     * @var array<int, class-string<OverrideClass>|array<string, mixed>|OverrideClass>
     */
    private array $overridesClasses;

    /**
     * @var array<class-string<OverrideClass>, OverrideClass>
     */
    private array $overrides = [];

    public function __construct(string $service, array $config)
    {
        parent::__construct($service, $config);

        if (empty($config['overrides'])) {
            throw MisconfigurationException::missingConfig('overrides', 'stacked service', $service);
        }

        /** @var array{overrides:array<int, class-string<OverrideClass>|array<string, mixed>|OverrideClass>} $config */

        $this->overridesClasses = $config['overrides'];
    }

    /**
     * Create the overrides from the config
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     * @param \Sprout\Core\Sprout                          $sprout
     *
     * @return void
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Core\Exceptions\MisconfigurationException
     */
    protected function createOverrides(Application $app, Sprout $sprout): void
    {
        // If the overrides already exist, we don't need to create them
        if (! empty($this->overrides)) {
            return; // @codeCoverageIgnore
        }

        foreach ($this->overridesClasses as $value) {
            $override = $overrideClass = null;

            if ($value instanceof ServiceOverride) {
                // We might just have an instance of a service override,
                // which is totally possible.
                // Honest!
                $override      = $value;
                $overrideClass = $override::class;
            } else if (is_string($value)) {
                // We're either looking at a class name with no config
                /** @phpstan-ignore function.alreadyNarrowedType */
                if (! is_subclass_of($value, ServiceOverride::class)) {
                    throw MisconfigurationException::invalidConfig('overrides.*.driver', 'service override', $this->service, $value);
                }

                $override = $app->make($value, [
                    'service' => $this->service,
                    'config'  => [],
                ]);

                $overrideClass = $value;
                /** @phpstan-ignore function.alreadyNarrowedType */
            } else if (is_array($value)) {
                /** @var array{driver?: scalar} $value */

                if (! isset($value['driver'])) {
                    throw MisconfigurationException::missingConfig('overrides.*.driver', 'service override', $this->service);
                }

                // Or config keyed by the class name
                if (! is_string($value['driver']) || ! is_subclass_of($value['driver'], ServiceOverride::class)) {
                    throw MisconfigurationException::invalidConfig('overrides.*.driver', 'service override', $this->service, $value['driver']);
                }

                $override = $app->make($value['driver'], [
                    'service' => $this->service,
                    'config'  => Arr::except($value, 'driver'),
                ]);

                $overrideClass = $value['driver'];
            }

            if ($override === null) {
                throw MisconfigurationException::invalidConfig('overrides', 'service override', $this->service);
            }

            /** @var OverrideClass $override */

            if (method_exists($override, 'setApp')) {
                $override->setApp($app);
            }

            if (method_exists($override, 'setSprout')) {
                $override->setSprout($sprout);
            }

            $this->overrides[$overrideClass] = $override;
        }
    }

    /**
     * Get the created overrides
     *
     * @return array<class-string<OverrideClass>, OverrideClass>
     */
    public function getOverrides(): array
    {
        return $this->overrides;
    }

    /**
     * @template ServiceOverrideClass of OverrideClass
     *
     * @param class-string<ServiceOverrideClass> $class
     *
     * @return \Sprout\Core\Contracts\ServiceOverride|null
     *
     * @phpstan-require-implements ServiceOverrideClass|null
     */
    public function getOverride(string $class): ?ServiceOverride
    {
        return $this->overrides[$class] ?? null;
    }

    /**
     * Boot a service override
     *
     * This method should perform any initial steps required for the service
     * override that take place during the booting of the framework.
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     * @param \Sprout\Core\Sprout                          $sprout
     *
     * @return void
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Core\Exceptions\MisconfigurationException
     */
    public function boot(Application $app, Sprout $sprout): void
    {
        $this->createOverrides($app, $sprout);

        foreach ($this->overrides as $override) {
            if ($override instanceof BootableServiceOverride) {
                $override->boot($app, $sprout);
            }
        }
    }

    /**
     * Set up the service override
     *
     * This method should perform any necessary setup actions for the service
     * override.
     * It is called when a new tenant is marked as the current tenant.
     *
     * @template TenantClass of \Sprout\Core\Contracts\Tenant
     *
     * @param \Sprout\Core\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Core\Contracts\Tenant               $tenant
     *
     * @phpstan-param TenantClass                         $tenant
     *
     * @return void
     */
    public function setup(Tenancy $tenancy, Tenant $tenant): void
    {
        foreach ($this->overrides as $override) {
            $override->setup($tenancy, $tenant);
        }
    }

    /**
     * Clean up the service override
     *
     * This method should perform any necessary setup actions for the service
     * override.
     * It is called when the current tenant is unset, either to be replaced
     * by another tenant, or none.
     *
     * It will be called before {@see self::setup()}, but only if the previous
     * tenant was not null.
     *
     * @template TenantClass of \Sprout\Core\Contracts\Tenant
     *
     * @param \Sprout\Core\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Core\Contracts\Tenant               $tenant
     *
     * @phpstan-param TenantClass                         $tenant
     *
     * @return void
     */
    public function cleanup(Tenancy $tenancy, Tenant $tenant): void
    {
        foreach ($this->overrides as $override) {
            $override->cleanup($tenancy, $tenant);
        }
    }
}
