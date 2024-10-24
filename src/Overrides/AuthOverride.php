<?php
declare(strict_types=1);

namespace Sprout\Overrides;

use Illuminate\Auth\AuthManager;
use Sprout\Contracts\ServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;

/**
 * Auth Override
 *
 * This class provides the override/multitenancy extension/features for Laravels
 * auth service.
 *
 * @package Overrides
 */
final class AuthOverride implements ServiceOverride
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
    private function forgetGuards(): void
    {
        if ($this->authManager->hasResolvedGuards()) {
            $this->authManager->forgetGuards();
        }
    }
}
