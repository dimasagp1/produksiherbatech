<?php

return [
    'odoo_batch_writeback' => env('FEATURE_ODOO_BATCH_WRITEBACK', false),
    'odoo_stock_adjustment' => env('FEATURE_ODOO_STOCK_ADJUSTMENT', false),
    'odoo_mo_webhook' => env('FEATURE_ODOO_MO_WEBHOOK', false),
    'mrp_incoming_supply' => env('FEATURE_MRP_INCOMING_SUPPLY', false),
];
