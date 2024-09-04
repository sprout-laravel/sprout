<?php
declare(strict_types=1);

namespace Sprout;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Sprout\Events\CurrentTenantChanged;
use Sprout\Http\Middleware\TenantRoutes;
use Sprout\Listeners\HandleTenantContext;
use Sprout\Listeners\PerformIdentityResolverSetup;
use Sprout\Listeners\IdentityTenantOnRouting;
use Sprout\Managers\IdentityResolverManager;
use Sprout\Managers\ProviderManager;

class SproutServiceProvider extends ServiceProvider
{
    private Sprout $sprout;

    public function register(): void
    {
        $this->handleCoreConfig();
        $this->registerSprout();
        $this->registerManagers();
        $this->registerMiddleware();
        $this->booting(function () {
            $this->registerEventListeners();
        });
    }

    private function handleCoreConfig(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../resources/config/sprout.php', 'sprout');
    }

    private function registerSprout(): void
    {
        $this->sprout = new Sprout($this->app);

        $this->app->singleton(Sprout::class, fn () => $this->sprout);
        $this->app->alias(Sprout::class, 'sprout');
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

    private function registerEventListeners(): void
    {
        /** @var \Illuminate\Contracts\Events\Dispatcher $events */
        $events = $this->app->make(Dispatcher::class);

        // If we should be listening for routing
        if ($this->sprout->shouldListenForRouting()) {
            $events->listen(RouteMatched::class, IdentityTenantOnRouting::class);
        }

        $events->listen(CurrentTenantChanged::class, HandleTenantContext::class);
        $events->listen(CurrentTenantChanged::class, PerformIdentityResolverSetup::class);
    }

    public function boot(): void
    {
        $this->publishConfig();
    }

    private function publishConfig(): void
    {
        $this->publishes([__DIR__.'/../resources/config/multitenancy.php'], 'config');
    }
}
