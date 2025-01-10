<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Events\ServiceOverrideBooted;
use Sprout\Events\ServiceOverrideProcessed;
use Sprout\Events\ServiceOverrideProcessing;
use Sprout\Events\ServiceOverrideRegistered;
use Sprout\Exceptions\ServiceOverrideException;
use Sprout\Overrides\AuthOverride;
use Sprout\Overrides\StorageOverride;
use Sprout\Support\Services;
use Sprout\Support\Settings;
use Sprout\Support\SettingsRepository;
use stdClass;
use Workbench\App\Models\TenantModel;
use function Sprout\sprout;

class SproutTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('sprout.services', []);
            $config->set('multitenancy.tenancies.tenants.model', TenantModel::class);
        });
    }

    protected function setupSecondTenancy($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('multitenancy.providers.backup', [
                'driver' => 'database',
                'table'  => 'tenants',
            ]);

            $config->set('multitenancy.tenancies.backup', [
                'provider' => 'backup',
            ]);
        });
    }

    #[Test]
    public function allowsAccessToCoreConfig(): void
    {
        $this->assertSame(sprout()->config('hooks'), config('sprout.hooks'));

        config()->set('sprout.hooks', []);

        $this->assertSame(sprout()->config('hooks'), config('sprout.hooks'));
    }

    #[Test]
    public function hasNoCurrentTenancyByDefault(): void
    {
        $this->assertFalse(sprout()->hasCurrentTenancy());
    }

    #[Test]
    public function isNotWithinMultitenantedContextByDefault(): void
    {
        $this->assertFalse(sprout()->withinContext());
    }

    #[Test]
    public function setsCurrentTenancy(): void
    {
        $tenancy = sprout()->tenancies()->get();

        $this->assertFalse(sprout()->hasCurrentTenancy());
        $this->assertNull(sprout()->getCurrentTenancy());
        $this->assertFalse(sprout()->withinContext());

        sprout()->setCurrentTenancy($tenancy);

        $this->assertTrue(sprout()->hasCurrentTenancy());
        $this->assertSame($tenancy, sprout()->getCurrentTenancy());
        $this->assertTrue(sprout()->withinContext());
    }

    #[Test, DefineEnvironment('setupSecondTenancy')]
    public function canStackCurrentTenancies(): void
    {
        $tenancy1 = sprout()->tenancies()->get();
        $tenancy2 = sprout()->tenancies()->get('backup');

        $this->assertFalse(sprout()->hasCurrentTenancy());
        $this->assertNull(sprout()->getCurrentTenancy());
        $this->assertFalse(sprout()->withinContext());

        sprout()->setCurrentTenancy($tenancy1);

        $this->assertTrue(sprout()->hasCurrentTenancy());
        $this->assertSame($tenancy1, sprout()->getCurrentTenancy());
        $this->assertTrue(sprout()->withinContext());

        sprout()->setCurrentTenancy($tenancy2);

        $this->assertTrue(sprout()->hasCurrentTenancy());
        $this->assertSame($tenancy2, sprout()->getCurrentTenancy());
        $this->assertTrue(sprout()->withinContext());

        $this->assertContains($tenancy1, sprout()->getAllCurrentTenancies());
        $this->assertContains($tenancy2, sprout()->getAllCurrentTenancies());
    }

    #[Test]
    public function isAwareOfHooksToSupport(): void
    {
        $hooks = config('sprout.hooks');

        foreach ($hooks as $hook) {
            $this->assertTrue(sprout()->supportsHook($hook));
        }

        config()->set('sprout.hooks', []);

        foreach ($hooks as $hook) {
            $this->assertFalse(sprout()->supportsHook($hook));
        }
    }

    #[Test]
    public function canManuallyMarkAsInOrOutOfContext(): void
    {
        $this->assertFalse(sprout()->withinContext());

        sprout()->markAsInContext();

        $this->assertTrue(sprout()->withinContext());

        sprout()->markAsOutsideContext();

        $this->assertFalse(sprout()->withinContext());
    }

    #[Test]
    public function hasSettingsRepository(): void
    {
        $this->assertInstanceOf(SettingsRepository::class, sprout()->settings());
        $this->assertSame(app()->make(SettingsRepository::class), sprout()->settings());
    }

    #[Test]
    public function providesAccessToIndividualSettings(): void
    {
        $this->assertNull(sprout()->setting(Settings::URL_PATH));
        $this->assertNull(sprout()->setting(Settings::URL_DOMAIN));
    }

    #[Test]
    public function canRegisterServiceOverrides(): void
    {
        $sprout = sprout();

        Event::listen(ServiceOverrideRegistered::class, function (ServiceOverrideRegistered $event) {
            $this->assertSame(Services::AUTH, $event->service);
            $this->assertSame(AuthOverride::class, $event->override);
        });

        Event::listen(ServiceOverrideProcessing::class, function (ServiceOverrideProcessing $event) {
            $this->assertSame(Services::AUTH, $event->service);
            $this->assertSame(AuthOverride::class, $event->override);
        });

        Event::listen(ServiceOverrideProcessed::class, function (ServiceOverrideProcessed $event) {
            $this->assertSame(Services::AUTH, $event->service);
            $this->assertInstanceOf(AuthOverride::class, $event->override);
        });

        Event::listen(ServiceOverrideBooted::class, function (ServiceOverrideBooted $event) {
            $this->assertSame(Services::AUTH, $event->service);
            $this->assertInstanceOf(AuthOverride::class, $event->override);
        });

        $sprout->registerOverride(Services::AUTH, AuthOverride::class);

        $this->assertTrue($sprout->hasRegisteredOverride(AuthOverride::class));
        $this->assertTrue($sprout->hasOverride(AuthOverride::class));
        $this->assertTrue($sprout->isServiceBeingOverridden(Services::AUTH));
        $this->assertTrue($sprout->isBootableOverride(AuthOverride::class));
        $this->assertFalse($sprout->isDeferrableOverride(AuthOverride::class));
        $this->assertTrue($sprout->hasBootedOverride(AuthOverride::class));
        $this->assertFalse($sprout->hasOverrideBeenSetup(AuthOverride::class));

        $overrides = $sprout->getOverrides();

        $this->assertCount(1, $overrides);
        $this->assertInstanceOf(AuthOverride::class, $overrides[AuthOverride::class]);

        $overrides = $sprout->getRegisteredOverrides();

        $this->assertCount(1, $overrides);
        $this->assertContains(AuthOverride::class, $overrides);
    }

    #[Test]
    public function errorsWhenRegisteringAnInvalidServiceOverride(): void
    {
        $sprout = sprout();

        $this->expectException(ServiceOverrideException::class);
        $this->expectExceptionMessage('The provided service override [stdClass] does not implement the Sprout\Contracts\ServiceOverride interface');

        $sprout->registerOverride(Services::AUTH, stdClass::class);
    }

    #[Test]
    public function errorsWhenReplacingAnExistingServiceOverrideThatHasBootedOrBeenSetup(): void
    {
        $sprout = sprout();

        $sprout->registerOverride(Services::AUTH, AuthOverride::class);

        $this->assertTrue($sprout->hasRegisteredOverride(AuthOverride::class));
        $this->assertTrue($sprout->isServiceBeingOverridden(Services::AUTH));
        $this->assertTrue($sprout->hasBootedOverride(AuthOverride::class));

        $this->expectException(ServiceOverrideException::class);
        $this->expectExceptionMessage('The service [auth] already has an override registered [Sprout\Overrides\AuthOverride] which has already been processed');

        $sprout->registerOverride(Services::AUTH, AuthOverride::class);
    }

    #[Test]
    public function canRegisterDeferrableServiceOverrides(): void
    {
        $sprout = sprout();

        Event::fake();

        $sprout->registerOverride(Services::STORAGE, StorageOverride::class);

        Event::assertDispatched(ServiceOverrideRegistered::class);
        Event::assertNotDispatched(ServiceOverrideProcessing::class);
        Event::assertNotDispatched(ServiceOverrideProcessed::class);
        Event::assertNotDispatched(ServiceOverrideBooted::class);

        $this->assertTrue($sprout->hasRegisteredOverride(StorageOverride::class));
        $this->assertFalse($sprout->hasOverride(StorageOverride::class));
        $this->assertTrue($sprout->isServiceBeingOverridden(Services::STORAGE));
        $this->assertFalse($sprout->isBootableOverride(StorageOverride::class));
        $this->assertTrue($sprout->isDeferrableOverride(StorageOverride::class));
        $this->assertFalse($sprout->hasBootedOverride(StorageOverride::class));
        $this->assertFalse($sprout->hasOverrideBeenSetup(StorageOverride::class));

        $overrides = $sprout->getOverrides();

        $this->assertEmpty($overrides);

        $overrides = $sprout->getRegisteredOverrides();

        $this->assertCount(1, $overrides);
        $this->assertContains(StorageOverride::class, $overrides);

        app()->make('filesystem');

        $overrides = $sprout->getOverrides();

        $this->assertCount(1, $overrides);
        $this->assertInstanceOf(StorageOverride::class, $overrides[StorageOverride::class]);

        $this->assertTrue($sprout->isBootableOverride(StorageOverride::class));
        $this->assertTrue($sprout->hasBootedOverride(StorageOverride::class));

        Event::assertDispatched(ServiceOverrideProcessing::class);
        Event::assertDispatched(ServiceOverrideProcessed::class);
        Event::assertDispatched(ServiceOverrideBooted::class);
    }

    #[Test]
    public function immediatelyProcessesDeferredOverridesIfServiceIsResolved(): void
    {
        $sprout = sprout();

        app()->make('filesystem');

        Event::fake();

        $sprout->registerOverride(Services::STORAGE, StorageOverride::class);

        Event::assertDispatched(ServiceOverrideRegistered::class);
        Event::assertDispatched(ServiceOverrideProcessing::class);
        Event::assertDispatched(ServiceOverrideProcessed::class);
        Event::assertDispatched(ServiceOverrideBooted::class);

        $this->assertTrue($sprout->hasRegisteredOverride(StorageOverride::class));
        $this->assertTrue($sprout->hasOverride(StorageOverride::class));
        $this->assertTrue($sprout->isServiceBeingOverridden(Services::STORAGE));
        $this->assertTrue($sprout->isBootableOverride(StorageOverride::class));
        $this->assertTrue($sprout->isDeferrableOverride(StorageOverride::class));
        $this->assertTrue($sprout->hasBootedOverride(StorageOverride::class));
        $this->assertFalse($sprout->hasOverrideBeenSetup(StorageOverride::class));

        $overrides = $sprout->getOverrides();

        $this->assertCount(1, $overrides);
        $this->assertInstanceOf(StorageOverride::class, $overrides[StorageOverride::class]);

        $overrides = $sprout->getRegisteredOverrides();

        $this->assertCount(1, $overrides);
        $this->assertContains(StorageOverride::class, $overrides);
    }

    #[Test]
    public function doesNotDoubleBootOverrides(): void
    {
        $this->assertTrue(sprout()->haveOverridesBooted());

        $sprout = sprout();

        /** @var \Sprout\Sprout $sprout */
        $sprout = Mockery::mock($sprout, static function (MockInterface $mock) {
            $mock->shouldAllowMockingProtectedMethods();

            // Ideally we'd test that 'haveOverridesBooted' is called once,
            // and returns 'true', but for some reason Mockery can't properly
            // mock that method.
            // It doesn't give an error when attempting to do so, it just
            // refuses to detect the call to it from within 'bootOverrides'.

            $mock->shouldNotReceive('hasBootedOverride');
            $mock->shouldNotReceive('bootOverride');
        });

        $sprout->bootOverrides();
    }

    #[Test]
    public function canSetupOverrides(): void
    {
        $sprout = sprout();

        $sprout->registerOverride(Services::AUTH, AuthOverride::class);

        $this->assertTrue($sprout->hasRegisteredOverride(AuthOverride::class));
        $this->assertTrue($sprout->hasOverride(AuthOverride::class));
        $this->assertTrue($sprout->isServiceBeingOverridden(Services::AUTH));
        $this->assertTrue($sprout->isBootableOverride(AuthOverride::class));
        $this->assertFalse($sprout->isDeferrableOverride(AuthOverride::class));
        $this->assertTrue($sprout->hasBootedOverride(AuthOverride::class));
        $this->assertFalse($sprout->hasOverrideBeenSetup(AuthOverride::class));

        $overrides = $sprout->getOverrides();

        $this->assertCount(1, $overrides);
        $this->assertInstanceOf(AuthOverride::class, $overrides[AuthOverride::class]);

        $overrides = $sprout->getRegisteredOverrides();

        $this->assertCount(1, $overrides);
        $this->assertContains(AuthOverride::class, $overrides);

        $tenant  = TenantModel::factory()->createOne();
        $tenancy = $sprout->tenancies()->get();

        $this->assertEmpty($sprout->getCurrentOverrides($tenancy));
        $this->assertFalse($sprout->hasSetupOverride($tenancy, AuthOverride::class));

        $tenancy->setTenant($tenant);

        $sprout->setupOverrides($tenancy, $tenant);

        $overrides = $sprout->getCurrentOverrides($tenancy);

        $this->assertCount(1, $overrides);
        $this->assertInstanceOf(AuthOverride::class, $overrides[AuthOverride::class]);

        $this->assertTrue($sprout->hasSetupOverride($tenancy, AuthOverride::class));
        $this->assertTrue($sprout->hasOverrideBeenSetup(AuthOverride::class));
    }

    #[Test]
    public function failsSilentWhenNoTenancyToGetOverridesFor(): void
    {
        $this->assertEmpty(sprout()->getCurrentOverrides());
    }

    #[Test]
    public function immediatelySetsUpDeferredOverridesIfTenancyHasTenant(): void
    {
        $sprout = sprout();

        Event::fake();

        $tenant  = TenantModel::factory()->createOne();
        $tenancy = $sprout->tenancies()->get();

        $this->assertEmpty($sprout->getCurrentOverrides($tenancy));
        $this->assertFalse($sprout->hasSetupOverride($tenancy, StorageOverride::class));

        $tenancy->setTenant($tenant);

        $sprout->setCurrentTenancy($tenancy);

        $sprout->registerOverride(Services::STORAGE, StorageOverride::class);

        Event::assertDispatched(ServiceOverrideRegistered::class);
        Event::assertNotDispatched(ServiceOverrideProcessing::class);
        Event::assertNotDispatched(ServiceOverrideProcessed::class);
        Event::assertNotDispatched(ServiceOverrideBooted::class);

        $this->assertTrue($sprout->hasRegisteredOverride(StorageOverride::class));
        $this->assertFalse($sprout->hasOverride(StorageOverride::class));
        $this->assertTrue($sprout->isServiceBeingOverridden(Services::STORAGE));
        $this->assertFalse($sprout->isBootableOverride(StorageOverride::class));
        $this->assertTrue($sprout->isDeferrableOverride(StorageOverride::class));
        $this->assertFalse($sprout->hasBootedOverride(StorageOverride::class));
        $this->assertFalse($sprout->hasOverrideBeenSetup(StorageOverride::class));

        $overrides = $sprout->getOverrides();

        $this->assertEmpty($overrides);

        $overrides = $sprout->getRegisteredOverrides();

        $this->assertCount(1, $overrides);
        $this->assertContains(StorageOverride::class, $overrides);

        app()->make('filesystem');

        $overrides = $sprout->getCurrentOverrides($tenancy);

        $this->assertCount(1, $overrides);
        $this->assertInstanceOf(StorageOverride::class, $overrides[StorageOverride::class]);

        $this->assertTrue($sprout->hasSetupOverride($tenancy, StorageOverride::class));
        $this->assertTrue($sprout->hasOverrideBeenSetup(StorageOverride::class));
    }

    #[Test]
    public function canCleanupOverrides(): void
    {
        $sprout = sprout();

        $sprout->registerOverride(Services::AUTH, AuthOverride::class);

        $this->assertTrue($sprout->hasRegisteredOverride(AuthOverride::class));
        $this->assertTrue($sprout->hasOverride(AuthOverride::class));
        $this->assertTrue($sprout->isServiceBeingOverridden(Services::AUTH));
        $this->assertTrue($sprout->isBootableOverride(AuthOverride::class));
        $this->assertFalse($sprout->isDeferrableOverride(AuthOverride::class));
        $this->assertTrue($sprout->hasBootedOverride(AuthOverride::class));
        $this->assertFalse($sprout->hasOverrideBeenSetup(AuthOverride::class));

        $overrides = $sprout->getOverrides();

        $this->assertCount(1, $overrides);
        $this->assertInstanceOf(AuthOverride::class, $overrides[AuthOverride::class]);

        $overrides = $sprout->getRegisteredOverrides();

        $this->assertCount(1, $overrides);
        $this->assertContains(AuthOverride::class, $overrides);

        $tenant  = TenantModel::factory()->createOne();
        $tenancy = $sprout->tenancies()->get();

        $this->assertEmpty($sprout->getCurrentOverrides($tenancy));
        $this->assertFalse($sprout->hasSetupOverride($tenancy, AuthOverride::class));

        $tenancy->setTenant($tenant);

        $sprout->setupOverrides($tenancy, $tenant);

        $overrides = $sprout->getCurrentOverrides($tenancy);

        $this->assertCount(1, $overrides);
        $this->assertInstanceOf(AuthOverride::class, $overrides[AuthOverride::class]);

        $this->assertTrue($sprout->hasSetupOverride($tenancy, AuthOverride::class));
        $this->assertTrue($sprout->hasOverrideBeenSetup(AuthOverride::class));

        $sprout->cleanupOverrides($tenancy, $tenant);

        $overrides = $sprout->getCurrentOverrides($tenancy);

        $this->assertEmpty($overrides);

        $this->assertFalse($sprout->hasSetupOverride($tenancy, AuthOverride::class));
        $this->assertFalse($sprout->hasOverrideBeenSetup(AuthOverride::class));
    }
}
