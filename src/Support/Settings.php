<?php
declare(strict_types=1);

namespace Sprout\Support;

final class Settings
{
    public const URL = 'url';

    public const URL_PATH = self::URL . '.path';

    public const URL_DOMAIN = self::URL . '.domain';

    public const COOKIE = 'cookie';

    public const COOKIE_SECURE = self::COOKIE . '.secure';

    public const COOKIE_SAME_SITE = self::COOKIE . '.same_site';

    public const NO_DATABASE_OVERRIDE = 'no-database-override';
}
