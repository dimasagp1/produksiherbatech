<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Material Loss Report</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#111}
h1{font-size:16px;margin:0 0 4px}
.meta{color:#555;margin-bottom:12px}
table{width:100%;border-collapse:collapse;margin-top:8px}
th,td{border:1px solid #ccc;padding:4px 6px;text-align:left}
th{background:#f3f4f6}
.num{text-align:right}
</style></head>
<body>
<h1>Material Loss Report</h1>
<div class="meta">Periode: {{ $summary['date_from'] }} s/d {{ $summary['date_to'] }} · LinePulse SCM</div>
<table>
<thead><tr><th>No. Usage</th><th>Tanggal</th><th>Batch</th><th>Material</th><th class="num">Standard</th><th class="num">Actual</th><th class="num">Variance</th><th class="num">Ratio %</th></tr></thead>
<tbody>
@forelse($rows as $r)
<tr>
<td>{{ $r['usage_number'] }}</td><td>{{ $r['usage_date'] }}</td><td>{{ $r['batch_number'] }}</td><td>{{ $r['material_name'] }}</td>
<td class="num">{{ number_format($r['standard'], 2) }}</td><td class="num">{{ number_format($r['used'], 2) }}</td>
<td class="num">{{ number_format($r['variance'], 2) }}</td><td class="num">{{ $r['ratio'] !== null ? number_format($r['ratio'], 2) : '-' }}</td>
</tr>
@empty
<tr><td colspan="8">Tidak ada data</td></tr>
@endforelse
</tbody>
<tfoot>
<tr><th colspan="4">Total</th>
<th class="num">{{ number_format($summary['total_standard'], 2) }}</th>
<th class="num">{{ number_format($summary['total_used'], 2) }}</th>
<th class="num">{{ number_format($summary['total_variance'], 2) }}</th><th></th></tr>
</tfoot>
</table>
</body>
</html>
