<?php
declare(strict_types=1);

namespace Sprout\Tests\_Original;

use Illuminate\Events\Dispatcher;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Events\CurrentTenantChanged;
use Sprout\Listeners\IdentifyTenantOnRouting;
use Sprout\Listeners\PerformIdentityResolverSetup;
use Sprout\Listeners\SetCurrentTenantContext;
use Sprout\Listeners\SetCurrentTenantForJob;
use Sprout\Managers\IdentityResolverManager;
use Sprout\Managers\TenantProviderManager;
use Sprout\Managers\TenancyManager;
use Sprout\Sprout;
use Sprout\SproutServiceProvider;

#[Group('core'), Group('serviceProviders')]
class ServiceProviderTest extends TestCase
{
    use WithWorkbench;

    protected $enablesPackageDiscoveries = true;

    #[Test]
    public function serviceProviderIsRegistered(): void
    {
        $this->assertTrue(app()->providerIsLoaded(SproutServiceProvider::class));
    }

    #[Test]
    public function sproutIsRegistered(): void
    {
        $this->assertTrue(app()->has(Sprout::class));
        $this->assertTrue(app()->has('sprout'));
        $this->assertTrue(app()->isShared(Sprout::class));
        $this->assertFalse(app()->isShared('sprout'));

        $this->assertSame(app()->make(Sprout::class), app()->make(Sprout::class));
        $this->assertSame(app()->make('sprout'), app()->make('sprout'));
        $this->assertSame(app()->make(Sprout::class), app()->make('sprout'));
        $this->assertSame(app()->make('sprout'), app()->make(Sprout::class));
    }

    #[Test]
    public function coreSproutConfigExists(): void
    {
        $this->assertTrue(app()['config']->has('sprout'));
        $this->assertIsArray(app()['config']->get('sprout'));
        $this->assertTrue(app()['config']->has('sprout.hooks'));
    }

    #[Test]
    public function providerManagerIsRegistered(): void
    {
        $this->assertTrue(app()->has(TenantProviderManager::class));
        $this->assertTrue(app()->has('sprout.providers'));
        $this->assertTrue(app()->isShared(TenantProviderManager::class));
        $this->assertFalse(app()->isShared('sprout.providers'));

        $this->assertSame(app()->make(TenantProviderManager::class), app()->make(TenantProviderManager::class));
        $this->assertSame(app()->make('sprout.providers'), app()->make('sprout.providers'));
        $this->assertSame(app()->make(TenantProviderManager::class), app()->make('sprout.providers'));
        $this->assertSame(app()->make('sprout.providers'), app()->make(TenantProviderManager::class));
        $this->assertSame(app()->make(Sprout::class)->providers(), app()->make('sprout.providers'));
        $this->assertSame(app()->make(Sprout::class)->providers(), app()->make(TenantProviderManager::class));
    }

    #[Test]
    public function identityResolverManagerIsRegistered(): void
    {
        $this->assertTrue(app()->has(IdentityResolverManager::class));
        $this->assertTrue(app()->has('sprout.resolvers'));
        $this->assertTrue(app()->isShared(IdentityResolverManager::class));
        $this->assertFalse(app()->isShared('sprout.resolvers'));

        $this->assertSame(app()->make(IdentityResolverManager::class), app()->make(IdentityResolverManager::class));
        $this->assertSame(app()->make('sprout.resolvers'), app()->make('sprout.resolvers'));
        $this->assertSame(app()->make(IdentityResolverManager::class), app()->make('sprout.resolvers'));
        $this->assertSame(app()->make('sprout.resolvers'), app()->make(IdentityResolverManager::class));
        $this->assertSame(app()->make(Sprout::class)->resolvers(), app()->make('sprout.resolvers'));
        $this->assertSame(app()->make(Sprout::class)->resolvers(), app()->make(IdentityResolverManager::class));
    }

    #[Test]
    public function tenancyManagerIsRegistered(): void
    {
        $this->assertTrue(app()->has(TenancyManager::class));
        $this->assertTrue(app()->has('sprout.tenancies'));
        $this->assertTrue(app()->isShared(TenancyManager::class));
        $this->assertFalse(app()->isShared('sprout.tenancies'));

        $this->assertSame(app()->make(TenancyManager::class), app()->make(TenancyManager::class));
        $this->assertSame(app()->make('sprout.tenancies'), app()->make('sprout.tenancies'));
        $this->assertSame(app()->make(TenancyManager::class), app()->make('sprout.tenancies'));
        $this->assertSame(app()->make('sprout.tenancies'), app()->make(TenancyManager::class));
        $this->assertSame(app()->make(Sprout::class)->tenancies(), app()->make('sprout.tenancies'));
        $this->assertSame(app()->make(Sprout::class)->tenancies(), app()->make(TenancyManager::class));
    }

    #[Test]
    public function publishesConfig(): void
    {
        $paths = ServiceProvider::pathsToPublish(SproutServiceProvider::class, 'config');

        $key = realpath(__DIR__ . '/../../src');

        $this->assertArrayHasKey($key . '/../resources/config/multitenancy.php', $paths);
        $this->assertContains(config_path('multitenancy.php'), $paths);
    }

    #[Test]
    public function registersEventHandlers(): void
    {
        $dispatcher = app()->make(Dispatcher::class);

        $this->assertTrue($dispatcher->hasListeners(RouteMatched::class));
        $this->assertTrue($dispatcher->hasListeners(CurrentTenantChanged::class));
        $this->assertTrue($dispatcher->hasListeners(JobProcessing::class));

        $listeners = $dispatcher->getRawListeners();

        $this->assertContains(IdentifyTenantOnRouting::class, $listeners[RouteMatched::class]);
        $this->assertContains(SetCurrentTenantContext::class, $listeners[CurrentTenantChanged::class]);
        $this->assertContains(PerformIdentityResolverSetup::class, $listeners[CurrentTenantChanged::class]);
        $this->assertContains(SetCurrentTenantForJob::class, $listeners[JobProcessing::class]);
    }
}
