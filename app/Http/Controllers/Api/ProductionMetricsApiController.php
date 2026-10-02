<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HrisProductionSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionMetricsApiController extends Controller
{
    /**
     * Inbound API for Finance & HRIS to pull monthly production and SCM metrics.
     */
    public function getMetrics(Request $request, HrisProductionSyncService $syncService): JsonResponse
    {
        $period = $request->get('period') ?: date('Y-m');
        $payload = $syncService->gatherMonthlyPayload($period);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => "Data produksi dan SCM periode {$period} berhasil diambil.",
            'data' => $payload,
            'variables' => $payload['variables'] ?? [],
            'metrics' => $payload['metrics'] ?? [],
        ]);
    }

    /**
     * Health check / ping endpoint.
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Sistem Produksi & SCM API online dan terhubung.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
