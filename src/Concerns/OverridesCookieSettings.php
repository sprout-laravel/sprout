<?php
declare(strict_types=1);

namespace Sprout\Concerns;

/**
 * Overrides Cookie Settings
 *
 * This is a helper trait used with some service overrides that need a way
 * for other elements within Sprout to override the settings of a cookie.
 *
 * @package Overrides
 */
trait OverridesCookieSettings
{
    /**
     * @var array{path?:string|null,domain?:string|null,secure?:bool|null,same_site?:string|null}
     */
    protected static array $settings = [];

    /**
     * Set the cookie domain
     *
     * @param string|null $domain
     *
     * @return void
     */
    public static function setDomain(?string $domain): void
    {
        self::$settings['domain'] = $domain;
    }

    /**
     * Set the cookie path
     *
     * @param string|null $path
     *
     * @return void
     */
    public static function setPath(?string $path): void
    {
        self::$settings['path'] = $path ? '/' . ltrim($path, '/') : null;
    }

    // @codeCoverageIgnoreStart

    /**
     * Set the same site value for the cookie
     *
     * @param string|null $sameSite
     *
     * @return void
     */
    public static function setSameSite(?string $sameSite): void
    {
        self::$settings['same_site'] = $sameSite;
    }

    /**
     * Set the secure value for the cookie
     *
     * @param bool|null $secure
     *
     * @return void
     */
    public static function setSecure(?bool $secure): void
    {
        self::$settings['secure'] = $secure;
    }
    // @codeCoverageIgnoreEnd
}
