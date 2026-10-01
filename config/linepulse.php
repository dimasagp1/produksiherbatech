<?php

return [
    'kpi' => [
        'produktivitas_target' => env('KPI_PRODUKTIVITAS_TARGET', 90),
        'oee_target' => env('KPI_OEE_TARGET', 70),
        'yield_target' => env('KPI_YIELD_TARGET', 85),
        'reject_rate_warning' => env('KPI_REJECT_RATE_WARNING', 5),
    ],
    'heat' => [
        'high' => 7000,
        'medium' => 5000,
        'low' => 3000,
    ],
];
