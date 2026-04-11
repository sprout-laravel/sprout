<?php
declare(strict_types=1);

namespace Sprout\Overrides\Auth;

use Closure;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Foundation\Application;
use LogicException;
use RuntimeException;
use Sprout\Bud;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Overrides\BudBaseOverride;
use Sprout\Sprout;

/**
 * @extends BudBaseOverride<AuthManager>
 */
final class BudAuthProviderOverride extends BudBaseOverride
{
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
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param Tenancy<TenantClass> $tenancy
     * @param Tenant               $tenant
     *
     * @phpstan-param TenantClass                         $tenant
     *
     * @return void
     *
     * @throws BindingResolutionException
     */
    public function cleanup(Tenancy $tenancy, Tenant $tenant): void
    {
        if ($this->getApp()->resolved($this->serviceName())) {
            /** @var AuthManager $service */
            $service = $this->getApp()->make('auth');

            // Providers cannot be cleaned up in the same way as other services,
            // so we just forget ALL guards, because one or two may have been
            // using the custom provider.
            $service->forgetGuards();

            $this->overrides = [];
        }
    }

    /**
     * Get the name of the service being overridden.
     *
     * @return string
     */
    protected function serviceName(): string
    {
        return 'auth';
    }

    /**
     * Add a driver to the service.
     *
     * @param object  $service
     * @param Bud     $bud
     * @param Sprout  $sprout
     * @param Closure $tracker
     *
     * @phpstan-param AuthManager $service
     *
     * @return void
     */
    protected function addDriver(object $service, Bud $bud, Sprout $sprout, Closure $tracker): void
    {
        if (! $service instanceof BudAuthManager) {
            throw new LogicException('Cannot override auth providers without the Bud auth manager override');
        }

        $service->provider('sprout:bud', function (Application $app, array $config) use ($service, $bud, $sprout, $tracker) {
            /**
             * @var array<string, mixed>&array{budStore?:string|null,driver:string,provider?:mixed} $config
             */
            if (! isset($config['provider']) || ! is_string($config['provider']) || $config['provider'] === '') {
                throw new RuntimeException('Cannot create an auth provider using bud without a name'); // @codeCoverageIgnore
            }

            $tracker($config['provider']);

            return (new BudAuthProviderCreator($service, $bud, $sprout, $config['provider'], $config))();
        });
    }

    /**
     * Clean-up an overridden service.
     *
     * @param object $service
     * @param string $name
     *
     * @phpstan-param AuthManager $service
     *
     * @return void
     *
     * @codeCoverageIgnore
     */
    protected function cleanupOverride(object $service, string $name): void
    {
    }
}
