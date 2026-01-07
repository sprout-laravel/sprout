<?php
declare(strict_types=1);

namespace Sprout\Bud;

use Illuminate\Support\ServiceProvider;
use Sprout\Bud\Contracts\ConfigStore;
use Sprout\Bud\Managers\ConfigStoreManager;

/**
 * Bud Service Provider
 */
class BudServiceProvider extends ServiceProvider
{
    private Bud $bud;

    public function register(): void
    {
        $this->registerBud();
        $this->registerDefaultBindings();
        $this->registerManagers();
    }

    private function registerBud(): void
    {
        $this->bud = new Bud($this->app);

        $this->app->singleton(Bud::class, fn () => $this->bud);
        $this->app->alias(Bud::class, 'sprout.bud');
    }

    private function registerDefaultBindings(): void
    {
        // Bind the config store to the default implementation
        $this->app->bind(ConfigStore::class, fn () => $this->bud->store());
    }

    private function registerManagers(): void
    {
        // Register the config store manager
        $this->app->singleton(ConfigStoreManager::class, fn () => $this->bud->stores());

        // Alias the managers with simple names
        $this->app->alias(ConfigStoreManager::class, 'sprout.bud.stores');
    }

    public function boot(): void
    {
        $this->publishConfig();
        $this->publishMigrations();
    }

    private function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../resources/config/bud.php' => config_path('sprout/bud.php'),
        ], ['config', 'sprout-config', 'bud-config']);
    }

    private function publishMigrations(): void
    {
        $this->publishes([
            __DIR__ . '/../resources/migrations/0001_01_01_70000_create_bud_config_store_table.php' => database_path('migrations/0001_01_01_70000_create_bud_config_store_table.php'),
        ], ['migrations', 'sprout-migrations', 'bud-migrations']);
    }
}
