<?php

namespace App\Http\Controllers\SCM;

use App\Http\Controllers\Controller;
use App\Models\Bom;
use App\Models\MaterialSupply;
use App\Models\MaterialSupplyItem;
use App\Models\Produk;
use App\Models\ScmUom;
use App\Models\WeeklyPlan;
use App\Models\WorkCenter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class SupplyController extends Controller
{
    public function index(Request $request)
    {
        $q = MaterialSupply::with(['weeklyPlan.produk', 'workCenter', 'creator', 'deliverer', 'items.produk', 'items.uom'])
            ->when($request->status, fn ($qq, $v) => $v !== 'all' ? $qq->where('status', $v) : $qq)
            ->when($request->search, fn ($qq, $v) => $qq->where('supply_number', 'like', "%{$v}%")->orWhereHas('weeklyPlan', fn ($w) => $w->where('batch_number', 'like', "%{$v}%")))
            ->latest('required_date')->latest('id');
        $supplies = $q->paginate(15)->withQueryString();

        $tomorrow = Carbon::tomorrow()->toDateString();
        $dueTomorrow = MaterialSupply::where('required_date', $tomorrow)->whereIn('status', ['pending', 'late'])->count();
        $late = MaterialSupply::where('status', 'late')->orWhere(function ($qq) {
            $qq->where('status', 'pending')->where('required_date', '<', Carbon::today());
        })->count();
        $total = MaterialSupply::count();
        $delivered = MaterialSupply::whereIn('status', ['delivered', 'partial'])->count();
        $onTime = MaterialSupply::where('status', 'delivered')->whereRaw('DATE(delivered_at) <= required_date')->count();
        $onTimePct = $delivered ? round($onTime / max(1, $delivered) * 100, 1) : 0;
        $completePct = $total ? round(MaterialSupplyItem::sum('delivered_qty') / max(1, MaterialSupplyItem::sum('required_qty')) * 100, 1) : 0;

        return Inertia::render('SCM/Supply/Index', [
            'supplies' => $supplies,
            'filters' => ['search' => $request->search, 'status' => $request->status ?? 'all'],
            'kpi' => ['dueTomorrow' => $dueTomorrow, 'late' => $late, 'total' => $total, 'onTimePct' => $onTimePct, 'completePct' => $completePct],
        ]);
    }

    public function create(Request $request)
    {
        $weeklyPlans = WeeklyPlan::with(['produk', 'workCenter', 'line'])->whereIn('status', ['draft', 'aktif'])->orderByDesc('tanggal')->limit(100)->get(['id', 'batch_number', 'produk_id', 'work_center_id', 'line_id', 'tanggal', 'target_output', 'status']);
        $workCenters = WorkCenter::active()->get(['id', 'code', 'name', 'type']);
        $produks = Produk::where('status_aktif', true)->orderBy('nama_produk')->get(['id', 'kode_produk', 'nama_produk', 'item_type']);
        $uoms = ScmUom::orderBy('code')->get(['id', 'code', 'name']);
        $preselected = null;
        if ($request->weekly_plan_id) {
            $wp = WeeklyPlan::with('produk')->find($request->weekly_plan_id);
            if ($wp) {
                $bom = Bom::where('produk_id', $wp->produk_id)->where('is_active', true)->first();
                $preselected = ['weeklyPlan' => $wp, 'bom' => $bom ? $bom->getBreakdown((float) $wp->target_output) : null];
            }
        }

        return Inertia::render('SCM/Supply/Create', ['weeklyPlans' => $weeklyPlans, 'workCenters' => $workCenters, 'produks' => $produks, 'uoms' => $uoms, 'preselected' => $preselected]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'weekly_plan_id' => 'nullable|exists:weekly_plans,id',
            'work_center_id' => 'nullable|exists:work_centers,id',
            'required_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.produk_id' => 'required|exists:produks,id',
            'items.*.required_qty' => 'required|numeric|min:0.0001',
            'items.*.uom_id' => 'nullable|exists:scm_uoms,id',
        ]);

        $supply = MaterialSupply::create([
            'supply_number' => MaterialSupply::nextNumber(),
            'weekly_plan_id' => $validated['weekly_plan_id'] ?? null,
            'work_center_id' => $validated['work_center_id'] ?? WeeklyPlan::find($validated['weekly_plan_id'])?->work_center_id,
            'required_date' => $validated['required_date'],
            'status' => 'pending',
            'created_by' => auth()->id(),
            'notes' => $validated['notes'] ?? null,
        ]);

        foreach ($validated['items'] as $it) {
            $supply->items()->create([
                'produk_id' => $it['produk_id'],
                'required_qty' => $it['required_qty'],
                'delivered_qty' => 0,
                'uom_id' => $it['uom_id'] ?? null,
            ]);
        }

        return redirect()->route('scm.supply.show', $supply->id)->with('success', 'Supply dibuat — target H-1: '.Carbon::parse($supply->required_date)->subDay()->format('d M Y'));
    }

    public function show(MaterialSupply $supply)
    {
        $supply->load(['weeklyPlan.produk', 'weeklyPlan.line', 'workCenter', 'creator', 'deliverer', 'items.produk', 'items.uom']);

        return Inertia::render('SCM/Supply/Show', ['supply' => $supply]);
    }

    public function markDelivered(Request $request, MaterialSupply $supply)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:material_supply_items,id',
            'items.*.delivered_qty' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        foreach ($validated['items'] as $row) {
            $item = MaterialSupplyItem::where('material_supply_id', $supply->id)->findOrFail($row['id']);
            $item->update(['delivered_qty' => $row['delivered_qty']]);
        }

        $supply->load('items');
        $allDelivered = $supply->items->every(fn ($i) => $i->delivered_qty >= $i->required_qty - 0.0001);
        $anyDelivered = $supply->items->some(fn ($i) => $i->delivered_qty > 0);
        $isLate = Carbon::now()->gt(Carbon::parse($supply->required_date)->endOfDay());
        if ($allDelivered) {
            $status = $isLate ? 'late' : 'delivered';
        } elseif ($anyDelivered) {
            $status = 'partial';
        } else {
            $status = $isLate ? 'late' : 'pending';
        }

        $supply->update([
            'status' => $status,
            'delivered_at' => now(),
            'delivered_by' => auth()->id(),
            'notes' => $validated['notes'] ?? $supply->notes,
        ]);

        return back()->with('success', 'Supply diupdate: '.$status);
    }

    public function kpi()
    {
        $supplies = MaterialSupply::with('items')->get();
        $total = $supplies->count();
        $delivered = $supplies->whereIn('status', ['delivered', 'partial'])->count();
        $onTime = $supplies->filter(fn ($s) => $s->status === 'delivered' && $s->delivered_at && Carbon::parse($s->delivered_at)->lte(Carbon::parse($s->required_date)->endOfDay()))->count();
        $late = $supplies->filter(fn ($s) => $s->status === 'late')->count();
        $onTimePct = $delivered ? round($onTime / $delivered * 100, 1) : 0;
        $completePct = (float) MaterialSupplyItem::sum('required_qty') > 0 ? round(MaterialSupplyItem::sum('delivered_qty') / MaterialSupplyItem::sum('required_qty') * 100, 1) : 0;
        $accuracy = $supplies->isEmpty() ? 0 : round($supplies->filter(fn ($s) => $s->items->every(fn ($i) => abs($i->variance_qty) < 0.01))->count() / max(1, $total) * 100, 1);

        return Inertia::render('SCM/Supply/Kpi', [
            'kpi' => ['total' => $total, 'delivered' => $delivered, 'late' => $late, 'onTimePct' => $onTimePct, 'completePct' => $completePct, 'accuracy' => $accuracy],
            'supplies' => MaterialSupply::with(['weeklyPlan', 'workCenter'])->latest('required_date')->limit(50)->get(),
        ]);
    }
}
