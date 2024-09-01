<?php
declare(strict_types=1);

namespace Sprout;

use Illuminate\Support\ServiceProvider;
use Sprout\Managers\IdentityResolverManager;
use Sprout\Managers\ProviderManager;

class SproutServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerManagers();
    }

    private function registerManagers(): void
    {
        // Register the tenant provider manager
        $this->app->singleton(ProviderManager::class, function ($app) {
            return new ProviderManager($app);
        });

        // Register the identity resolver manager
        $this->app->singleton(IdentityResolverManager::class, function ($app) {
            return new IdentityResolverManager($app);
        });

        // Alias the managers with simple names
        $this->app->alias(ProviderManager::class, 'tenanted.providers');
        $this->app->alias(IdentityResolverManager::class, 'tenanted.resolvers');
    }

    public function boot(): void
    {
        // Book tasks
    }
}
