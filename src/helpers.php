<?php

namespace Sprout;

use Sprout\Contracts\IdentityResolver;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\TenantProvider;
use Sprout\Managers\IdentityResolverManager;
use Sprout\Managers\TenancyManager;
use Sprout\Managers\TenantProviderManager;
use Sprout\Support\SettingsRepository;

/**
 * Get the core Sprout class
 *
 * @return \Sprout\Sprout
 */
function sprout(): Sprout
{
    return app(Sprout::class);
}

/**
 * Get the Sprout settings repository
 *
 * @return \Sprout\Support\SettingsRepository
 */
function settings(): SettingsRepository
{
    return app(SettingsRepository::class);
}

/**
 * Get an identity resolver
 *
 * @param string|null $name
 *
 * @return \Sprout\Contracts\IdentityResolver
 *
 * @throws \Illuminate\Contracts\Container\BindingResolutionException
 * @throws \Sprout\Exceptions\MisconfigurationException
 */
function resolver(?string $name = null): IdentityResolver
{
    return app(IdentityResolverManager::class)->get($name);
}

/**
 * Get a tenancy
 *
 * @param string|null $name
 *
 * @return \Sprout\Contracts\Tenancy<*>
 *
 * @throws \Illuminate\Contracts\Container\BindingResolutionException
 * @throws \Sprout\Exceptions\MisconfigurationException
 */
function tenancy(?string $name = null): Tenancy
{
    return app(TenancyManager::class)->get($name);
}

/**
 * Get a tenant provider
 *
 * @param string|null $name
 *
 * @return \Sprout\Contracts\TenantProvider<*>
 *
 * @throws \Illuminate\Contracts\Container\BindingResolutionException
 * @throws \Sprout\Exceptions\MisconfigurationException
 */
function provider(?string $name = null): TenantProvider
{
    return app(TenantProviderManager::class)->get($name);
}
