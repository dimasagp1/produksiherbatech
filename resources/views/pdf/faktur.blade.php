<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Faktur {{ $plan->delivery_number }}</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:11px}
h1{font-size:15px;margin:0 0 10px}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #999;padding:4px 5px}
th{background:#f3f4f6}
.num{text-align:right}
</style></head>
<body>
<h1>FAKTUR PENJUALAN</h1>
<p><strong>No:</strong> {{ $plan->delivery_number }} · <strong>Tanggal:</strong> {{ ($plan->actual_delivery_date ?? $plan->planned_date)->format('d-m-Y') }}</p>
<p><strong>Pelanggan:</strong> {{ $plan->customer_name }}</p>
<p><strong>COA Pendapatan:</strong> {{ $coa }}</p>
<table>
<thead><tr><th>Produk</th><th class="num">Qty</th><th>UOM</th></tr></thead>
<tbody>
@foreach($plan->items as $item)
<tr><td>{{ $item->product_name }}</td><td class="num">{{ number_format($item->quantity, 2) }}</td><td>{{ $item->uom_name ?? '-' }}</td></tr>
@endforeach
</tbody>
</table>
<p style="margin-top:16px">Hormat kami,<br><br><br>PT Herbatech</p>
</body>
</html>
