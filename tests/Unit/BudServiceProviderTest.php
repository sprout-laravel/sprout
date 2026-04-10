<?php
declare(strict_types=1);

namespace Sprout\Bud\Tests\Unit;

use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Bud\Bud;
use Sprout\Bud\BudServiceProvider;
use Sprout\Bud\Contracts\ConfigStore;
use Sprout\Bud\Managers\ConfigStoreManager;

class BudServiceProviderTest extends UnitTestCase
{
    #[Test]
    public function serviceProviderIsRegistered(): void
    {
        $this->assertTrue(app()->providerIsLoaded(BudServiceProvider::class));
    }

    #[Test]
    public function serviceProviderIsDiscovered(): void
    {
        $manifest = app(PackageManifest::class);

        $this->assertContains(BudServiceProvider::class, $manifest->providers());
    }

    #[Test]
    public function budIsRegistered(): void
    {
        $this->assertTrue(app()->has(Bud::class));
        $this->assertTrue(app()->has('sprout.bud'));
        $this->assertTrue(app()->isShared(Bud::class));
        $this->assertFalse(app()->isShared('sprout.bud'));

        $this->assertSame(app()->make(Bud::class), app()->make(Bud::class));
        $this->assertSame(app()->make('sprout.bud'), app()->make('sprout.bud'));
        $this->assertSame(app()->make(Bud::class), app()->make('sprout.bud'));
        $this->assertSame(app()->make('sprout.bud'), app()->make(Bud::class));
    }

    #[Test]
    public function configStoreManagerIsRegistered(): void
    {
        $this->assertTrue(app()->has(ConfigStoreManager::class));
        $this->assertTrue(app()->has('sprout.bud.stores'));
        $this->assertTrue(app()->isShared(ConfigStoreManager::class));
        $this->assertFalse(app()->isShared('sprout.bud.stores'));

        $this->assertSame(app()->make(ConfigStoreManager::class), app()->make(ConfigStoreManager::class));
        $this->assertSame(app()->make('sprout.bud.stores'), app()->make('sprout.bud.stores'));
        $this->assertSame(app()->make(ConfigStoreManager::class), app()->make('sprout.bud.stores'));
        $this->assertSame(app()->make('sprout.bud.stores'), app()->make(ConfigStoreManager::class));
        $this->assertSame(app()->make(Bud::class)->stores(), app()->make('sprout.bud.stores'));
        $this->assertSame(app()->make(Bud::class)->stores(), app()->make(ConfigStoreManager::class));
    }

    #[Test]
    public function hasDefaultConfigStoreBinding(): void
    {
        $this->assertTrue(app()->bound(ConfigStore::class));
    }

    #[Test]
    public function publishesConfig(): void
    {
        $paths = ServiceProvider::pathsToPublish(BudServiceProvider::class, 'config');

        $key = realpath(__DIR__ . '/../../src');

        $this->assertArrayHasKey($key . '/../resources/config/bud.php', $paths);
        $this->assertContains(config_path('sprout/bud.php'), $paths);
    }

    #[Test]
    public function publishesMigrations(): void
    {
        $paths = ServiceProvider::pathsToPublish(BudServiceProvider::class, 'migrations');

        $key = realpath(__DIR__ . '/../../src');

        $this->assertArrayHasKey($key . '/../resources/migrations/0001_01_01_70000_create_bud_config_store_table.php', $paths);
        $this->assertContains(database_path('migrations/0001_01_01_70000_create_bud_config_store_table.php'), $paths);
    }

    #[Test]
    public function coreSproutConfigExists(): void
    {
        $this->assertTrue(app()['config']->has('sprout.bud'));
        $this->assertIsArray(app()['config']->get('sprout.bud'));
        $this->assertTrue(app()['config']->has('sprout.bud.stores'));
    }
}
