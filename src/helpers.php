<?php

namespace Sprout;

use Sprout\Contracts\IdentityResolver;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\TenantProvider;

/**
 * Get the core sprout class
 *
 * @return \Sprout\Sprout
 */
function sprout(): Sprout
{
    return app(Sprout::class);
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
    return sprout()->resolvers()->get($name);
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
    return sprout()->tenancies()->get($name);
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
    return sprout()->providers()->get($name);
}
