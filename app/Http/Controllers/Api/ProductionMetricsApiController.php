<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FinanceProductionSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionMetricsApiController extends Controller
{
    /**
     * Inbound API for Finance & HRIS to pull monthly production metrics.
     */
    public function getMetrics(Request $request, FinanceProductionSyncService $syncService): JsonResponse
    {
        $period = $request->get('period') ?: date('Y-m');
        $payload = $syncService->gatherMonthlyPayload($period);

        return response()->json([
            'status' => 'success',
            'message' => "Data produksi periode {$period} berhasil diambil.",
            'data' => $payload,
        ]);
    }

    /**
     * Health check / ping endpoint.
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Sistem Produksi API online dan terhubung.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
