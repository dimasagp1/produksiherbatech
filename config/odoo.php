<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Odoo ERP Integration Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi integrasi dengan Odoo ERP untuk sinkronisasi Produk,
    | Manufacturing Order (MO), dan data Reject (Scrap).
    |
    */

    'host' => rtrim(env('ODOO_HOST', 'http://localhost:8069'), '/'),
    'db' => env('ODOO_DB', 'odoo_production'),
    'username' => env('ODOO_USERNAME', 'admin@example.com'),
    'api_key' => env('ODOO_API_KEY', ''), // Bisa berupa API Key atau Password User Odoo
    'timeout' => env('ODOO_TIMEOUT', 15),
    'verify_ssl' => env('ODOO_VERIFY_SSL', true),

    /*
    | Default product filters & field mapping
    */
    'products' => [
        'domain' => [
            ['active', '=', true],
            // FG sale_ok; RM/PM sale_ok=false tapi purchase_ok=true — prefix OR (format Odoo)
            '|',
            ['sale_ok', '=', true],
            ['purchase_ok', '=', true],
        ],
        'default_process' => 'mixing',
    ],

    /*
    | Scrap / Reject Mapping
    */
    'reject' => [
        'auto_push_on_submit' => env('ODOO_AUTO_PUSH_REJECT', false),
    ],
];
