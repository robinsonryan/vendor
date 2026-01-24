<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Customize the table names used by the vendor package.
    |
    */
    'tables' => [
        'vendors' => 'vendors',
    ],

    /*
    |--------------------------------------------------------------------------
    | Primary Key Type
    |--------------------------------------------------------------------------
    |
    | The type of primary key to use for vendor records.
    | Options: 'uuid7', 'uuid', 'ulid', 'increments'
    |
    */
    'id_type' => 'uuid7',

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how vendors relate to tenants in your application.
    |
    */
    'tenant' => [
        'enabled' => true,
        'column' => 'tenant_id',
        'scoped' => false,  // Vendors are global by default
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
    |
    | Override default models with your own implementations.
    |
    */
    'models' => [
        'vendor' => \RobinsonRyan\Vendor\Models\Vendor::class,
    ],
];
