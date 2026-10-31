<?php

declare(strict_types=1);
use RobinsonRyan\Vendor\Models\Vendor;
use RobinsonRyan\Vendor\Purchasing\Models\PurchaseLine;
use RobinsonRyan\Vendor\Purchasing\Models\VendorDocument;
use RobinsonRyan\Vendor\Purchasing\Models\VendorItem;

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
        'vendor_documents' => 'vendor_documents',
        'purchase_lines' => 'purchase_lines',
        'vendor_items' => 'vendor_items',
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
        'vendor' => Vendor::class,
        'vendor_document' => VendorDocument::class,
        'purchase_line' => PurchaseLine::class,
        'vendor_item' => VendorItem::class,
    ],
];
