<?php
declare(strict_types=1);

namespace Sprout\Overrides;

use Illuminate\Cookie\CookieJar;
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
final class CookieOverride extends BaseOverride
{
    /**
     * Set up the service override
     *
     * This method should perform any necessary setup actions for the service
     * override.
     * It is called when a new tenant is marked as the current tenant.
     *
     * @param \Sprout\Contracts\Tenancy<*> $tenancy
     * @param \Sprout\Contracts\Tenant     $tenant
     *
     * @return void
     */
    public function setup(Tenancy $tenancy, Tenant $tenant): void
    {
        // Collect the values
        $path     = $this->getSprout()->settings()->getUrlPath(config('session.path') ?? '/');             // @phpstan-ignore-line
        $domain   = $this->getSprout()->settings()->getUrlDomain(config('session.domain'));                // @phpstan-ignore-line
        $secure   = $this->getSprout()->settings()->shouldCookieBeSecure(config('session.secure', false)); // @phpstan-ignore-line
        $sameSite = $this->getSprout()->settings()->getCookieSameSite(config('session.same_site'));        // @phpstan-ignore-line

        /**
         * This is here to make PHPStan quiet down
         *
         * @var string      $path
         * @var string|null $domain
         * @var bool|null   $secure
         * @var string|null $sameSite
         */

        // Set the default values on the cookiejar
        $this->getApp()
             ->make(CookieJar::class)
             ->setDefaultPathAndDomain($path, $domain, $secure, $sameSite);
    }
}
