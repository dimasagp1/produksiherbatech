<?php

namespace App\Http\Controllers\PPIC;

use App\Exports\MrpExport;
use App\Http\Controllers\Controller;
use App\Models\Bom;
use App\Models\MpsPlan;
use App\Models\MrpCalculation;
use App\Services\MrpCalculationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class MrpController extends Controller
{
    public function index(Request $request)
    {
        $mpsId = $request->input('mps_plan_id');
        $status = $request->input('status', 'all');
        $plans = MpsPlan::with('produk')->orderByDesc('month_year')->get(['id', 'produk_id', 'month_year', 'status']);
        $q = MrpCalculation::with(['mpsPlan.produk', 'materialProduk'])->when($mpsId, fn ($qq, $v) => $qq->where('mps_plan_id', $v))->when($status !== 'all', fn ($qq, $v) => $qq->where('status', $status))->orderByDesc('shortage_qty');
        $rows = $q->paginate(50)->withQueryString();
        $summary = ['critical' => MrpCalculation::when($mpsId, fn ($qq) => $qq->where('mps_plan_id', $mpsId))->where('status', 'critical')->count(), 'warning' => MrpCalculation::when($mpsId, fn ($qq) => $qq->where('mps_plan_id', $mpsId))->where('status', 'warning')->count(), 'ok' => MrpCalculation::when($mpsId, fn ($qq) => $qq->where('mps_plan_id', $mpsId))->where('status', 'ok')->count()];

        return Inertia::render('PPIC/Mrp/Index', ['rows' => $rows, 'plans' => $plans, 'mpsPlanId' => $mpsId ? (int) $mpsId : null, 'status' => $status, 'summary' => $summary]);
    }

    public function detail(MrpCalculation $mrp)
    {
        $mrp->load(['mpsPlan.produk', 'mpsPlan.workCenter', 'materialProduk', 'produk']);
        $bom = $mrp->mpsPlan ? Bom::where('produk_id', $mrp->mpsPlan->produk_id)->where('is_active', true)->first() : null;
        $totalQty = $mrp->mpsPlan ? (int) $mrp->mpsPlan->items()->where('cleaning', false)->get()->sum(fn ($i) => $i->adjusted_qty ?? $i->target_qty) : (float) $mrp->needed_qty;
        $breakdown = $bom ? $bom->getBreakdown((float) $totalQty) : null;

        return Inertia::render('PPIC/Mrp/Detail', ['row' => $mrp, 'breakdown' => $breakdown, 'totalQty' => $totalQty]);
    }

    public function recalculate(Request $request, MrpCalculationService $svc)
    {
        $mpsId = $request->input('mps_plan_id');
        if ($mpsId) {
            $mps = MpsPlan::findOrFail($mpsId);
            $r = $svc->calculate($mps);

            return back()->with('success', "MRP recalculated: {$r['count']} materials, critical {$r['critical']}, warning {$r['warning']}");
        }
        $r = $svc->recalculateAllActive();

        return back()->with('success', "MRP recalculated all: {$r['count']} materials");
    }

    public function export(Request $request)
    {
        $mpsId = $request->input('mps_plan_id');
        $rows = MrpCalculation::with(['mpsPlan.produk', 'materialProduk'])->when($mpsId, fn ($q, $v) => $q->where('mps_plan_id', $v))->get();

        return Excel::download(new MrpExport($rows), 'MRP-'.($mpsId ?? 'all').'.xlsx');
    }
}
