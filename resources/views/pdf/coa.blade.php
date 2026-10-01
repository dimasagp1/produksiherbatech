<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>COA {{ $plan->delivery_number }}</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:11px}
h1{font-size:15px;margin:0 0 8px}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #999;padding:4px 5px}
th{background:#ecfdf5}
</style></head>
<body>
<h1>CERTIFICATE OF ANALYSIS (CoA)</h1>
<p><strong>Delivery:</strong> {{ $plan->delivery_number }} · <strong>Pelanggan:</strong> {{ $plan->customer_name }}</p>
<p><strong>Tanggal:</strong> {{ ($plan->actual_delivery_date ?? $plan->planned_date)->format('d-m-Y') }}</p>
<table>
<thead><tr><th>Produk</th><th>Qty</th><th>UOM</th><th>Status</th></tr></thead>
<tbody>
@foreach($plan->items as $item)
<tr>
<td>{{ $item->product_name }}</td>
<td>{{ number_format($item->quantity, 2) }}</td>
<td>{{ $item->uom_name ?? '-' }}</td>
<td>Release — sesuai spesifikasi internal</td>
</tr>
@endforeach
</tbody>
</table>
<p style="margin-top:20px">QA / QC — PT Herbatech</p>
</body>
</html>
