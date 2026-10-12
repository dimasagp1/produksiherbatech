<?php

return [
    'host' => rtrim(env('ODOO_HOST', 'http://localhost:8069'), '/'),
    'db' => env('ODOO_DB', 'odoo_production'),
    'username' => env('ODOO_USERNAME', 'admin@example.com'),
    'api_key' => env('ODOO_API_KEY', ''),
    'timeout' => env('ODOO_TIMEOUT', 15),
    'timeout_connect' => env('ODOO_TIMEOUT_CONNECT', 5),
    'timeout_response' => env('ODOO_TIMEOUT_RESPONSE', 15),
    'verify_ssl' => env('ODOO_VERIFY_SSL', true),
    'sync' => [
        'max_attempts' => env('ODOO_SYNC_MAX_ATTEMPTS', 5),
        'webhook_secret' => env('ODOO_WEBHOOK_SECRET'),
        'mo_poll_minutes' => env('ODOO_MO_POLL_MINUTES', 5),
    ],
    'products' => [
        'domain' => [
            ['active', '=', true],
            '|',
            ['sale_ok', '=', true],
            ['purchase_ok', '=', true],
        ],
        'default_process' => 'mixing',
    ],
    'reject' => [
        'auto_push_on_submit' => env('ODOO_AUTO_PUSH_REJECT', false),
    ],
];
