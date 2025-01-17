<?php
declare(strict_types=1);

namespace Sprout\Overrides;

use Illuminate\Cookie\CookieJar;
use Sprout\Contracts\DeferrableServiceOverride;
use Sprout\Contracts\ServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use function Sprout\settings;

/**
 * Cookie Override
 *
 * This class provides the override/multitenancy extension/features for Laravels
 * cookie service.
 *
 * @package Overrides
 */
final class CookieOverride implements ServiceOverride, DeferrableServiceOverride
{
    /**
     * Get the service to watch for before overriding
     *
     * @return string
     */
    public static function service(): string
    {
        return 'cookie';
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
        // Collect the values
        $path     = settings()->getUrlPath(config('session.path') ?? '/');             // @phpstan-ignore-line
        $domain   = settings()->getUrlDomain(config('session.domain'));                // @phpstan-ignore-line
        $secure   = settings()->shouldCookieBeSecure(config('session.secure', false)); // @phpstan-ignore-line
        $sameSite = settings()->getCookieSameSite(config('session.same_site'));        // @phpstan-ignore-line

        /**
         * This is here to make PHPStan quiet down
         *
         * @var string      $path
         * @var string|null $domain
         * @var bool|null   $secure
         * @var string|null $sameSite
         */

        // Set the default values on the cookiejar
        app(CookieJar::class)->setDefaultPathAndDomain($path, $domain, $secure, $sameSite);
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
     *
     * @codeCoverageIgnore
     */
    public function cleanup(Tenancy $tenancy, Tenant $tenant): void
    {
        // This is intentionally empty
    }
}
