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
            \Sprout\Overrides\Filesystem\FilesystemManagerOverride::class,
            \Sprout\Overrides\Filesystem\FilesystemOverride::class,
        ],
    ],

    'job' => [
        'driver' => \Sprout\Overrides\Job\JobOverride::class,
    ],

    'cache' => [
        'driver' => \Sprout\Overrides\Cache\CacheOverride::class,
    ],

    'auth' => [
        'driver'    => \Sprout\Core\Overrides\StackedOverride::class,
        'overrides' => [
            \Sprout\Overrides\Auth\AuthGuardOverride::class,
            \Sprout\Overrides\Auth\AuthPasswordOverride::class,
        ],
    ],

    'cookie' => [
        'driver' => \Sprout\Overrides\Cookie\CookieOverride::class,
    ],

    'session' => [
        'driver'   => \Sprout\Overrides\Session\SessionOverride::class,
        'database' => false,
    ],
];
