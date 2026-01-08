<?php
declare(strict_types=1);

namespace Sprout\Core\Tests\Unit\Managers;

use PHPUnit\Framework\Attributes\Test;
use Sprout\Core\Exceptions\MisconfigurationException;
use Sprout\Core\Exceptions\TenancyMissingException;
use Sprout\Core\Managers\ServiceOverrideManager;
use Sprout\Core\Overrides\CookieOverride;
use Sprout\Core\Overrides\SessionOverride;
use Sprout\Core\TenancyOptions;
use Sprout\Core\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;
use function Sprout\Core\sprout;

class ServiceOverrideManagerTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('sprout.overrides', []);
        });
    }

    #[Test]
    public function isRegisteredWithTheContainerAsSingleton(): void
    {
        $manager = app()->make(ServiceOverrideManager::class);

        $this->assertInstanceOf(ServiceOverrideManager::class, $manager);

        $aliasedManager = app()->make('sprout.overrides');

        $this->assertInstanceOf(ServiceOverrideManager::class, $aliasedManager);
        $this->assertSame($manager, $aliasedManager);

        $sproutManager = sprout()->overrides();

        $this->assertInstanceOf(ServiceOverrideManager::class, $sproutManager);
        $this->assertSame($manager, $sproutManager);
        $this->assertSame($aliasedManager, $sproutManager);
    }

    #[Test]
    public function keepsTrackOfRegisteredOverrides(): void
    {
        $overrides = sprout()->overrides();

        $this->assertFalse($overrides->hasOverride('session'));

        config()->set('sprout.overrides.session', [
            'driver' => SessionOverride::class,
        ]);

        $overrides->registerOverrides();

        $this->assertTrue($overrides->hasOverride('session'));
    }

    #[Test]
    public function keepsTrackOfWhichOverridesAreBootable(): void
    {
        config()->set('sprout.overrides', [
            'session' => ['driver' => SessionOverride::class],
            'cookie'  => ['driver' => CookieOverride::class],
        ]);

        $overrides = sprout()->overrides();

        $overrides->registerOverrides();

        $this->assertTrue($overrides->isOverrideBootable('session'));
        $this->assertFalse($overrides->isOverrideBootable('cookie'));
    }

    #[Test]
    public function bootsBootableOverrides(): void
    {
        config()->set('sprout.overrides', [
            'session' => ['driver' => SessionOverride::class],
            'cookie'  => ['driver' => CookieOverride::class],
        ]);

        $overrides = sprout()->overrides();

        $overrides->registerOverrides();

        $this->assertTrue($overrides->isOverrideBootable('session'));
        $this->assertFalse($overrides->isOverrideBootable('cookie'));
        $this->assertTrue($overrides->haveOverridesBooted());
        $this->assertTrue($overrides->hasOverrideBooted('session'));
        $this->assertFalse($overrides->hasOverrideBooted('cookie'));
    }

    #[Test]
    public function canReturnServiceOverrides(): void
    {
        config()->set('sprout.overrides', [
            'session' => ['driver' => SessionOverride::class],
            'cookie'  => ['driver' => CookieOverride::class],
        ]);

        $overrides = sprout()->overrides();

        $overrides->registerOverrides();

        $this->assertInstanceOf(SessionOverride::class, $overrides->get('session'));
        $this->assertInstanceOf(CookieOverride::class, $overrides->get('cookie'));
        $this->assertNull($overrides->get('missing'));
    }

    #[Test]
    public function mapsServicesToTheirDriverClass(): void
    {
        config()->set('sprout.overrides', [
            'session' => ['driver' => SessionOverride::class],
            'cookie'  => ['driver' => CookieOverride::class],
        ]);

        $overrides = sprout()->overrides();

        $overrides->registerOverrides();

        $this->assertSame(SessionOverride::class, $overrides->getOverrideClass('session'));
        $this->assertSame(CookieOverride::class, $overrides->getOverrideClass('cookie'));
        $this->assertInstanceOf(SessionOverride::class, $overrides->get('session'));
        $this->assertInstanceOf(CookieOverride::class, $overrides->get('cookie'));
    }

    #[Test]
    public function errorsWhenCurrentTenancyMissingForSetupCheck(): void
    {
        $this->expectException(TenancyMissingException::class);
        $this->expectExceptionMessage('There is no current tenancy');

        sprout()->overrides()->hasOverrideBeenSetUp('session');
    }

    #[Test]
    public function keepsTrackOfServiceOverridesThatHaveBeenSetupForEachTenancy(): void
    {
        config()->set('sprout.overrides', [
            'session' => ['driver' => SessionOverride::class],
            'cookie'  => ['driver' => CookieOverride::class],
        ]);

        $tenancy = sprout()->tenancies()->get();

        sprout()->setCurrentTenancy($tenancy);

        $overrides = sprout()->overrides();

        $overrides->registerOverrides();

        $this->assertFalse($overrides->hasOverrideBeenSetUp('session'));
        $this->assertFalse($overrides->hasOverrideBeenSetUp('cookie'));
        $this->assertEmpty($overrides->getSetupOverrides($tenancy));
    }

    #[Test]
    public function setsUpOverridesForTenancies(): void
    {
        config()->set('sprout.overrides', [
            'session' => ['driver' => SessionOverride::class],
            'cookie'  => ['driver' => CookieOverride::class],
        ]);

        $tenancy = sprout()->tenancies()->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenant = TenantModel::factory()->createOne();

        $tenancy->setTenant($tenant);

        $overrides = sprout()->overrides();

        $overrides->registerOverrides();

        $this->assertFalse($overrides->hasOverrideBeenSetUp('session'));
        $this->assertFalse($overrides->hasOverrideBeenSetUp('cookie'));
        $this->assertEmpty($overrides->getSetupOverrides($tenancy));

        $overrides->setupOverrides($tenancy, $tenant);

        $this->assertTrue($overrides->hasOverrideBeenSetUp('session'));
        $this->assertTrue($overrides->hasOverrideBeenSetUp('cookie'));
        $this->assertContains('session', $overrides->getSetupOverrides($tenancy));
        $this->assertContains('cookie', $overrides->getSetupOverrides($tenancy));
        $this->assertNotContains('auth', $overrides->getSetupOverrides($tenancy));
    }

    #[Test]
    public function onlySetsUpOverridesConfiguredForTenancy(): void
    {
        config()->set('sprout.overrides', [
            'session' => ['driver' => SessionOverride::class],
            'cookie'  => ['driver' => CookieOverride::class],
        ]);

        config()->set('multitenancy.tenancies.tenants.options', [TenancyOptions::overrides(['cookie'])]);

        $tenancy = sprout()->tenancies()->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenant = TenantModel::factory()->createOne();

        $tenancy->setTenant($tenant);

        $overrides = sprout()->overrides();

        $overrides->registerOverrides();

        $this->assertFalse($overrides->hasOverrideBeenSetUp('session'));
        $this->assertFalse($overrides->hasOverrideBeenSetUp('cookie'));
        $this->assertEmpty($overrides->getSetupOverrides($tenancy));

        $overrides->setupOverrides($tenancy, $tenant);

        $this->assertFalse($overrides->hasOverrideBeenSetUp('session'));
        $this->assertTrue($overrides->hasOverrideBeenSetUp('cookie'));
        $this->assertNotContains('session', $overrides->getSetupOverrides($tenancy));
        $this->assertContains('cookie', $overrides->getSetupOverrides($tenancy));
        $this->assertNotContains('session', $overrides->getSetupOverrides($tenancy));
    }

    #[Test]
    public function setsUpAllOverridesIfConfiguredTo(): void
    {
        config()->set('sprout.overrides', [
            'session' => ['driver' => SessionOverride::class],
            'cookie'  => ['driver' => CookieOverride::class],
        ]);

        config()->set('multitenancy.tenancies.tenants.options', [TenancyOptions::allOverrides()]);

        $tenancy = sprout()->tenancies()->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenant = TenantModel::factory()->createOne();

        $tenancy->setTenant($tenant);

        $overrides = sprout()->overrides();

        $overrides->registerOverrides();

        $this->assertFalse($overrides->hasOverrideBeenSetUp('session'));
        $this->assertFalse($overrides->hasOverrideBeenSetUp('cookie'));
        $this->assertEmpty($overrides->getSetupOverrides($tenancy));

        $overrides->setupOverrides($tenancy, $tenant);

        $this->assertTrue($overrides->hasOverrideBeenSetUp('session'));
        $this->assertTrue($overrides->hasOverrideBeenSetUp('cookie'));
        $this->assertContains('session', $overrides->getSetupOverrides($tenancy));
        $this->assertContains('cookie', $overrides->getSetupOverrides($tenancy));
        $this->assertNotContains('auth', $overrides->getSetupOverrides($tenancy));
    }

    #[Test]
    public function onlyCleansUpOverridesThatHaveAlreadyBeenSetUp(): void
    {
        config()->set('sprout.overrides', [
            'session' => ['driver' => SessionOverride::class],
            'cookie'  => ['driver' => CookieOverride::class],
        ]);

        config()->set('multitenancy.tenancies.tenants.options', [TenancyOptions::overrides(['cookie'])]);

        $tenancy = sprout()->tenancies()->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenant = TenantModel::factory()->createOne();

        $tenancy->setTenant($tenant);

        $overrides = sprout()->overrides();

        $overrides->registerOverrides();

        $this->assertFalse($overrides->hasOverrideBeenSetUp('session'));
        $this->assertFalse($overrides->hasOverrideBeenSetUp('cookie'));
        $this->assertEmpty($overrides->getSetupOverrides($tenancy));

        $overrides->setupOverrides($tenancy, $tenant);

        $this->assertFalse($overrides->hasOverrideBeenSetUp('session'));
        $this->assertTrue($overrides->hasOverrideBeenSetUp('cookie'));
        $this->assertNotContains('session', $overrides->getSetupOverrides($tenancy));
        $this->assertContains('cookie', $overrides->getSetupOverrides($tenancy));
        $this->assertNotContains('session', $overrides->getSetupOverrides($tenancy));

        $overrides->cleanupOverrides($tenancy, $tenant);

        $this->assertFalse($overrides->hasOverrideBeenSetUp('session'));
        $this->assertFalse($overrides->hasOverrideBeenSetUp('cookie'));
        $this->assertEmpty($overrides->getSetupOverrides($tenancy));
    }

    #[Test]
    public function errorsWhenRegisteringOverrideWithoutConfig(): void
    {
        config()->set('sprout.overrides', ['session' => null]);

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The service override for [session] could not be found');

        sprout()->overrides()->registerOverrides();
    }

    #[Test]
    public function errorsWhenRegisteringOverrideWithoutDriver(): void
    {
        config()->set('sprout.overrides', ['session' => []]);

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The service override [session] is missing a required value for \'driver\'');

        sprout()->overrides()->registerOverrides();
    }

    #[Test]
    public function errorsWhenRegisteringOverrideWithInvalidDriver(): void
    {
        config()->set('sprout.overrides', ['session' => ['driver' => \stdClass::class]]);

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The provided value for \'driver\' [stdClass] is not valid for service override [session]');

        sprout()->overrides()->registerOverrides();
    }
}
