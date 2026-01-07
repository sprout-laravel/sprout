<?php

/*
|--------------------------------------------------------------------------
| Service Overrides
|--------------------------------------------------------------------------
|
| This config file provides the config for the different service overrides
| registered by Sprout.
| Service overrides are registered against a "service", which is an arbitrary
| string value, used to prevent multiple overrides for a single service.
|
| All service overrides should have a "driver" which should contain an FQN
| for a class that implements the ServiceOverride interface.
| Any other config options will depend on the individual service override
| driver.
|
*/

return [

    'filesystem' => [
        'driver'    => \Sprout\Overrides\StackedOverride::class,
        'overrides' => [
            \Sprout\Overrides\FilesystemManagerOverride::class,
            \Sprout\Overrides\FilesystemOverride::class,
        ],
    ],

    'job' => [
        'driver' => \Sprout\Overrides\JobOverride::class,
    ],

    'cache' => [
        'driver' => \Sprout\Overrides\CacheOverride::class,
    ],

    'auth' => [
        'driver'    => \Sprout\Overrides\StackedOverride::class,
        'overrides' => [
            \Sprout\Overrides\AuthGuardOverride::class,
            \Sprout\Overrides\AuthPasswordOverride::class,
        ],
    ],

    'cookie' => [
        'driver' => \Sprout\Overrides\CookieOverride::class,
    ],

    'session' => [
        'driver'   => \Sprout\Overrides\SessionOverride::class,
        'database' => false,
    ],
];
