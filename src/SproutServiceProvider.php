<?php
declare(strict_types=1);

namespace Sprout;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Sprout\Http\Middleware\TenantRoutes;
use Sprout\Managers\IdentityResolverManager;
use Sprout\Managers\ProviderManager;

class SproutServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerManagers();
        $this->registerMiddleware();
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
        $this->app->alias(ProviderManager::class, 'sprout.providers');
        $this->app->alias(IdentityResolverManager::class, 'sprout.resolvers');
    }

    private function registerMiddleware(): void
    {
        /** @var \Illuminate\Routing\Router $router */
        $router = $this->app->make(Router::class);

        // Alias the basic tenant middleware
        $router->aliasMiddleware(TenantRoutes::ALIAS, TenantRoutes::class);
    }

    public function boot(): void
    {
        // Book tasks
    }
}
