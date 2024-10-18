<?php
declare(strict_types=1);

namespace Sprout;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\ServiceOverride;
use Sprout\Events\CurrentTenantChanged;
use Sprout\Http\Middleware\TenantRoutes;
use Sprout\Http\RouterMethods;
use Sprout\Listeners\IdentifyTenantOnRouting;
use Sprout\Listeners\SetCurrentTenantForJob;
use Sprout\Managers\IdentityResolverManager;
use Sprout\Managers\ProviderManager;
use Sprout\Managers\TenancyManager;

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
        $this->registerServiceOverrides();
        $this->registerEventListeners();
        $this->registerTenancyBootstrappers();
        $this->bootServiceOverrides();
    }

    private function publishConfig(): void
    {
        $this->publishes([__DIR__ . '/../resources/config/multitenancy.php' => config_path('multitenancy.php')], ['config', 'sprout-config']);
    }

    private function registerServiceOverrides(): void
    {
        /** @var array<class-string<\Sprout\Contracts\ServiceOverride>> $overrides */
        $overrides = config('sprout.services', []);

        foreach ($overrides as $overrideClass) {
            if (! is_subclass_of($overrideClass, ServiceOverride::class)) {
                throw new InvalidArgumentException('Provided class [' . $overrideClass . '] does not implement ' . ServiceOverride::class);
            }

            /** @var \Sprout\Contracts\ServiceOverride $override */
            $override = $this->app->make($overrideClass);

            $this->sprout->addOverride($override);
        }
    }

    private function registerEventListeners(): void
    {
        /** @var \Illuminate\Contracts\Events\Dispatcher $events */
        $events = $this->app->make(Dispatcher::class);

        // If we should be listening for routing
        if ($this->sprout->shouldListenForRouting()) {
            $events->listen(RouteMatched::class, IdentifyTenantOnRouting::class);
        }

        $events->listen(JobProcessing::class, SetCurrentTenantForJob::class);
    }

    private function registerTenancyBootstrappers(): void
    {
        /** @var \Illuminate\Contracts\Events\Dispatcher $events */
        $events = $this->app->make(Dispatcher::class);

        /** @var array<class-string> $bootstrappers */
        $bootstrappers = config('sprout.bootstrappers', []);

        foreach ($bootstrappers as $bootstrapper) {
            $events->listen(CurrentTenantChanged::class, $bootstrapper);
        }
    }

    private function bootServiceOverrides(): void
    {
        foreach ($this->sprout->getOverrides() as $override) {
            if ($override instanceof BootableServiceOverride) {
                $override->boot($this->app, $this->sprout);
            }
        }
    }
}
