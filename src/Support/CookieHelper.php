<?php
declare(strict_types=1);

namespace Sprout\Support;

use Illuminate\Cookie\CookieJar;

final class CookieHelper
{
    public static function setDefaults(?string $path = null, ?string $domain = null, ?bool $secure = null, ?string $sameSite = null): void
    {
        // Collect the defaults for the values
        self::collectDefaults($path, $domain, $secure, $sameSite);

        /**
         * This is here, so PHPStan doesn't get upset
         *
         * @var string $path
         * @var string|null $domain
         * @var bool $secure
         * @var string|null $sameSite
         */

        // Set the defaults
        app(CookieJar::class)->setDefaultPathAndDomain($path, $domain, $secure, $sameSite);
    }

    public static function collectDefaults(?string &$path = null, ?string &$domain = null, ?bool &$secure = null, ?string &$sameSite = null): void
    {
        // Collect the defaults for the values
        $path     ??= config('session.path');
        $domain   ??= config('session.domain');
        $secure   ??= config('session.secure', false);
        $sameSite ??= config('session.same_site');
    }
}
