<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Kartu Stelling {{ $opname->opname_number }}</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:11px}
h1{font-size:15px;margin:0 0 4px}
.meta{margin-bottom:10px;color:#333}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #999;padding:4px 5px}
th{background:#eef2ff;font-size:10px}
.sign{margin-top:40px;display:flex;justify-content:space-between;font-size:11px}
</style></head>
<body>
<h1>KARTU STELLING — Stock Opname</h1>
<div class="meta">
No: <strong>{{ $opname->opname_number }}</strong> · Lokasi: {{ $opname->location }} ·
Status: {{ $opname->status }} · Tanggal: {{ optional($opname->initiated_at)->format('d-m-Y H:i') }}
</div>
<table>
<thead><tr><th>ST Card</th><th>Produk</th><th>Batch</th><th>Qty Sistem</th><th>Qty Hitung</th><th>Selisih</th><th>Alasan</th></tr></thead>
<tbody>
@forelse($opname->items as $item)
<tr>
<td>{{ $item->stelling_card }}</td>
<td>{{ $item->produk->nama_produk ?? '-' }}</td>
<td>{{ $item->batch_number ?? '-' }}</td>
<td>{{ number_format($item->system_qty, 2) }}</td>
<td>{{ $item->counted_qty !== null ? number_format($item->counted_qty, 2) : '________' }}</td>
<td>{{ $item->discrepancy !== null ? number_format($item->discrepancy, 2) : '' }}</td>
<td>{{ $item->discrepancy_reason ?? '' }}</td>
</tr>
@empty
<tr><td colspan="7">Tidak ada baris snapshot</td></tr>
@endforelse
</tbody>
</table>
<div class="sign">
<div>Petugas Hitung: ....................<br>({{ $opname->initiator->name ?? '-' }})</div>
<div>Manager Approve: ....................<br>({{ $opname->approver->name ?? '-' }})</div>
</div>
</body>
</html>
