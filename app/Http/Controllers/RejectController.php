<?php

namespace App\Http\Controllers;

use App\Models\LaporanHarian;
use App\Models\Produk;
use App\Models\RejectDetail;
use App\Services\OdooService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RejectController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $search = $request->input('search', '');
        $filter = $request->input('filter', 'all');
        $month = $request->input('month', ''); // e.g. '2026-10'
        $startDate = $request->input('start_date', '');
        $endDate = $request->input('end_date', '');
        $produkId = $request->input('produk_id', '');
        $sortBy = $request->input('sort_by', 'tanggal_desc');

        // 1. Laporan Harian (Produksi)
        $query = LaporanHarian::with(['produk', 'mesin', 'line', 'user', 'rejectDetails.creator'])
            ->withSum('rejectDetails as total_reject', 'jumlah');

        if ($filter === 'has_reject') {
            $query->has('rejectDetails');
        } elseif ($filter === 'odoo') {
            $query->whereHas('rejectDetails', fn ($q) => $q->whereNotNull('odoo_scrap_id'));
        }

        // Filter per Bulan (YYYY-MM)
        if (! empty($month)) {
            $parts = explode('-', $month);
            if (count($parts) === 2) {
                $query->whereYear('tanggal', (int) $parts[0])
                    ->whereMonth('tanggal', (int) $parts[1]);
            }
        }

        // Filter Rentang Tanggal
        if (! empty($startDate)) {
            $query->whereDate('tanggal', '>=', $startDate);
        }
        if (! empty($endDate)) {
            $query->whereDate('tanggal', '<=', $endDate);
        }

        // Filter Produk / Item
        if (! empty($produkId)) {
            $query->where('produk_id', $produkId);
        }

        // Pencarian Umum
        $query->when($search, function ($q, $s) {
            $q->where(function ($qq) use ($s) {
                $qq->whereHas('produk', fn ($p) => $p->where('nama_produk', 'like', "%{$s}%")->orWhere('kode_produk', 'like', "%{$s}%"))
                    ->orWhere('batch_number', 'like', "%{$s}%")
                    ->orWhere('proses', 'like', "%{$s}%");
            });
        });

        // Sorting
        switch ($sortBy) {
            case 'tanggal_asc':
                $query->orderBy('tanggal', 'asc')->orderBy('id', 'asc');
                break;
            case 'reject_desc':
                $query->orderByDesc('total_reject')->orderByDesc('tanggal');
                break;
            case 'reject_asc':
                $query->orderBy('total_reject', 'asc')->orderByDesc('tanggal');
                break;
            case 'batch_asc':
                $query->orderBy('batch_number', 'asc');
                break;
            case 'batch_desc':
                $query->orderByDesc('batch_number');
                break;
            case 'produk_asc':
                $query->join('produks', 'laporan_harians.produk_id', '=', 'produks.id')
                    ->orderBy('produks.nama_produk', 'asc')
                    ->select('laporan_harians.*');
                break;
            case 'tanggal_desc':
            default:
                $query->orderByDesc('tanggal')->orderByDesc('updated_at');
                break;
        }

        $productions = $query->paginate(15)->withQueryString();
        $productions->getCollection()->transform(function ($item) {
            $available = $item->output_fisik ?? $item->capacity_fisik ?? 0;
            $item->available_qty = (int) $available;
            $item->total_reject = (int) ($item->total_reject ?? 0);
            $item->sisa_qty = max(0, $item->available_qty - $item->total_reject);

            return $item;
        });

        // 2. Daftar Detail Item Reject (untuk pencarian / history)
        $rejectDetailsQuery = RejectDetail::with(['laporanHarian.produk', 'laporanHarian.line', 'laporanHarian.mesin', 'creator'])
            ->latest();

        if (! empty($month)) {
            $parts = explode('-', $month);
            if (count($parts) === 2) {
                $rejectDetailsQuery->whereHas('laporanHarian', fn ($q) => $q->whereYear('tanggal', (int) $parts[0])->whereMonth('tanggal', (int) $parts[1]));
            }
        }
        if (! empty($startDate)) {
            $rejectDetailsQuery->whereHas('laporanHarian', fn ($q) => $q->whereDate('tanggal', '>=', $startDate));
        }
        if (! empty($endDate)) {
            $rejectDetailsQuery->whereHas('laporanHarian', fn ($q) => $q->whereDate('tanggal', '<=', $endDate));
        }
        if (! empty($produkId)) {
            $rejectDetailsQuery->whereHas('laporanHarian', fn ($q) => $q->where('produk_id', $produkId));
        }

        if ($search) {
            $rejectDetailsQuery->where(function ($q) use ($search) {
                $q->where('keterangan', 'like', "%{$search}%")
                    ->orWhere('jenis_reject', 'like', "%{$search}%")
                    ->orWhere('odoo_scrap_id', 'like', "%{$search}%")
                    ->orWhereHas('laporanHarian', function ($qq) use ($search) {
                        $qq->where('batch_number', 'like', "%{$search}%")
                            ->orWhere('proses', 'like', "%{$search}%")
                            ->orWhereHas('produk', function ($p) use ($search) {
                                $p->where('nama_produk', 'like', "%{$search}%")
                                    ->orWhere('kode_produk', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $rejectDetails = $rejectDetailsQuery->paginate(20, ['*'], 'reject_page')->withQueryString();

        // 3. Master Produk untuk Filter Dropdown
        $produks = Produk::select('id', 'kode_produk', 'nama_produk')
            ->orderBy('nama_produk')
            ->get();

        // 4. Ringkasan Statistik Filtered
        $totalFilteredReject = (int) (clone $rejectDetailsQuery)->sum('jumlah');

        return Inertia::render('Reject/Index', [
            'productions' => $productions,
            'rejectDetails' => $rejectDetails,
            'produks' => $produks,
            'search' => $search,
            'filter' => $filter,
            'month' => $month,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'produk_id' => $produkId ? (int) $produkId : '',
            'sort_by' => $sortBy,
            'stats' => [
                'total_reject_pcs' => $totalFilteredReject,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }
        if (! $user->hasAnyRole(['leader', 'operator', 'spv', 'admin', 'superadmin'])) {
            abort(403, 'Anda tidak memiliki akses untuk melakukan reject.');
        }

        $validated = $request->validate([
            'laporan_harian_id' => 'required|exists:laporan_harians,id',
            'alasan_reject' => ['required', 'string', 'min:3', function ($attr, $val, $fail) {
                if (trim($val) === '') {
                    $fail('Alasan reject wajib diisi.');
                }
            }],
            'qty_reject' => 'required|integer|min:1',
            'jenis_reject' => 'required|in:sublayer,ga,process',
        ], [
            'alasan_reject.required' => 'Alasan reject wajib diisi.',
            'qty_reject.required' => 'Qty reject wajib diisi.',
            'qty_reject.min' => 'Qty reject harus lebih besar dari 0.',
            'jenis_reject.required' => 'Jenis reject wajib dipilih.',
        ]);

        $laporan = LaporanHarian::with(['produk', 'rejectDetails'])->findOrFail($validated['laporan_harian_id']);

        if ($laporan->status === 'locked') {
            return back()->withErrors(['laporan_harian_id' => 'Data produksi yang sudah dikunci tidak dapat direject.']);
        }

        $availableQty = $laporan->output_fisik ?? $laporan->capacity_fisik ?? 0;
        if ($availableQty <= 0) {
            return back()->withErrors(['qty_reject' => 'Quantity produk tidak tersedia atau belum diisi.']);
        }

        $totalExisting = (int) $laporan->rejectDetails()->sum('jumlah');
        $sisa = $availableQty - $totalExisting;

        if ($sisa <= 0) {
            return back()->withErrors(['qty_reject' => 'Qty reject tidak boleh melebihi qty produk yang tersedia. Sisa: 0']);
        }

        if ($validated['qty_reject'] > $sisa) {
            return back()->withErrors(['qty_reject' => "Qty reject tidak boleh melebihi qty produk yang tersedia. Sisa: {$sisa}"]);
        }

        $jenisToStore = $validated['jenis_reject'];
        $alasan = trim($validated['alasan_reject']);

        try {
            DB::transaction(function () use ($laporan, $jenisToStore, $validated, $alasan, $user) {
                RejectDetail::create([
                    'laporan_harian_id' => $laporan->id,
                    'jenis_reject' => $jenisToStore,
                    'jumlah' => $validated['qty_reject'],
                    'keterangan' => $alasan,
                    'created_by' => $user->id,
                ]);
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Gagal menyimpan data reject. Silakan coba lagi.']);
        }

        return back()->with('success', 'Reject produk berhasil disimpan.');
    }

    /**
     * Hapus item reject
     */
    public function destroy(RejectDetail $rejectDetail)
    {
        $user = auth()->user();
        if ($user->hasRole('manager')) {
            abort(403, 'Manager hanya memiliki akses baca.');
        }

        $laporan = $rejectDetail->laporanHarian;
        if ($laporan && $laporan->status === 'locked') {
            return back()->withErrors(['error' => 'Data reject tidak dapat dihapus karena laporan produksi sudah dikunci.']);
        }

        if ($user->hasAnyRole(['leader', 'operator']) && $rejectDetail->created_by !== $user->id) {
            return back()->withErrors(['error' => 'Anda hanya dapat menghapus data reject yang Anda input sendiri.']);
        }

        $rejectDetail->delete();

        return back()->with('success', 'Data reject berhasil dihapus.');
    }

    /**
     * Pull dan sync data reject dari MO Odoo yang sudah Done.
     */
    public function syncFromOdoo(OdooService $odooService)
    {
        $user = auth()->user();
        if ($user->hasRole('manager')) {
            abort(403, 'Manager hanya memiliki akses baca.');
        }

        try {
            $summary = $odooService->syncMoRejects();
            $msg = "Sync Reject MO Odoo selesai: {$summary['created']} item baru, {$summary['updated']} diperbarui.";
            if (! empty($summary['errors'])) {
                $msg .= ' Error: '.implode(', ', $summary['errors']);
            }

            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Gagal sync Reject dari Odoo: '.$e->getMessage()]);
        }
    }
}
