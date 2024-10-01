<?php
declare(strict_types=1);

namespace Sprout\Support;

use Illuminate\Cookie\CookieJar;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;

final class CookieHelper
{
    public static function getCookieName(Tenancy $tenancy, Tenant $tenant): string
    {
        return $tenancy->getName() . '_' . $tenant->getTenantIdentifier() . '_session';
    }

    public static function setSessionDefaults(string $cookieName, ?string $path = null, ?string $domain = null, ?bool $secure = null, ?string $sameSite = null): void
    {
        self::collectCookieDefaults($path, $domain, $secure, $sameSite);

        $config = config();

        $config->set('session.cookie', $cookieName);
        $config->set('session.path', $path);
        $config->set('session.domain', $domain);
        $config->set('session.secure', $secure);
        $config->set('session.same_site', $sameSite);
    }

    public static function resetSessionDefaults(): void
    {
        $config = config();

        $sessionConfig = require config_path('session.php');

        $config->set('session.path', $sessionConfig['path']);
        $config->set('session.domain', $sessionConfig['domain']);
        $config->set('session.secure', $sessionConfig['secure']);
        $config->set('session.same_site', $sessionConfig['same_site']);
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
