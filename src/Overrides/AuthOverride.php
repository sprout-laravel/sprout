<?php
declare(strict_types=1);

namespace Sprout\Overrides;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Foundation\Application;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\DeferrableServiceOverride;
use Sprout\Contracts\ServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Overrides\Auth\TenantAwarePasswordBrokerManager;
use Sprout\Sprout;

/**
 * Auth Override
 *
 * This class provides the override/multitenancy extension/features for Laravels
 * auth service.
 *
 * @package Overrides
 */
final class AuthOverride implements ServiceOverride, BootableServiceOverride, DeferrableServiceOverride
{
    /**
     * @var \Illuminate\Auth\AuthManager
     */
    private AuthManager $authManager;

    /**
     * Create a new instance
     *
     * @param \Illuminate\Auth\AuthManager $authManager
     */
    public function __construct(AuthManager $authManager)
    {
        $this->authManager = $authManager;
    }

    /**
     * Get the service to watch for before overriding
     *
     * @return string
     */
    public static function service(): string
    {
        return AuthManager::class;
    }

    /**
     * Boot a service override
     *
     * This method should perform any initial steps required for the service
     * override that take place during the booting of the framework.
     *
     * @param \Illuminate\Contracts\Foundation\Application&\Illuminate\Foundation\Application $app
     * @param \Sprout\Sprout                                                                  $sprout
     *
     * @return void
     */
    public function boot(Application $app, Sprout $sprout): void
    {
        // Although this isn't strictly necessary, this is here to tidy up
        // the list of deferred services, just in case there's some weird gotcha
        // somewhere that causes the provider to be loaded anyway.
        // We'll remove the two services we're about to bind against.
        $app->removeDeferredServices(['auth.password', 'auth.password.broker']);

        // This is the actual thing we need.
        $app->singleton('auth.password', function ($app) {
            return new TenantAwarePasswordBrokerManager($app);
        });

        // I would ideally also like to mark the password reset service provider
        // as loaded here, but that method is protected.
    }

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
        $this->flushPasswordBrokers();
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
        $this->flushPasswordBrokers();
    }

    /**
     * Forget all resolved guards
     *
     * @return void
     */
    private function forgetGuards(): void
    {
        if ($this->authManager->hasResolvedGuards()) {
            $this->authManager->forgetGuards();
        }
    }

    /**
     * Flush all password brokers
     *
     * @return void
     */
    private function flushPasswordBrokers(): void
    {
        /** @var \Illuminate\Auth\Passwords\PasswordBrokerManager $passwordBroker */
        $passwordBroker = app('auth.password');

        // The flush method only exists on our custom implementation
        if ($passwordBroker instanceof TenantAwarePasswordBrokerManager) {
            $passwordBroker->flush();
        }
    }
}
