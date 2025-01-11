<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Overrides;

use Illuminate\Config\Repository;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Events\ServiceOverrideBooted;
use Sprout\Events\ServiceOverrideRegistered;
use Sprout\Overrides\SessionOverride;
use Sprout\Support\Services;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;
use function Sprout\sprout;

class SessionOverrideTest extends UnitTestCase
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
        $this->assertTrue(is_subclass_of(SessionOverride::class, BootableServiceOverride::class));
    }

    #[Test]
    public function isRegisteredWithSproutCorrectly(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'session' => [
                'driver' => SessionOverride::class,
            ],
        ]);

        $sprout->overrides()->registerOverrides();

        $this->assertTrue($sprout->overrides()->hasOverride('session'));
        $this->assertSame(SessionOverride::class, $sprout->overrides()->getOverrideClass('session'));
        $this->assertTrue($sprout->overrides()->isOverrideBootable('session'));
        $this->assertTrue($sprout->overrides()->hasOverrideBooted('session'));
    }

    #[Test]
    public function isDeferredCorrectly(): void
    {
        $this->markTestSkipped('This test needs to be updated');

        $sprout = sprout();

        Event::fake();

        $sprout->registerOverride(Services::SESSION, SessionOverride::class);

        Event::assertDispatched(ServiceOverrideRegistered::class);
        Event::assertNotDispatched(ServiceOverrideProcessing::class);
        Event::assertNotDispatched(ServiceOverrideProcessed::class);
        Event::assertNotDispatched(ServiceOverrideBooted::class);

        $this->assertTrue($sprout->hasRegisteredOverride(SessionOverride::class));
        $this->assertFalse($sprout->hasOverride(SessionOverride::class));
        $this->assertTrue($sprout->isServiceBeingOverridden(Services::SESSION));
        $this->assertFalse($sprout->isBootableOverride(SessionOverride::class));
        $this->assertTrue($sprout->isDeferrableOverride(SessionOverride::class));
        $this->assertFalse($sprout->hasBootedOverride(SessionOverride::class));
        $this->assertFalse($sprout->hasOverrideBeenSetup(SessionOverride::class));

        $overrides = $sprout->getOverrides();

        $this->assertEmpty($overrides);

        $overrides = $sprout->getRegisteredOverrides();

        $this->assertCount(1, $overrides);
        $this->assertContains(SessionOverride::class, $overrides);

        $this->instance(SessionManager::class, Mockery::mock(SessionManager::class, function (MockInterface $mock) {
            $mock->shouldReceive('extend')->times(3);
        }));

        app()->make('session');

        $overrides = $sprout->getOverrides();

        $this->assertCount(1, $overrides);
        $this->assertInstanceOf(SessionOverride::class, $overrides[SessionOverride::class]);

        $this->assertTrue($sprout->isBootableOverride(SessionOverride::class));
        $this->assertTrue($sprout->hasBootedOverride(SessionOverride::class));

        Event::assertDispatched(ServiceOverrideProcessing::class);
        Event::assertDispatched(ServiceOverrideProcessed::class);
        Event::assertDispatched(ServiceOverrideBooted::class);
    }

    #[Test]
    public function performsSetup(): void
    {
        $this->markTestSkipped('This test needs to be updated');

        $sprout = sprout();

        $this->assertFalse(sprout()->settings()->has('original.session'));

        $this->instance(SessionManager::class, Mockery::mock(SessionManager::class, function (MockInterface $mock) {
            $mock->makePartial();
            $mock->shouldReceive('forgetDrivers')->once();
        }));

        $this->assertNull(sprout()->settings()->getUrlPath());
        $this->assertNull(sprout()->settings()->getUrlDomain());
        $this->assertNull(sprout()->settings()->shouldCookieBeSecure());
        $this->assertNull(sprout()->settings()->getCookieSameSite());

        $tenant  = TenantModel::factory()->createOne();
        $tenancy = $sprout->tenancies()->get();

        $tenancy->setTenant($tenant);

        $sprout->registerOverride(Services::SESSION, SessionOverride::class);

        app()->make('session');

        $override = $sprout->getOverrides()[SessionOverride::class];

        $this->assertInstanceOf(SessionOverride::class, $override);

        $override->setup($tenancy, $tenant);
    }

    #[Test]
    public function performsCleanup(): void
    {
        $this->markTestSkipped('This test needs to be updated');

        $sprout = sprout();

        $this->assertFalse(sprout()->settings()->has('original.session'));

        $this->instance(SessionManager::class, Mockery::mock(SessionManager::class, function (MockInterface $mock) {
            $mock->makePartial();
            $mock->shouldReceive('forgetDrivers')->once();
        }));

        $tenant  = TenantModel::factory()->createOne();
        $tenancy = $sprout->tenancies()->get();

        $tenancy->setTenant($tenant);

        $sprout->registerOverride(Services::SESSION, SessionOverride::class);

        app()->make('session');

        $override = $sprout->getOverrides()[SessionOverride::class];

        $this->assertInstanceOf(SessionOverride::class, $override);

        $override->cleanup($tenancy, $tenant);
    }
}
