<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Overrides;

use Illuminate\Config\Repository;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\DeferrableServiceOverride;
use Sprout\Overrides\Auth\TenantAwarePasswordBrokerManager;
use Sprout\Overrides\AuthOverride;
use Sprout\Support\Services;
use Sprout\Tests\Unit\UnitTestCase;
use function Sprout\sprout;

class AuthOverrideTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('sprout.services', []);
        });
    }

    #[Test]
    public function isBuiltCorrectly(): void
    {
        $this->assertTrue(is_subclass_of(AuthOverride::class, BootableServiceOverride::class));
        $this->assertFalse(is_subclass_of(AuthOverride::class, DeferrableServiceOverride::class));
    }

    #[Test]
    public function isRegisteredWithSproutCorrectly(): void
    {
        $sprout = sprout();

        $sprout->registerOverride(Services::AUTH, AuthOverride::class);

        $this->assertTrue($sprout->hasRegisteredOverride(AuthOverride::class));
        $this->assertTrue($sprout->isBootableOverride(AuthOverride::class));
        $this->assertFalse($sprout->isDeferrableOverride(AuthOverride::class));
    }

    #[Test]
    public function isBootedCorrectly(): void
    {
        $sprout = sprout();

        $sprout->registerOverride(Services::AUTH, AuthOverride::class);

        $this->assertFalse(app()->isDeferredService('auth.password'));
        $this->assertTrue(app()->bound('auth.password'));
        $this->assertFalse(app()->resolved('auth.password'));
        $this->assertFalse(app()->resolved('auth.password.broker'));
        $this->assertInstanceOf(TenantAwarePasswordBrokerManager::class, app()->make('auth.password'));
        $this->assertTrue(app()->resolved('auth.password'));
        $this->assertFalse(app()->resolved('auth.password.broker'));
    }
}
