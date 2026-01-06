<?php

use Sprout\TenancyOptions;

return [

    /*
    |--------------------------------------------------------------------------
    | Multitenancy Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default tenancy, tenant provider and identity
    | resolver for your application.
    | You may change these values as required, but they're a perfect start
    | for most applications.
    |
    */

    'defaults' => [

        'tenancy'  => 'tenants',
        'provider' => 'tenants',
        'resolver' => 'subdomain',

    ],

    /*
    |--------------------------------------------------------------------------
    | Tenancies
    |--------------------------------------------------------------------------
    |
    | Next, you may define every tenancy type for your application.
    | If you only have one type of tenancy within your application, which will
    | be the case for most people, you can leave it at one.
    |
    | All tenancies have a tenant provider, which defines how the
    | tenants are actually retrieved out of your database or other storage
    | system used by the application.
    |
    | Tenancies can also have options, which is an array of options provided
    | by the TenancyOptions class that lets you fine tune the tenancies
    | behaviour.
    |
    */

    'tenancies' => [

        'tenants' => [
            'provider' => 'tenants',
            'options'  => [
                TenancyOptions::hydrateTenantRelation(),
                TenancyOptions::throwIfNotRelated(),
                TenancyOptions::allOverrides(),
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Providers
    |--------------------------------------------------------------------------
    |
    | All tenancies have a tenant provider, which defines how the
    | tenants are actually retrieved out of your database or other storage
    | system used by the application.
    |
    | If you have multiple tenant tables or models, you can configure multiple
    | providers to represent each.
    | These providers may then be assigned to any extra tenancies you have defined.
    |
    | Supported: "database", "eloquent"
    |
    | Caching: You can optionally enable caching for tenant providers to reduce
    | database queries. Cache configuration is per-provider and supports the
    | following options:
    |   - ttl: Cache duration in seconds (null for indefinite)
    |   - store: Cache store name (null for default)
    |
    | Note: The cache store must support tagging (redis, memcached, dynamodb, array).
    |
    */

    'providers' => [

        'tenants' => [
            'driver' => 'eloquent',
            'model'  => \Sprout\Database\Eloquent\Tenant::class,

            // Optional: Enable caching to improve performance
            // 'cache' => [
            //     'ttl'   => 3600, // 1 hour in seconds
            //     'store' => null, // null = default cache store
            // ],
        ],

        // 'backup' => [
        //     'driver' => 'database',
        //     'table'  => 'tenants',
        // ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Identity Resolvers
    |--------------------------------------------------------------------------
    |
    | Where Laravel's auth would have a separate guard for each way that a
    | user can be authenticated in a request (think session, header, etc.),
    | Sprout abstracts this out into identity resolvers.
    | This means that all tenancies within the application can use all
    | configured resolvers.
    |
    | If you have multiple ways that tenant can be identified, say, through
    | subdomain and then a HTTP header for APIs, you can define one for each.
    |
    | There are sensible defaults for each supported driver, though it is
    | recommended that you remove any that you don't need, for simplicity
    | sake.
    |
    | Supported: "subdomain", "header", "path", "cookie" and "session"
    |
    */

    'resolvers' => [

        'subdomain' => [
            'driver'  => 'subdomain',
            'domain'  => env('TENANTED_DOMAIN'),
            'pattern' => '.*',
        ],

        'header' => [
            'driver' => 'header',
            'header' => '{Tenancy}-Identifier',
        ],

        'path' => [
            'driver'  => 'path',
            'segment' => 1,
        ],

        'cookie' => [
            'driver' => 'cookie',
            'cookie' => '{Tenancy}-Identifier',
        ],

        'session' => [
            'driver'  => 'session',
            'session' => 'multitenancy.{tenancy}',
        ],

    ],

];
