<?php
declare(strict_types=1);

namespace Sprout\Support;

use Illuminate\Config\Repository;

/**
 * Settings Repository
 *
 * This class is used to store internal Sprout settings used by various
 * components for various purposes.
 */
final class SettingsRepository extends Repository
{
    public function setUrlPath(?string $path): void
    {
        $this->set(Settings::URL_PATH, $path);
    }

    public function getUrlPath(?string $default = null): ?string
    {
        /** @var string|null $path */
        $path = $this->get(Settings::URL_PATH, $default);

        return $path;
    }

    public function setUrlDomain(?string $domain): void
    {
        $this->set(Settings::URL_DOMAIN, $domain);
    }

    public function getUrlDomain(?string $default = null): ?string
    {
        /** @var string|null $domain */
        $domain = $this->get(Settings::URL_DOMAIN, $default);

        return $domain;
    }

    public function setCookieSecure(bool $secure): void
    {
        $this->set(Settings::COOKIE_SECURE, $secure);
    }

    public function shouldCookieBeSecure(?bool $default = null): bool
    {
        return $this->boolean(Settings::COOKIE_SECURE, $default);
    }

    public function setCookieSameSite(bool $sameSite): void
    {
        $this->set(Settings::COOKIE_SAME_SITE, $sameSite);
    }

    public function shouldCookeBeSameSite(?bool $default = null): bool
    {
        return $this->boolean(Settings::COOKIE_SAME_SITE, $default);
    }

    public function doNotOverrideTheDatabase(): void
    {
        $this->set(Settings::NO_DATABASE_OVERRIDE, true);
    }

    public function shouldNotOverrideTheDatabase(bool $default = null): bool
    {
        return $this->boolean(Settings::NO_DATABASE_OVERRIDE, $default);
    }
}
