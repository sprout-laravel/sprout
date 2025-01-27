<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Mockery;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\Tenancy;
use Sprout\Sprout;
use Sprout\Support\ResolutionHook;
use Sprout\Support\Settings;
use Sprout\Support\SettingsRepository;
use Workbench\App\Models\TenantModel;
use function Sprout\sprout;

class SproutTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
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
        $this->assertSame(sprout()->config('core.hooks'), config('sprout.core.hooks'));

        config()->set('sprout.core.hooks', []);

        $this->assertSame(sprout()->config('core.hooks'), config('sprout.core.hooks'));
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
    public function canResetTenancies(): void
    {
        $app = Mockery::mock(Application::class, static function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('forgetExtenders')->with(Tenancy::class)->twice();
            $mock->shouldReceive('extend')->with(Tenancy::class, Mockery::on(fn ($arg) => $arg instanceof \Closure))->twice();
        });

        $sprout = new Sprout($app, new SettingsRepository());

        $tenancy1 = Mockery::mock(Tenancy::class, static function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('check')->andReturn(true)->once();
            $mock->shouldReceive('setTenant')->with(null)->once();
        });

        $tenancy2 = Mockery::mock(Tenancy::class, static function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('check')->andReturn(false)->once();
            $mock->shouldNotReceive('setTenant');
        });

        Event::fake();

        $sprout->setCurrentTenancy($tenancy1);
        $sprout->setCurrentTenancy($tenancy2);

        $this->assertNotEmpty($sprout->getAllCurrentTenancies());
        $this->assertContains($tenancy1, $sprout->getAllCurrentTenancies());
        $this->assertContains($tenancy2, $sprout->getAllCurrentTenancies());

        $sprout->resetTenancies();

        $this->assertEmpty($sprout->getAllCurrentTenancies());
    }

    #[Test]
    public function isAwareOfHooksToSupport(): void
    {
        $hooks = config('sprout.core.hooks');

        foreach ($hooks as $hook) {
            $this->assertTrue(sprout()->supportsHook($hook));
        }

        config()->set('sprout.core.hooks', []);

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
    public function isAwareOfCurrentHook(): void
    {
        $sprout = new Sprout(Mockery::mock(Application::class), new SettingsRepository());

        $this->assertNull($sprout->getCurrentHook());

        $sprout->setCurrentHook(ResolutionHook::Routing);

        $this->assertNotNull($sprout->getCurrentHook());
        $this->assertNotSame(ResolutionHook::Booting, $sprout->getCurrentHook());
        $this->assertSame(ResolutionHook::Routing, $sprout->getCurrentHook());
        $this->assertNotSame(ResolutionHook::Middleware, $sprout->getCurrentHook());
        $this->assertFalse($sprout->isCurrentHook(ResolutionHook::Booting));
        $this->assertTrue($sprout->isCurrentHook(ResolutionHook::Routing));
        $this->assertFalse($sprout->isCurrentHook(ResolutionHook::Middleware));

        $sprout->setCurrentHook(ResolutionHook::Middleware);

        $this->assertNotNull($sprout->getCurrentHook());
        $this->assertNotSame(ResolutionHook::Booting, $sprout->getCurrentHook());
        $this->assertNotSame(ResolutionHook::Routing, $sprout->getCurrentHook());
        $this->assertSame(ResolutionHook::Middleware, $sprout->getCurrentHook());
        $this->assertFalse($sprout->isCurrentHook(ResolutionHook::Booting));
        $this->assertFalse($sprout->isCurrentHook(ResolutionHook::Routing));
        $this->assertTrue($sprout->isCurrentHook(ResolutionHook::Middleware));

        $sprout->setCurrentHook(null);

        $this->assertNull($sprout->getCurrentHook());
        $this->assertNotSame(ResolutionHook::Booting, $sprout->getCurrentHook());
        $this->assertNotSame(ResolutionHook::Routing, $sprout->getCurrentHook());
        $this->assertNotSame(ResolutionHook::Middleware, $sprout->getCurrentHook());
        $this->assertFalse($sprout->isCurrentHook(ResolutionHook::Booting));
        $this->assertFalse($sprout->isCurrentHook(ResolutionHook::Routing));
        $this->assertFalse($sprout->isCurrentHook(ResolutionHook::Middleware));
    }
}
