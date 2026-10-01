<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LaporanHarian;
use App\Models\Setting;
use App\Services\HrisProductionSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class HrisSettingController extends Controller
{
    /**
     * Tampilkan halaman Pengaturan Integrasi API HRIS Sasaran Mutu.
     */
    public function index()
    {
        $settings = [
            'hris_app_url' => Setting::where('key', 'hris_app_url')->value('value') ?: env('HRIS_API_URL', 'http://backuphris.test'),
            'hris_api_key' => Setting::where('key', 'hris_api_key')->value('value') ?: env('HRIS_API_KEY', 'bsc_sec_live_9f82d1c6b3e44a7b'),
            'hris_auto_sync' => (bool) (Setting::where('key', 'hris_auto_sync')->value('value') ?? true),
        ];

        $currentPeriod = date('Y-m');
        $laporanCount = LaporanHarian::whereYear('tanggal', date('Y'))
            ->whereMonth('tanggal', date('m'))
            ->count();

        $avgOee = (float) LaporanHarian::whereYear('tanggal', date('Y'))
            ->whereMonth('tanggal', date('m'))
            ->avg('oee_persen');

        return Inertia::render('Admin/Settings/Hris', [
            'settings' => $settings,
            'stats' => [
                'current_period' => $currentPeriod,
                'monthly_reports' => $laporanCount,
                'avg_oee' => round($avgOee, 2),
                'endpoint' => rtrim($settings['hris_app_url'], '/').'/api/v1/inbound/production-metrics',
            ],
        ]);
    }

    /**
     * Simpan pengaturan URL API dan Key HRIS.
     */
    public function update(Request $request)
    {
        $rawUrl = $this->cleanUrl($request->input('hris_app_url', ''));
        $request->merge(['hris_app_url' => $rawUrl]);

        $validated = $request->validate([
            'hris_app_url' => 'required|url',
            'hris_api_key' => 'required|string|max:255',
            'hris_auto_sync' => 'nullable|boolean',
        ]);

        Setting::updateOrCreate(['key' => 'hris_app_url'], ['value' => $this->cleanUrl($validated['hris_app_url'])]);
        Setting::updateOrCreate(['key' => 'hris_api_key'], ['value' => trim($validated['hris_api_key'])]);
        Setting::updateOrCreate(['key' => 'hris_auto_sync'], ['value' => $request->boolean('hris_auto_sync') ? '1' : '0']);

        return back()->with('success', 'Pengaturan API HRIS berhasil disimpan!');
    }

    /**
     * Test koneksi HTTP ke HRIS Sasaran Mutu.
     */
    public function testConnection(Request $request)
    {
        $rawUrl = $request->input('hris_app_url') ?: Setting::where('key', 'hris_app_url')->value('value') ?: env('HRIS_API_URL', 'http://backuphris.test');
        $url = $this->cleanUrl($rawUrl);
        $apiKey = trim($request->input('hris_api_key', Setting::where('key', 'hris_api_key')->value('value') ?: env('HRIS_API_KEY', 'bsc_sec_live_9f82d1c6b3e44a7b')));

        $endpoint = "{$url}/api/v1/inbound/production-metrics";

        $startTime = microtime(true);
        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'X-API-KEY' => $apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(6)
                ->post($endpoint, [
                    'period' => date('Y-m'),
                    'source_system' => 'TEST_PING_SISTEM_PRODUKSI',
                    'dept_code' => 'PRO',
                    'metrics' => [
                        'Ping' => 1,
                    ],
                ]);

            $latencyMs = round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Koneksi ke API HRIS berhasil terhubung dengan sempurna (HTTP 200)!',
                    'latency_ms' => $latencyMs,
                    'endpoint' => $endpoint,
                    'hris_response' => $response->json(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal terhubung ke HRIS. Status: HTTP '.$response->status().' - '.($response->json('message') ?? 'Akses ditolak / endpoint tidak ditemukan'),
                'latency_ms' => $latencyMs,
                'endpoint' => $endpoint,
            ], 400);
        } catch (\Throwable $e) {
            $latencyMs = round((microtime(true) - $startTime) * 1000);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghubungi server HRIS ('.$url.'): '.$e->getMessage(),
                'latency_ms' => $latencyMs,
                'endpoint' => $endpoint,
            ], 500);
        }
    }

    /**
     * Bersihkan format URL jika ada duplikasi atau trailing slash.
     */
    private function cleanUrl(string $url): string
    {
        $url = trim($url);
        if (empty($url)) {
            return 'http://backuphris.test';
        }

        // Tangani jika user tidak sengaja mem-paste dua kali (cth: http://backuphris.testhttp://backuphris.test)
        if (preg_match('/(https?:\/\/[^\/]+?)https?:\/\//i', $url, $m)) {
            $url = $m[1];
        }

        if (! preg_match('/^https?:\/\//i', $url)) {
            $url = 'http://'.$url;
        }

        return rtrim($url, '/');
    }

    /**
     * Jalankan Sinkronisasi Metrik Produksi Sekarang.
     */
    public function syncNow(Request $request, HrisProductionSyncService $syncService)
    {
        $period = $request->input('period', date('Y-m'));
        $result = $syncService->syncProductionMetricsToHris($period);

        if ($result['success']) {
            return back()->with('success', '✓ '.$result['message']);
        }

        return back()->with('error', '❌ '.$result['message']);
    }
}
