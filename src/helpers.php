<?php

namespace Sprout;

use Sprout\Contracts\IdentityResolver;
use Sprout\Contracts\ServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\TenantProvider;
use Sprout\Support\SettingsRepository;

/**
 * Get the core Sprout class
 *
 * @return Sprout
 *
 * @codeCoverageIgnore
 */
function sprout(): Sprout
{
    return app(Sprout::class);
}

/**
 * Get the Sprout settings repository
 *
 * @return SettingsRepository
 *
 * @codeCoverageIgnore
 */
function settings(): SettingsRepository
{
    return sprout()->settings();
}

/**
 * Get an identity resolver
 *
 * @param string|null $name
 *
 * @return IdentityResolver
 *
 * @throws Exceptions\MisconfigurationException
 *
 * @codeCoverageIgnore
 */
function resolver(?string $name = null): IdentityResolver
{
    return sprout()->resolvers()->get($name);
}

/**
 * Get a tenancy
 *
 * @param string|null $name
 *
 * @return \Sprout\Contracts\Tenancy<*>
 *
 * @throws Exceptions\MisconfigurationException
 *
 * @codeCoverageIgnore
 */
function tenancy(?string $name = null): Tenancy
{
    return sprout()->tenancies()->get($name);
}

/**
 * Get a tenant provider
 *
 * @param string|null $name
 *
 * @return \Sprout\Contracts\TenantProvider<*>
 *
 * @throws Exceptions\MisconfigurationException
 *
 * @codeCoverageIgnore
 */
function provider(?string $name = null): TenantProvider
{
    return sprout()->providers()->get($name);
}

/**
 * Get a service override
 *
 * @param string $service
 *
 * @return ServiceOverride|null
 *
 * @codeCoverageIgnore
 */
function override(string $service): ?ServiceOverride
{
    return sprout()->overrides()->get($service);
}
