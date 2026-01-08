<?php

namespace Sprout;

use Sprout\Core\Contracts\IdentityResolver;
use Sprout\Core\Contracts\ServiceOverride;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Contracts\TenantProvider;
use Sprout\Core\Support\SettingsRepository;

/**
 * Get the core Sprout class
 *
 * @return \Sprout\Core\Sprout
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
 * @return \Sprout\Core\Support\SettingsRepository
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
 * @return \Sprout\Core\Contracts\IdentityResolver
 *
 * @throws \Sprout\Core\Exceptions\MisconfigurationException
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
 * @return \Sprout\Core\Contracts\Tenancy<*>
 *
 * @throws \Sprout\Core\Exceptions\MisconfigurationException
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
 * @return \Sprout\Core\Contracts\TenantProvider<*>
 *
 * @throws \Sprout\Core\Exceptions\MisconfigurationException
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
 * @return \Sprout\Core\Contracts\ServiceOverride|null
 *
 * @codeCoverageIgnore
 */
function override(string $service): ?ServiceOverride
{
    return sprout()->overrides()->get($service);
}
