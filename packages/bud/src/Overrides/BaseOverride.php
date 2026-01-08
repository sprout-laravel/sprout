<?php
declare(strict_types=1);

namespace Sprout\Bud\Overrides;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Sprout\Bud\Bud;
use Sprout\Core\Contracts\BootableServiceOverride;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Contracts\Tenant;
use Sprout\Core\Overrides\BaseOverride as SproutBaseOverride;
use Sprout\Core\Sprout;

/**
 * @template OverrideService of object
 */
abstract class BaseOverride extends SproutBaseOverride implements BootableServiceOverride
{
    /**
     * @var array<string>
     */
    protected array $overrides = [];

    protected bool $tracksOverrides = true;

    /**
     * Get the name of the service being overridden.
     *
     * @return string
     */
    abstract protected function serviceName(): string;

    /**
     * Get the overridden driver names.
     *
     * @return string[]
     */
    public function getOverrides(): array
    {
        return $this->overrides;
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
     */
    public function boot(Application $app, Sprout $sprout): void
    {
        $tracker = $this->tracksOverrides
            ? fn (string $name) => $this->overrides[] = $name
            : static fn () => null;

        if ($app->resolved($this->serviceName())) {
            /** @var OverrideService $service */
            $service = $app->make($this->serviceName());
            $this->addDriver($service, $app->make(Bud::class), $sprout, $tracker);
        } else {
            $app->afterResolving(
                $this->serviceName(),
                function (object $service, Application $app) use ($sprout, $tracker) {
                    /** @var OverrideService $service */
                    $this->addDriver($service, $app->make(Bud::class), $sprout, $tracker);
                }
            );
        }
    }

    /**
     * Add a driver to the service.
     *
     * @param object                  $service
     * @param \Sprout\Bud\Bud    $bud
     * @param \Sprout\Core\Sprout     $sprout
     * @param \Closure                $tracker
     *
     * @phpstan-param OverrideService $service
     *
     * @return void
     */
    abstract protected function addDriver(object $service, Bud $bud, Sprout $sprout, Closure $tracker): void;

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
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function cleanup(Tenancy $tenancy, Tenant $tenant): void
    {
        if ($this->tracksOverrides === false) {
            return; // @codeCoverageIgnore
        }

        // If the service was resolved, we need to clear it up.
        if ($this->getApp()->resolved($this->serviceName())) {
            $overrides = $this->getOverrides();

            if (! empty($overrides)) {
                /** @var OverrideService $service */
                $service = $this->getApp()->make($this->serviceName());

                foreach ($overrides as $override) {
                    $this->cleanupOverride($service, $override);
                }

                $this->overrides = [];
            }
        }
    }

    /**
     * Clean-up an overridden service.
     *
     * @param object                  $service
     * @param string                  $name
     *
     * @phpstan-param OverrideService $service
     *
     * @return void
     */
    abstract protected function cleanupOverride(object $service, string $name): void;
}
