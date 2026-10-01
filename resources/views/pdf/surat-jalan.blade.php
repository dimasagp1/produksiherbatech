<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Surat Jalan {{ $plan->delivery_number }}</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:11px}
h1{font-size:15px;margin:0 0 2px;text-align:center}
.sub{text-align:center;color:#555;margin-bottom:12px}
.box{border:1px solid #999;padding:8px;margin-bottom:10px}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #999;padding:4px 5px}
th{background:#f3f4f6}
.sign{margin-top:36px;display:flex;justify-content:space-between}
</style></head>
<body>
<h1>SURAT JALAN</h1>
<div class="sub">PT Herbatech · LinePulse SCM</div>
<div class="box">
<strong>No. SJ:</strong> {{ $plan->delivery_number }}<br>
<strong>Tanggal Rencana:</strong> {{ $plan->planned_date->format('d-m-Y') }}<br>
<strong>Pelanggan:</strong> {{ $plan->customer_name }}<br>
<strong>Armada:</strong> {{ $plan->fleet->nama_armada ?? '-' }} ({{ $plan->fleet->plat_number ?? '-' }})<br>
<strong>Driver:</strong> {{ $plan->driver_name ?? $plan->fleet->driver_name ?? '-' }}
</div>
<table>
<thead><tr><th>Produk</th><th>Qty</th><th>UOM</th></tr></thead>
<tbody>
@foreach($plan->items as $item)
<tr><td>{{ $item->product_name }}</td><td>{{ number_format($item->quantity, 2) }}</td><td>{{ $item->uom_name ?? '-' }}</td></tr>
@endforeach
</tbody>
</table>
<div class="sign">
<div>Penerima,<br><br><br>(..............................)</div>
<div>Pengirim / Driver,<br><br><br>(..............................)</div>
</div>
</body>
</html>
