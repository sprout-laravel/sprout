<?php

use Sprout\TenancyOptions;

return [

    'defaults' => [

        'tenancy'  => 'tenants',
        'provider' => 'tenants',
        'resolver' => 'subdomain',

    ],

    'tenancies' => [

        'tenants' => [
            'provider' => 'tenants',
            'options'  => [
                TenancyOptions::hydrateTenantRelation(),
                TenancyOptions::checkForRelationWithTenant(),
                TenancyOptions::throwIfNotRelated(),
            ],
        ],

    ],

    'providers' => [

        'tenants' => [
            'driver' => 'eloquent',
            'model'  => \App\Tenant::class,
        ],

        'backup' => [
            'driver' => 'database',
            'table'  => \App\Tenant::class,
        ],

    ],

    'resolvers' => [

        'subdomain' => [
            'driver'  => 'subdomain',
            'domain'  => env('TENANTED_DOMAIN'),
            'pattern' => '.*',
        ],

        'path' => [
            'driver'  => 'path',
            'segment' => 1,
        ],

        'header' => [
            'driver' => 'header',
        ],

        'cookie' => [
            'driver' => 'cookie',
        ],

        'session' => [
            'driver'  => 'session',
            'session' => 'multitenancy.{tenancy}',
        ],

    ],

];
