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
            return back()->with('error', 'Sync Odoo MO dinonaktifkan (setting odoo_mo_sync_enabled = 0).');
        }

        try {
            $result = $odoo->syncManufacturingOrders();

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

            return back()->with('success', $message)->with('moSync', $result);
        } catch (\Exception $e) {
            return back()->with('error', 'Sync MO gagal: '.$e->getMessage());
        }
    }

    /**
     * Preview Odoo MO list before applying sync (JSON).
     */
    public function moList(Request $request, OdooService $odoo)
    {
        try {
            $mos = $odoo->fetchManufacturingOrders();

            return response()->json(['success' => true, 'data' => $mos]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
