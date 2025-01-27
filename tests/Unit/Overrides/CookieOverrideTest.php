<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Overrides;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Events\ServiceOverrideBooted;
use Sprout\Events\ServiceOverrideRegistered;
use Sprout\Overrides\AuthOverride;
use Sprout\Overrides\CookieOverride;
use Sprout\Sprout;
use Sprout\Support\Services;
use Sprout\Support\SettingsRepository;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;
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

        $app = Mockery::mock(Application::class);

        $sprout = new Sprout($app, new SettingsRepository());
    }
}
