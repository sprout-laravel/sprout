<?php
declare(strict_types=1);

namespace Sprout\Support;

use Illuminate\Cookie\CookieJar;
use RuntimeException;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;

final class CookieHelper
{
    /**
     * @param \Sprout\Contracts\Tenancy<*> $tenancy
     * @param \Sprout\Contracts\Tenant     $tenant
     *
     * @return string
     */
    public static function getCookieName(Tenancy $tenancy, Tenant $tenant): string
    {
        return $tenancy->getName() . '_' . $tenant->getTenantIdentifier() . '_session';
    }

    public static function setSessionDefaults(string $cookieName, ?string $path = null, ?string $domain = null, ?bool $secure = null, ?string $sameSite = null): void
    {
        self::collectCookieDefaults($path, $domain, $secure, $sameSite);

        $config = config();

        // If the config isn't already backed up, we'll do so
        if (! $config->has('_original.session')) {
            $config->set('_original.session', [
                'cookie'    => $config->get('session.cookie'),
                'path'      => $config->get('session.path'),
                'domain'    => $config->get('session.domain'),
                'secure'    => $config->get('session.secure'),
                'same_site' => $config->get('session.same_site'),
            ]);
        }

        $config->set('session.cookie', $cookieName);
        $config->set('session.path', $path);
        $config->set('session.domain', $domain);
        $config->set('session.secure', $secure);
        $config->set('session.same_site', $sameSite);
    }

    public static function resetSessionDefaults(): void
    {
        $config = config();

        if (! $config->has('_original.session')) {
            throw new RuntimeException('Cannot reset session defaults as they are missing');
        }

        $config->set('session.path', $config->get('_original.session.path'));
        $config->set('session.domain', $config->get('_original.session.domain'));
        $config->set('session.secure', $config->get('_original.session.secure'));
        $config->set('session.same_site', $config->get('_original.session.same_site'));
    }

    public static function setCookieDefaults(?string $path = null, ?string $domain = null, ?bool $secure = null, ?string $sameSite = null): void
    {
        // Collect the defaults for the values
        self::collectCookieDefaults($path, $domain, $secure, $sameSite);

        /**
         * This is here, so PHPStan doesn't get upset
         *
         * @var string      $path
         * @var string|null $domain
         * @var bool        $secure
         * @var string|null $sameSite
         */

        // Set the defaults
        app(CookieJar::class)->setDefaultPathAndDomain($path, $domain, $secure, $sameSite);
    }

    public static function resetCookieDefaults(): void
    {
        self::setCookieDefaults();
    }

    public static function collectCookieDefaults(?string &$path = null, ?string &$domain = null, ?bool &$secure = null, ?string &$sameSite = null): void
    {
        // Collect the defaults for the values
        $path     ??= config('session.path');
        $domain   ??= config('session.domain');
        $secure   ??= config('session.secure', false);
        $sameSite ??= config('session.same_site');
    }
}
