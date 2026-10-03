<?php

namespace App\Http\Controllers\SCM;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\OdooService;
use Illuminate\Http\Request;

class OdooMoSyncController extends Controller
{
    /**
     * Manual on-demand pull of Odoo Manufacturing Orders into weekly_plans.
     */
    public function sync(Request $request, OdooService $odoo)
    {
        if (! filter_var(Setting::get('odoo_mo_sync_enabled', '1'), FILTER_VALIDATE_BOOLEAN)) {
            $err = 'Sync Odoo MO dinonaktifkan (setting odoo_mo_sync_enabled = 0).';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $err], 422);
            }

            return back()->with('error', $err);
        }

        $selectedIds = $request->input('selected_ids');
        if (is_array($selectedIds) && empty($selectedIds)) {
            $err = 'Silakan pilih setidaknya satu Manufacturing Order (MO) untuk disinkronkan.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $err], 422);
            }

            return back()->withErrors(['mo' => $err]);
        }

        try {
            $result = $odoo->syncManufacturingOrders(is_array($selectedIds) ? $selectedIds : null);

            $message = sprintf(
                'Sync MO selesai: %d dibuat, %d diperbarui, %d dibatalkan, %d dilewati',
                $result['created'],
                $result['updated'],
                $result['cancelled'],
                $result['skipped']
            );

            if (! empty($result['errors'])) {
                $message .= ' — '.count($result['errors']).' error (lihat log)';
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'result' => $result,
                ]);
            }

            return back()->with('success', $message)->with('moSync', $result);
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Sync MO gagal: '.$e->getMessage()], 500);
            }

            return back()->with('error', 'Sync MO gagal: '.$e->getMessage());
        }
    }

    /**
     * Preview Odoo MO list before applying sync (JSON).
     */
    public function moList(Request $request, OdooService $odoo)
    {
        try {
            $mos = $odoo->fetchManufacturingOrdersPreview();

            return response()->json([
                'success' => true,
                'data' => $mos,
                'count' => count($mos),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar MO dari Odoo: '.$e->getMessage(),
            ], 500);
        }
    }
}
