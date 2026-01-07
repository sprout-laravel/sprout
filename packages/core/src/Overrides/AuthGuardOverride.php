<?php
declare(strict_types=1);

namespace Sprout\Overrides;

use Illuminate\Auth\AuthManager;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;

/**
 * Auth Guard Override
 *
 * This class provides the override/multitenancy extension/features for Laravels
 * auth service.
 *
 * This override cannot be deferred as it works with several services, and it's
 * possible that one could be loaded without the other.
 * Instead, the code that deals with the various services is wrapped in a
 * condition to only run if the service itself has been loaded.
 *
 * @package Overrides
 */
final class AuthGuardOverride extends BaseOverride
{
    /**
     * Set up the service override
     *
     * This method should perform any necessary setup actions for the service
     * override.
     * It is called when a new tenant is marked as the current tenant.
     *
     * @param \Sprout\Contracts\Tenancy<*> $tenancy
     * @param \Sprout\Contracts\Tenant $tenant
     *
     * @return void
     */
    public function setup(Tenancy $tenancy, Tenant $tenant): void
    {
        $this->forgetGuards();
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
     * @param \Sprout\Contracts\Tenancy<*> $tenancy
     * @param \Sprout\Contracts\Tenant $tenant
     *
     * @return void
     */
    public function cleanup(Tenancy $tenancy, Tenant $tenant): void
    {
        $this->forgetGuards();
    }

    /**
     * Forget all resolved guards
     *
     * @return void
     */
    protected function forgetGuards(): void
    {
        // Since this class isn't deferred because it has to rely on
        // multiple services, we only want to actually run this code if
        // the auth manager has been resolved.
        if ($this->getApp()->resolved('auth')) {
            /** @var \Illuminate\Auth\AuthManager $authManager */
            $authManager = $this->getApp()->make(AuthManager::class);

            if ($authManager->hasResolvedGuards()) {
                $authManager->forgetGuards();
            }
        }
    }
}
