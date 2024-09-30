<?php
declare(strict_types=1);

namespace Sprout;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Application;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Sprout\Contracts\TenantHasResources;
use Sprout\Events\CurrentTenantChanged;
use Sprout\Http\Middleware\TenantRoutes;
use Sprout\Http\RouterMethods;
use Sprout\Listeners\CleanupLaravelServices;
use Sprout\Listeners\IdentifyTenantOnRouting;
use Sprout\Listeners\PerformIdentityResolverSetup;
use Sprout\Listeners\SetCurrentTenantContext;
use Sprout\Listeners\SetCurrentTenantForJob;
use Sprout\Managers\IdentityResolverManager;
use Sprout\Managers\ProviderManager;
use Sprout\Managers\TenancyManager;
use Sprout\Support\StorageHelper;

class SproutServiceProvider extends ServiceProvider
{
    private Sprout $sprout;

    public function register(): void
    {
        $this->handleCoreConfig();
        $this->registerSprout();
        $this->registerManagers();
        $this->registerMiddleware();
        $this->registerRouteMixin();
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

        // Register the tenancy manager
        $this->app->singleton(TenancyManager::class, function ($app) {
            return new TenancyManager($app, $app->make(ProviderManager::class));
        });

        // Alias the managers with simple names
        $this->app->alias(ProviderManager::class, 'sprout.providers');
        $this->app->alias(IdentityResolverManager::class, 'sprout.resolvers');
        $this->app->alias(TenancyManager::class, 'sprout.tenancies');
    }

    private function registerMiddleware(): void
    {
        /** @var \Illuminate\Routing\Router $router */
        $router = $this->app->make(Router::class);

        // Alias the basic tenant middleware
        $router->aliasMiddleware(TenantRoutes::ALIAS, TenantRoutes::class);
    }

    protected function registerRouteMixin(): void
    {
        Router::mixin(new RouterMethods());
    }

    public function boot(): void
    {
        $this->publishConfig();
        $this->registerEventListeners();
        $this->registerServiceOverrides();
    }

    private function publishConfig(): void
    {
        $this->publishes([__DIR__ . '/../resources/config/multitenancy.php' => config_path('multitenancy.php')], 'config');
    }

    private function registerEventListeners(): void
    {
        /** @var \Illuminate\Contracts\Events\Dispatcher $events */
        $events = $this->app->make(Dispatcher::class);

        // If we should be listening for routing
        if ($this->sprout->shouldListenForRouting()) {
            $events->listen(RouteMatched::class, IdentifyTenantOnRouting::class);
        }

        $events->listen(CurrentTenantChanged::class, SetCurrentTenantContext::class);
        $events->listen(CurrentTenantChanged::class, PerformIdentityResolverSetup::class);
        $events->listen(CurrentTenantChanged::class, CleanupLaravelServices::class);
        $events->listen(JobProcessing::class, SetCurrentTenantForJob::class);
    }

    private function registerServiceOverrides(): void
    {
        // If we're providing a tenanted override for Laravels filesystem/storage
        // service, we'll do that here
        if ($this->sprout->config('services.storage', false)) {
            $filesystemManager = $this->app->make(FilesystemManager::class);
            $filesystemManager->extend('sprout', StorageHelper::creator($this->sprout, $filesystemManager));
        }
    }
}
