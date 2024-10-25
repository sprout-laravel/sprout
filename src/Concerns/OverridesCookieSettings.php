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
     * @var string|null
     */
    protected static ?string $path = null;

    /**
     * @var string|null
     */
    protected static ?string $domain = null;

    /**
     * @var bool|null
     */
    protected static ?bool $secure = null;

    /**
     * @var string|null
     */
    protected static ?string $sameSite = null;

    /**
     * Set the cookie domain
     *
     * @param string|null $domain
     *
     * @return void
     */
    public static function setDomain(?string $domain): void
    {
        self::$domain = $domain;
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
        self::$path = $path;
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
        self::$sameSite = $sameSite;
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
        self::$secure = $secure;
    }
    // @codeCoverageIgnoreEnd
}
