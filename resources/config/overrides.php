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
        'driver'    => \Sprout\Core\Overrides\StackedOverride::class,
        'overrides' => [
            \Sprout\Core\Overrides\FilesystemManagerOverride::class,
            \Sprout\Core\Overrides\FilesystemOverride::class,
        ],
    ],

    'job' => [
        'driver' => \Sprout\Core\Overrides\JobOverride::class,
    ],

    'cache' => [
        'driver' => \Sprout\Core\Overrides\CacheOverride::class,
    ],

    'auth' => [
        'driver'    => \Sprout\Core\Overrides\StackedOverride::class,
        'overrides' => [
            \Sprout\Core\Overrides\AuthGuardOverride::class,
            \Sprout\Core\Overrides\AuthPasswordOverride::class,
        ],
    ],

    'cookie' => [
        'driver' => \Sprout\Core\Overrides\CookieOverride::class,
    ],

    'session' => [
        'driver'   => \Sprout\Core\Overrides\SessionOverride::class,
        'database' => false,
    ],
];
