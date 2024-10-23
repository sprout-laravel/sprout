<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Should Sprout listen for routing?
    |--------------------------------------------------------------------------
    |
    | This value decides whether Sprout listens for the RouteMatched event to
    | identify tenants.
    |
    | Setting it to false will disable the pre-middleware identification,
    | which in turn will make tenant-aware dependency injection no longer
    | functionality.
    |
    */

    'listen_for_routing' => true,

    /*
    |--------------------------------------------------------------------------
    | The event listeners used to bootstrap a tenancy
    |--------------------------------------------------------------------------
    |
    | This value contains all the listeners that should be run for the
    | \Sprout\Events\CurrentTenantChanged event to bootstrap a tenancy.
    |
    */

    'bootstrappers' => [
        // Set the current tenant within the Laravel context
        \Sprout\Listeners\SetCurrentTenantContext::class,
        // Calls the setup method on the current identity resolver
        \Sprout\Listeners\PerformIdentityResolverSetup::class,
        // Performs any clean-up from the previous tenancy
        \Sprout\Listeners\CleanupServiceOverrides::class,
        // Sets up service overrides for the current tenancy
        \Sprout\Listeners\SetupServiceOverrides::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Service Overrides
    |--------------------------------------------------------------------------
    |
    | This is an array of service override classes.
    | These classes will be instantiated and automatically run when relevant.
    |
    */

    'services' => [
        // This will override the storage by introducing a 'sprout' driver
        // that wraps any other storage drive in a tenant resource subdirectory.
        \Sprout\Overrides\StorageOverride::class,
        // This will override the cache by introducing a 'sprout' driver
        // that adds a prefix to cache stores for the current tenant.
        \Sprout\Overrides\CacheOverride::class,
        // This is a simple override that removes all currently resolved
        // guards to prevent user auth leaking.
        \Sprout\Overrides\AuthOverride::class,
        // This will override the cookie settings so that all created cookies
        // are specific to the tenant.
        \Sprout\Overrides\CookieOverride::class,
        // This will override the session by introducing a 'sprout' driver
        // that wraps any other session store.
        \Sprout\Overrides\SessionOverride::class,
    ],

];
