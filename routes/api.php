<?php

use App\Http\Controllers\Api\ProductionMetricsApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/production')->middleware(['api.key'])->group(function () {
    Route::get('/metrics', [ProductionMetricsApiController::class, 'getMetrics']);
    Route::get('/ping', [ProductionMetricsApiController::class, 'ping']);
});

Route::prefix('v1/finance')->middleware(['api.key'])->group(function () {
    Route::get('/production-feed', function () {
        return response()->json([
            'status' => 'success',
            'message' => 'Finance Production Feed API online dan siap menerima data.',
            'count' => 0,
            'data' => [],
        ]);
    });
});
