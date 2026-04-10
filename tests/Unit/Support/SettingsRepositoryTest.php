<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use Sprout\Support\Settings;
use Sprout\Support\SettingsRepository;
use Sprout\Tests\Unit\UnitTestCase;

class SettingsRepositoryTest extends UnitTestCase
{
    #[Test]
    public function canReadAndWriteValues(): void
    {
        $settings = app(SettingsRepository::class);

        $this->assertFalse($settings->has('fake-setting'));
        $this->assertNull($settings->get('fake-setting'));

        $settings->set('fake-setting', true);

        $this->assertTrue($settings->has('fake-setting'));
        $this->assertTrue($settings->get('fake-setting'));
    }

    #[Test]
    public function hasWorkingUrlPathHelpers(): void
    {
        $settings = app(SettingsRepository::class);

        $this->assertFalse($settings->has(Settings::URL_PATH));
        $this->assertNull($settings->getUrlPath());

        $settings->setUrlPath('twenty-four');

        $this->assertSame('twenty-four', $settings->get(Settings::URL_PATH));
        $this->assertSame('twenty-four', $settings->getUrlPath());
    }

    #[Test]
    public function hasWorkingUrlDomainHelpers(): void
    {
        $settings = app(SettingsRepository::class);

        $this->assertFalse($settings->has(Settings::URL_DOMAIN));
        $this->assertNull($settings->getUrlDomain());

        $settings->setUrlDomain('twenty-four');

        $this->assertSame('twenty-four', $settings->get(Settings::URL_DOMAIN));
        $this->assertSame('twenty-four', $settings->getUrlDomain());
    }

    #[Test]
    public function hasWorkingCookieSecureHelpers(): void
    {
        $settings = app(SettingsRepository::class);

        $this->assertFalse($settings->has(Settings::COOKIE_SECURE));
        $this->assertNull($settings->shouldCookieBeSecure());

        $settings->setCookieSecure(true);

        $this->assertTrue($settings->get(Settings::COOKIE_SECURE));
        $this->assertTrue($settings->shouldCookieBeSecure());
    }

    #[Test]
    public function hasWorkingCookieSameSiteHelpers(): void
    {
        $settings = app(SettingsRepository::class);

        $this->assertFalse($settings->has(Settings::COOKIE_SAME_SITE));
        $this->assertNull($settings->getCookieSameSite());

        $settings->setCookieSameSite('lax');

        $this->assertSame('lax', $settings->get(Settings::COOKIE_SAME_SITE));
        $this->assertSame('lax', $settings->getCookieSameSite());
    }

    #[Test]
    public function hasWorkingDatabaseOverrideHelpers(): void
    {
        $settings = app(SettingsRepository::class);

        $this->assertFalse($settings->has(Settings::NO_DATABASE_OVERRIDE));
        $this->assertFalse($settings->shouldNotOverrideTheDatabase());

        $settings->doNotOverrideTheDatabase();

        $this->assertTrue($settings->get(Settings::NO_DATABASE_OVERRIDE));
        $this->assertTrue($settings->shouldNotOverrideTheDatabase());
    }
}
