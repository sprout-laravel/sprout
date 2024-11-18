<?php
declare(strict_types=1);

namespace Sprout\Overrides;

use Illuminate\Cookie\CookieJar;
use Sprout\Concerns\OverridesCookieSettings;
use Sprout\Contracts\DeferrableServiceOverride;
use Sprout\Contracts\ServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;

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
    use OverridesCookieSettings;

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
        $path     = self::$path ?? config('session.path') ?? '/';
        $domain   = self::$domain ?? config('session.domain');
        $secure   = self::$secure ?? config('session.secure', false);
        $sameSite = self::$sameSite ?? config('session.same_site');

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
     */
    public function cleanup(Tenancy $tenancy, Tenant $tenant): void
    {
        // This is intentionally empty
    }
}
