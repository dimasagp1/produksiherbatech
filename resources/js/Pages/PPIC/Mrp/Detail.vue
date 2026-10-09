<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ row: any; breakdown: any; totalQty: number }>();
const mps = computed(() => props.row.mps_plan ?? props.row.mpsPlan ?? null);
const prod = computed(() => mps.value?.produk ?? null);
const wc = computed(() => mps.value?.work_center ?? mps.value?.workCenter ?? null);
const matProd = computed(() => props.row.materialProduk ?? props.row.material_produk ?? props.row.material_produk ?? null);
function fmt(n: any) { return Number(n ?? 0).toLocaleString('id-ID'); }
const statusColor: Record<string,string> = { critical:'bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-300', warning:'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300', ok:'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300' };
function wcIcon(t:string){ return ({mixing:'🥣',filling:'🥤',secondary:'📦'} as any)[t] ?? '⚙️'; }
</script>
<template>
<Head :title="`MRP ${props.row.material_name}`" />
<AuthenticatedLayout>
<template #header>
<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
<div>
<div class="flex items-center gap-2">
<h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-gray-100">{{ props.row.material_name }}</h2>
<span class="rounded-md bg-gray-100 px-2 py-0.5 text-[11px] font-bold dark:bg-gray-800">{{ props.row.needed_uom || '-' }}</span>
<span class="rounded-md px-2 py-0.5 text-[10px] font-bold uppercase" :class="statusColor[props.row.status] ?? 'bg-gray-100'">{{ props.row.status }}</span>
</div>
<p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
MPS: {{ mps?.month_year ?? '-' }} · {{ prod?.nama_produk ?? '-' }} ({{ prod?.kode_produk ?? '-' }}) <span v-if="wc">· <span class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-bold" :class="{'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300':wc?.type==='mixing','bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300':wc?.type==='filling','bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-300':wc?.type==='secondary'}">{{ wcIcon(wc?.type) }} {{ wc?.code }} - {{ wc?.name }}</span></span> · Total Qty {{ fmt(totalQty) }} pcs
</p>
</div>
<SecondaryButton @click="router.visit(route('ppic.mrp.index', { mps_plan_id: mps?.id }))">Kembali ke MRP</SecondaryButton>
</div>
</template>

<div class="mx-auto max-w-7xl px-3 sm:px-6 lg:px-8 space-y-4">
<div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
<div class="rounded-xl border bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800"><div class="text-[11px] text-gray-500">Need</div><div class="font-mono text-sm font-bold">{{ fmt(props.row.needed_qty) }} <span class="text-[11px] font-normal">{{ props.row.needed_uom }}</span></div><div v-if="props.row.needed_uom==='g' || props.row.needed_uom==='ml'" class="text-[10px] text-gray-400">≈ {{ fmt(props.row.needed_qty/1000) }} {{ props.row.needed_uom==='g'?'KG':'L' }}</div></div>
<div class="rounded-xl border bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800"><div class="text-[11px] text-gray-500">Stock OH (ternormalisasi)</div><div class="font-mono text-sm font-bold">{{ fmt(props.row.beginning_stock) }} <span class="text-[11px] font-normal">{{ props.row.needed_uom }}</span></div><div class="text-[10px] text-gray-400">Available {{ fmt(props.row.available_qty) }}</div></div>
<div class="rounded-xl border bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800"><div class="text-[11px] text-gray-500">ROP</div><div class="font-mono text-sm font-bold">{{ fmt(props.row.rop) }} <span class="text-[11px] font-normal">{{ props.row.needed_uom }}</span></div><div class="text-[10px] text-gray-400">Incoming {{ fmt(props.row.incoming_qty) }}</div></div>
<div class="rounded-xl border bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800" :class="props.row.status==='critical'?'border-rose-200 bg-rose-50 dark:border-rose-800 dark:bg-rose-950/40': props.row.status==='warning'?'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/40':'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/40'"><div class="text-[11px] text-gray-500">Shortage</div><div class="font-mono text-sm font-bold">{{ fmt(props.row.shortage_qty) }} <span class="text-[11px] font-normal">{{ props.row.needed_uom }}</span></div><div class="text-[10px]">{{ props.row.status==='critical'?'Stock < ROP':'Need+ROP - Available' }}</div></div>
</div>

<div class="rounded-xl border bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
<h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Material Produk</h3>
<p class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ matProd?.nama_produk ?? props.row.material_name }} <span class="text-gray-400">({{ matProd?.kode_produk ?? '-' }})</span> · UOM {{ matProd?.uom?.code ?? matProd?.odoo_uom ?? '-' }}</p>
<p class="text-[11px] text-gray-500">Need base unit {{ props.row.needed_uom }} · stok dinormalisasi KG→g, L→ml agar banding benar.</p>
</div>

<div v-if="breakdown" class="rounded-xl border bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
<div class="flex items-center justify-between">
<h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">BOM Breakdown — scale {{ fmt(totalQty) }} pcs (base {{ breakdown.base_quantity }} {{ breakdown.product_uom }})</h3>
<span class="text-[11px] text-gray-500">BOM #{{ breakdown.bom_id }} v{{ breakdown.version }}</span>
</div>
<div class="mt-3 grid gap-4 sm:grid-cols-3">
<div class="rounded-lg border dark:border-gray-700">
<div class="bg-gray-50 px-3 py-1.5 text-xs font-bold dark:bg-gray-900">Direct Items</div>
<table class="w-full text-xs"><thead class="text-[11px] text-gray-500"><tr><th class="px-2 py-1 text-left">Material</th><th class="px-2 py-1 text-right">Qty</th></tr></thead><tbody class="divide-y dark:divide-gray-700"><tr v-for="d in breakdown.direct_items" :key="d.material_name"><td class="px-2 py-1">{{ d.material_name }}</td><td class="px-2 py-1 text-right font-mono">{{ d.quantity }} {{ d.uom }}</td></tr></tbody></table>
</div>
<div class="rounded-lg border dark:border-gray-700">
<div class="bg-gray-50 px-3 py-1.5 text-xs font-bold dark:bg-gray-900">Raw — base g/ml</div>
<table class="w-full text-xs"><thead class="text-[11px] text-gray-500"><tr><th class="px-2 py-1 text-left">Material</th><th class="px-2 py-1 text-right">Qty</th><th class="px-2 py-1 text-right">%</th></tr></thead><tbody class="divide-y dark:divide-gray-700"><tr v-for="r in breakdown.raw_materials" :key="r.material_name"><td class="px-2 py-1">{{ r.material_name }}</td><td class="px-2 py-1 text-right font-mono">{{ fmt(r.quantity) }} {{ r.uom }}</td><td class="px-2 py-1 text-right">{{ r.composition_percentage ?? '-' }}%</td></tr></tbody></table>
</div>
<div class="rounded-lg border dark:border-gray-700">
<div class="bg-gray-50 px-3 py-1.5 text-xs font-bold dark:bg-gray-900">Packaging</div>
<table class="w-full text-xs"><thead class="text-[11px] text-gray-500"><tr><th class="px-2 py-1 text-left">Material</th><th class="px-2 py-1 text-right">Qty</th></tr></thead><tbody class="divide-y dark:divide-gray-700"><tr v-for="p in [...(breakdown.primary_packaging||[]),...(breakdown.secondary_packaging||[])]" :key="p.material_name"><td class="px-2 py-1">{{ p.material_name }}</td><td class="px-2 py-1 text-right font-mono">{{ fmt(p.quantity) }} {{ p.uom }}</td></tr></tbody></table>
</div>
</div>
<details class="mt-4"><summary class="cursor-pointer text-xs font-semibold text-indigo-600 dark:text-indigo-400">Raw JSON</summary><pre class="mt-2 max-h-80 overflow-auto rounded-lg bg-gray-50 p-3 text-[11px] dark:bg-gray-900">{{ JSON.stringify(breakdown, null, 2) }}</pre></details>
</div>
</div>
</AuthenticatedLayout>
</template>
