<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Overrides;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Cookie\CookieJar;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Overrides\CookieOverride;
use Sprout\Sprout;
use Sprout\Support\SettingsRepository;
use Sprout\Tests\Unit\UnitTestCase;
use function Sprout\sprout;

class CookieOverrideTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('sprout.overrides', []);
        });
    }

    #[Test]
    public function isBuiltCorrectly(): void
    {
        $this->assertFalse(is_subclass_of(CookieOverride::class, BootableServiceOverride::class));
    }

    #[Test]
    public function isRegisteredWithSproutCorrectly(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'cookie' => [
                'driver' => CookieOverride::class,
            ],
        ]);

        $sprout->overrides()->registerOverrides();

        $this->assertTrue($sprout->overrides()->hasOverride('cookie'));
        $this->assertSame(CookieOverride::class, $sprout->overrides()->getOverrideClass('cookie'));
        $this->assertFalse($sprout->overrides()->isOverrideBootable('cookie'));
        $this->assertFalse($sprout->overrides()->hasOverrideBooted('cookie'));
    }

    #[Test]
    public function performsSetup(): void
    {
        $override = new CookieOverride('cookie', []);

        $app = Mockery::mock(Application::class, static function (MockInterface $mock) {
            $mock->shouldReceive('make')
                 ->with(CookieJar::class)
                 ->andReturn(
                     Mockery::mock(CookieJar::class, static function (MockInterface $mock) {
                         $mock->shouldReceive('setDefaultPathAndDomain')
                              ->with('test-path', 'domain.com', true, 'strict')
                              ->once();
                     })
                 )
                 ->once();
        });

        $settings = new SettingsRepository();

        $settings->setUrlPath('test-path');
        $settings->setUrlDomain('domain.com');
        $settings->setCookieSameSite('strict');

        config()->set('session.secure', true);

        $sprout = new Sprout($app, $settings);

        $override->setApp($app)->setSprout($sprout);

        $tenant = Mockery::mock(Tenant::class);

        $tenancy = Mockery::mock(Tenancy::class);

        $override->setup($tenancy, $tenant);
    }
}
