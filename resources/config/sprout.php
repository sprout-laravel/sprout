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
    | Service Overrides
    |--------------------------------------------------------------------------
    |
    | This value sets which core Laravel services Sprout should override.
    |
    | Setting a service to false will disable its tenant-specific
    | configuration/settings, and leave them using the default.
    |
    */

    'services' => [
        // This will enable the 'sprout' driver for the filesystem disks,
        // allowing for the creation of tenant scoped disks.
        'storage'  => true,

        // This will enable the overwriting of the default settings for cookies.
        // Each identity resolver may have affected different settings.
        'cookies'  => true,

        // This will enable the overwriting of the default settings for sessions.
        // Each identity resolver may have affected different settings.
        'sessions' => true,
    ],

];
