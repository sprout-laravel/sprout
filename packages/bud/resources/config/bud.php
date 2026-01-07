<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Config Stores
    |--------------------------------------------------------------------------
    |
    | This option defines the different config stores, ie, mechanisms that Bud
    | will use to store config.
    | A default implementation for both the 'database' and 'filesystem'
    | drivers are provided.
    | You may change these values as required, but they're a perfect start
    | for most applications.
    |
    */

    'stores' => [

        'database' => [
            'driver'     => 'database',
            'connection' => env('DB_CONNECTION', 'sqlite'),
            'table'      => 'bud_config_store',
        ],

        'filesystem' => [
            'driver'    => 'filesystem',
            'disk'      => env('FILESYSTEM_DISK', 'local'),
            'directory' => 'bud/config',
        ],

    ],

];
