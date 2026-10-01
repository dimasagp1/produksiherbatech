<?php

use App\Http\Controllers\Api\ProductionMetricsApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/production')->middleware(['api.key'])->group(function () {
    Route::get('/metrics', [ProductionMetricsApiController::class, 'getMetrics']);
    Route::get('/ping', [ProductionMetricsApiController::class, 'ping']);
});
