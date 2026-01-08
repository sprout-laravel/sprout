<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled hooks
    |--------------------------------------------------------------------------
    |
    | This value contains an array of resolution hooks that should be enabled.
    | The handling of each hook is different, but if a hook is missing from
    | here, some things, such as listeners, may not be registered.
    |
    */

    'hooks' => [
        // \Sprout\Core\Support\ResolutionHook::Booting,
        \Sprout\Core\Support\ResolutionHook::Routing,
        \Sprout\Core\Support\ResolutionHook::Middleware,
    ],

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
        \Sprout\Core\Listeners\SetCurrentTenantContext::class,
        // Calls the setup method on the current identity resolver
        \Sprout\Core\Listeners\PerformIdentityResolverSetup::class,
        // Performs any clean-up from the previous tenancy
        \Sprout\Core\Listeners\CleanupServiceOverrides::class,
        // Sets up service overrides for the current tenancy
        \Sprout\Core\Listeners\SetupServiceOverrides::class,
        // Refresh anything that's tenant-aware
        \Sprout\Core\Listeners\RefreshTenantAwareDependencies::class,
    ],

];
