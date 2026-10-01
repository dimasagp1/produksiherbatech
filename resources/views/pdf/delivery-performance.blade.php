<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Delivery Performance</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:11px}
h1{font-size:15px;margin:0 0 8px}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #999;padding:4px 5px}
th{background:#f3f4f6}
</style></head>
<body>
<h1>Delivery Performance Report</h1>
<p>Periode: {{ $from }} s/d {{ $to }}</p>
<table>
<thead><tr><th>No</th><th>Pelanggan</th><th>Rencana</th><th>Aktual</th><th>Status</th><th>OTD</th><th>In-Full</th><th>Damage-Free</th><th>Doc</th><th>Complaint</th></tr></thead>
<tbody>
@forelse($plans as $i => $p)
<tr>
<td>{{ $p->delivery_number }}</td><td>{{ $p->customer_name }}</td>
<td>{{ $p->planned_date->format('d-m-Y') }}</td>
<td>{{ $p->actual_delivery_date?->format('d-m-Y') ?? '-' }}</td>
<td>{{ $p->status }}</td>
<td>{{ $p->on_time === null ? '-' : ($p->on_time ? 'OK' : 'Late') }}</td>
<td>{{ $p->in_full === null ? '-' : ($p->in_full ? 'OK' : 'Short') }}</td>
<td>{{ $p->damage_free === null ? '-' : ($p->damage_free ? 'OK' : 'Damage') }}</td>
<td>{{ $p->doc_accuracy === null ? '-' : ($p->doc_accuracy ? 'OK' : 'Err') }}</td>
<td>{{ $p->complaint ? 'Yes' : 'No' }}</td>
</tr>
@empty
<tr><td colspan="10">Tidak ada data</td></tr>
@endforelse
</tbody>
</table>
</body>
</html>
