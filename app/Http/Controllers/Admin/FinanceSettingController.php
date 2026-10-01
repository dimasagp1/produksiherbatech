<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LaporanHarian;
use App\Models\Setting;
use App\Services\FinanceProductionSyncService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FinanceSettingController extends Controller
{
    /**
     * Tampilkan halaman Pengaturan Integrasi API Finance Monitoring.
     */
    public function index(FinanceProductionSyncService $syncService)
    {
        $settings = $syncService->getFinanceConfig();
        $currentPeriod = date('Y-m');

        $laporanCount = LaporanHarian::whereYear('tanggal', date('Y'))
            ->whereMonth('tanggal', date('m'))
            ->count();

        $avgOee = (float) LaporanHarian::whereYear('tanggal', date('Y'))
            ->whereMonth('tanggal', date('m'))
            ->avg('oee_persen');

        $previewPayload = $syncService->gatherMonthlyPayload($currentPeriod);

        return Inertia::render('Admin/Settings/Finance', [
            'settings' => $settings,
            'stats' => [
                'current_period' => $currentPeriod,
                'monthly_reports' => $laporanCount,
                'avg_oee' => round($avgOee, 2),
                'endpoint' => rtrim($settings['url'], '/').'/api/v1/finance/production-feed',
                'summary' => $previewPayload['summary'] ?? [],
                'variance_count' => count($previewPayload['stock_opname_variance'] ?? []),
                'products_count' => count($previewPayload['products_output'] ?? []),
            ],
        ]);
    }

    /**
     * Simpan pengaturan URL API dan Key Finance.
     */
    public function update(Request $request)
    {
        $rawUrl = $this->cleanUrl($request->input('finance_api_url', ''));
        $request->merge(['finance_api_url' => $rawUrl]);

        $validated = $request->validate([
            'finance_api_url' => 'required|url',
            'finance_api_key' => 'required|string|max:255',
            'finance_auto_sync' => 'nullable|boolean',
        ]);

        Setting::updateOrCreate(['key' => 'finance_api_url'], ['value' => $this->cleanUrl($validated['finance_api_url']), 'group' => 'finance']);
        Setting::updateOrCreate(['key' => 'finance_api_key'], ['value' => trim($validated['finance_api_key']), 'group' => 'finance']);
        Setting::updateOrCreate(['key' => 'finance_auto_sync'], ['value' => $request->boolean('finance_auto_sync') ? '1' : '0', 'group' => 'finance']);

        return back()->with('success', 'Pengaturan API Finance berhasil disimpan!');
    }

    /**
     * Test koneksi HTTP ke Finance Monitoring.
     */
    public function testConnection(Request $request, FinanceProductionSyncService $syncService)
    {
        $rawUrl = $request->input('finance_api_url');
        $url = $rawUrl ? $this->cleanUrl($rawUrl) : null;
        $apiKey = $request->input('finance_api_key') ? trim($request->input('finance_api_key')) : null;

        $startTime = microtime(true);
        $result = $syncService->testConnection($url, $apiKey);
        $latencyMs = round((microtime(true) - $startTime) * 1000);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'latency_ms' => $latencyMs,
            'endpoint' => ($url ?: $syncService->getFinanceConfig()['url']).'/api/v1/finance/published-reports',
        ], $result['success'] ? 200 : 400);
    }

    /**
     * Bersihkan format URL jika ada duplikasi atau trailing slash.
     */
    private function cleanUrl(string $url): string
    {
        $url = trim($url);
        if (empty($url)) {
            return 'http://localhost:8000';
        }

        if (preg_match('/(https?:\/\/[^\/]+?)https?:\/\//i', $url, $m)) {
            $url = $m[1];
        }

        if (! preg_match('/^https?:\/\//i', $url)) {
            $url = 'http://'.$url;
        }

        return rtrim($url, '/');
    }

    /**
     * Jalankan Sinkronisasi Data Produksi ke Finance Sekarang.
     */
    public function syncNow(Request $request, FinanceProductionSyncService $syncService)
    {
        $period = $request->input('period', date('Y-m'));
        $result = $syncService->pushToFinance($period);

        if ($result['success']) {
            return back()->with('success', '✓ '.$result['message']);
        }

        return back()->with('error', '❌ '.$result['message']);
    }
}
