<?php

return [
    'shift_minutes' => env('SHIFT_MINUTES'),
    'planned_downtime' => env('PLANNED_DOWNTIME'),
    'usage_tolerance_pct' => env('USAGE_TOLERANCE_PCT'),
    'benchmark_pcs_per_mp' => 2000,
    'sync_alert_pending_min' => env('SYNC_ALERT_PENDING_MIN'),
];
