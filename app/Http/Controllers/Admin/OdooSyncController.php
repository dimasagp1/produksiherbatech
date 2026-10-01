<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Produk;
use App\Models\Setting;
use App\Services\OdooService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OdooSyncController extends Controller
{
    /**
     * Tampilkan halaman Pengaturan Integrasi Odoo
     */
    public function settings(OdooService $odooService)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya memiliki akses baca.');
        }

        $settings = [
            'odoo_host' => Setting::get('odoo_host', config('odoo.host', 'http://localhost:8069')),
            'odoo_db' => Setting::get('odoo_db', config('odoo.db', 'odoo_production')),
            'odoo_username' => Setting::get('odoo_username', config('odoo.username', '')),
            'odoo_api_key' => Setting::get('odoo_api_key', config('odoo.api_key', '')),
            'odoo_timeout' => (int) Setting::get('odoo_timeout', config('odoo.timeout', 15)),
            'odoo_auto_push_reject' => (bool) Setting::get('odoo_auto_push_reject', config('odoo.reject.auto_push_on_submit', false)),
        ];

        $syncedProductsCount = Produk::whereNotNull('odoo_id')->count();
        $totalProductsCount = Produk::count();
        $lastSyncedProduct = Produk::whereNotNull('odoo_synced_at')->latest('odoo_synced_at')->first();

        return Inertia::render('Admin/Settings/Odoo', [
            'settings' => $settings,
            'stats' => [
                'synced_products' => $syncedProductsCount,
                'total_products' => $totalProductsCount,
                'last_synced_at' => $lastSyncedProduct?->odoo_synced_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Simpan pengaturan Odoo dari form Web UI
     */
    public function updateSettings(Request $request, OdooService $odooService)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya memiliki akses baca.');
        }

        $validated = $request->validate([
            'odoo_host' => 'required|url',
            'odoo_db' => 'required|string|max:100',
            'odoo_username' => 'required|string|max:150',
            'odoo_api_key' => 'nullable|string|max:255',
            'odoo_timeout' => 'nullable|integer|min:5|max:60',
            'odoo_auto_push_reject' => 'nullable|boolean',
        ]);

        Setting::set('odoo_host', rtrim($validated['odoo_host'], '/'), 'odoo');
        Setting::set('odoo_db', trim($validated['odoo_db']), 'odoo');
        Setting::set('odoo_username', trim($validated['odoo_username']), 'odoo');

        if (isset($validated['odoo_api_key']) && $validated['odoo_api_key'] !== '') {
            Setting::set('odoo_api_key', trim($validated['odoo_api_key']), 'odoo');
        }

        Setting::set('odoo_timeout', $validated['odoo_timeout'] ?? 15, 'odoo');
        Setting::set('odoo_auto_push_reject', ! empty($validated['odoo_auto_push_reject']) ? '1' : '0', 'odoo');

        $odooService->reloadConfig();

        return redirect()->back()->with('success', 'Pengaturan Odoo ERP berhasil disimpan!');
    }

    /**
     * Tes koneksi ke Odoo (AJAX)
     */
    public function testConnection(Request $request, OdooService $odooService)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya memiliki akses baca.');
        }

        $odooService->reloadConfig();

        $customConfig = [];
        if ($request->filled('odoo_host')) {
            $customConfig['odoo_host'] = $request->input('odoo_host');
        }
        if ($request->filled('odoo_db')) {
            $customConfig['odoo_db'] = $request->input('odoo_db');
        }
        if ($request->filled('odoo_username')) {
            $customConfig['odoo_username'] = $request->input('odoo_username');
        }
        if ($request->filled('odoo_api_key')) {
            $customConfig['odoo_api_key'] = $request->input('odoo_api_key');
        }
        if ($request->filled('odoo_timeout')) {
            $customConfig['odoo_timeout'] = (int) $request->input('odoo_timeout');
        }

        $result = $odooService->testConnection(! empty($customConfig) ? $customConfig : null);

        return response()->json($result);
    }

    /**
     * Preview list of products from Odoo for user selection modal
     */
    public function previewProducts(OdooService $odooService)
    {
        try {
            $odooService->reloadConfig();
            $products = $odooService->fetchProductsPreview();

            return response()->json([
                'success' => true,
                'products' => $products,
                'count' => count($products),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar produk dari Odoo: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sinkronisasi Produk dari Odoo
     */
    public function syncProducts(Request $request, OdooService $odooService)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya memiliki akses baca.');
        }

        $selectedIds = $request->input('selected_ids');
        if (is_array($selectedIds) && empty($selectedIds)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Silakan pilih setidaknya satu produk untuk disinkronkan.',
                ], 422);
            }

            return redirect()->back()->withErrors(['odoo' => 'Silakan pilih setidaknya satu produk.']);
        }

        try {
            $odooService->reloadConfig();
            $stats = $odooService->syncProducts(is_array($selectedIds) ? $selectedIds : null);

            $message = "Sinkronisasi Odoo berhasil! {$stats['total_from_odoo']} produk diproses ({$stats['created']} baru dibuat, {$stats['updated']} diperbarui).";

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'stats' => $stats,
                ]);
            }

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal sinkronisasi Odoo: '.$e->getMessage(),
                ], 500);
            }

            return redirect()->back()->withErrors(['odoo' => 'Gagal sinkronisasi Odoo: '.$e->getMessage()]);
        }
    }

    /**
     * Reset / Hapus Massal produk hasil sinkronisasi Odoo
     */
    public function resetProducts(Request $request)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya memiliki akses baca.');
        }

        try {
            $odooProducts = Produk::whereNotNull('odoo_id')->get();
            $deletedCount = 0;
            $unlinkedCount = 0;

            foreach ($odooProducts as $produk) {
                // Check if product is used in transactions
                $isUsed = $produk->laporanHarians()->exists() || $produk->weeklyPlans()->exists();

                if ($isUsed) {
                    // Unlink instead of delete so historical transactions stay valid
                    $produk->update([
                        'odoo_id' => null,
                        'odoo_synced_at' => null,
                    ]);
                    $unlinkedCount++;
                } else {
                    $produk->forceDelete();
                    $deletedCount++;
                }
            }

            // Also clean up any soft-deleted products with odoo_id
            Produk::onlyTrashed()->whereNotNull('odoo_id')->forceDelete();

            $message = "Reset produk Odoo berhasil! {$deletedCount} produk dihapus".($unlinkedCount > 0 ? " dan {$unlinkedCount} produk dilepas tautan Odoo (karena memiliki data transaksi)." : '.');

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'deleted' => $deletedCount,
                    'unlinked' => $unlinkedCount,
                ]);
            }

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mereset produk Odoo: '.$e->getMessage(),
                ], 500);
            }

            return redirect()->back()->withErrors(['odoo' => 'Gagal mereset produk Odoo: '.$e->getMessage()]);
        }
    }

    /**
     * Ambil data Scrap / Reject dari Odoo ERP untuk monitoring
     */
    public function getScraps(OdooService $odooService)
    {
        try {
            $odooService->reloadConfig();
            $scraps = $odooService->fetchScrapsFromOdoo(100);

            return response()->json([
                'success' => true,
                'scraps' => $scraps,
                'count' => count($scraps),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data scrap dari Odoo: '.$e->getMessage(),
            ], 500);
        }
    }
}
