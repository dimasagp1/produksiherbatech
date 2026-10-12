<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SearchInput from '@/Components/SearchInput.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{ supplies:{data:any[],current_page:number,last_page:number,per_page:number,total:number}, filters:any, kpi:any }>();
const flash = computed(() => (usePage().props as any).flash ?? {});
const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? 'all');
watch([search, status], () => {} )
function apply(){ router.get(route('scm.supply.index'), { search: search.value || undefined, status: status.value },{preserveState:true, preserveScroll:true}); }
function goPage(p:number){ router.get(route('scm.supply.index'), { page:p, search: search.value || undefined, status: status.value },{preserveState:true}); }
function statusCls(s:string){
  if(s==='delivered') return 'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-900/50';
  if(s==='late') return 'bg-rose-100 text-rose-800 border-rose-200 dark:bg-rose-900/50';
  if(s==='partial') return 'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-900/50';
  return 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800';
}
</script>
<template>
<Head title="Supply H-1" />
<AuthenticatedLayout>
<template #header>
<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
<div><h2 class="text-xl font-bold tracking-tight">Supply H-1 (Warehouse → Produksi)</h2><p class="mt-0.5 text-xs text-gray-500">Target kirim H-1 sebelum MO running · KPI On-Time & Complete</p></div>
<div class="flex gap-2">
<Link :href="route('scm.supply.create')" class="inline-flex rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-indigo-700">+ Buat Supply</Link>
<Link :href="route('scm.supply.kpi')" class="inline-flex rounded-xl border bg-white px-4 py-2 text-xs font-bold hover:bg-gray-50 dark:bg-gray-800">KPI</Link>
</div>
</div>
</template>
<div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
<div v-if="flash.success" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">{{ flash.success }}</div>

<div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
<div class="rounded-2xl border bg-white p-4 shadow-sm dark:bg-gray-800"><div class="text-[11px] uppercase font-semibold text-gray-500">Due Tomorrow</div><div class="text-2xl font-extrabold">{{ kpi.dueTomorrow }}</div><div class="text-[11px] text-gray-400">H-1 need</div></div>
<div class="rounded-2xl border bg-white p-4 shadow-sm dark:bg-gray-800"><div class="text-[11px] uppercase font-semibold text-rose-600">Late</div><div class="text-2xl font-extrabold text-rose-600">{{ kpi.late }}</div><div class="text-[11px] text-gray-400">pending &lt; today</div></div>
<div class="rounded-2xl border bg-white p-4 shadow-sm dark:bg-gray-800"><div class="text-[11px] uppercase font-semibold text-gray-500">On-Time</div><div class="text-2xl font-extrabold">{{ kpi.onTimePct }}%</div><div class="text-[11px] text-gray-400">delivered ≤ required</div></div>
<div class="rounded-2xl border bg-white p-4 shadow-sm dark:bg-gray-800"><div class="text-[11px] uppercase font-semibold text-gray-500">Complete</div><div class="text-2xl font-extrabold">{{ kpi.completePct }}%</div><div class="text-[11px] text-gray-400">delivered / required</div></div>
<div class="rounded-2xl border bg-white p-4 shadow-sm dark:bg-gray-800"><div class="text-[11px] uppercase font-semibold text-gray-500">Total</div><div class="text-2xl font-extrabold">{{ kpi.total }}</div><div class="text-[11px] text-gray-400">supply</div></div>
</div>

<div class="rounded-2xl border bg-white p-4 shadow-sm dark:bg-gray-800">
<div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
<div><InputLabel value="Cari" /><SearchInput v-model="search" placeholder="Supply / batch..." @search="apply" class="mt-1.5" /></div>
<div><InputLabel value="Status" /><select v-model="status" @change="apply" class="mt-1.5 block w-full rounded-xl border-gray-300 text-xs dark:bg-gray-900"><option value="all">Semua</option><option value="pending">Pending</option><option value="delivered">Delivered</option><option value="partial">Partial</option><option value="late">Late</option></select></div>
<div class="flex items-end"><PrimaryButton @click="apply" class="w-full justify-center">Terapkan</PrimaryButton></div>
</div>
</div>

<div class="overflow-hidden rounded-2xl border bg-white shadow-sm dark:bg-gray-800">
<div class="overflow-x-auto">
<table class="min-w-full divide-y text-sm">
<thead class="bg-gray-50 font-semibold uppercase tracking-wider text-gray-600 dark:bg-gray-900/60"><tr><th class="px-4 py-3 text-[11px]">Supply</th><th class="px-4 py-3 text-[11px]">Weekly Plan</th><th class="px-4 py-3 text-[11px]">Required</th><th class="px-4 py-3 text-[11px]">H-1</th><th class="px-4 py-3 text-[11px]">Status</th><th class="px-4 py-3 text-right text-[11px]">Aksi</th></tr></thead>
<tbody class="divide-y">
<tr v-if="supplies.data.length===0"><td colspan="6" class="py-10 text-center text-sm text-gray-400">Belum ada supply — buat dari Weekly Plan / MPS</td></tr>
<tr v-for="s in supplies.data" :key="s.id" class="hover:bg-gray-50/70">
<td class="px-4 py-3"><div class="font-mono text-xs font-bold">{{ s.supply_number }}</div><div class="text-[11px] text-gray-500">{{ s.work_center?.name ?? s.workCenter?.name ?? '-' }}</div></td>
<td class="px-4 py-3"><div class="text-xs font-semibold">{{ s.weekly_plan?.batch_number ?? s.weeklyPlan?.batch_number ?? '-' }}</div><div class="text-[11px] text-gray-500">{{ s.weekly_plan?.produk?.nama_produk ?? s.weeklyPlan?.produk?.nama_produk ?? '-' }}</div></td>
<td class="px-4 py-3 font-mono text-xs">{{ s.required_date }}</td>
<td class="px-4 py-3 text-xs">{{ s.required_date ? new Date(new Date(s.required_date).getTime()-86400000).toLocaleDateString('id-ID',{day:'2-digit',month:'short'}) : '-' }}</td>
<td class="px-4 py-3"><span class="rounded-md border px-2 py-0.5 text-[11px] font-bold uppercase" :class="statusCls(s.status)">{{ s.status }}</span></td>
<td class="px-4 py-3 text-right"><Link :href="route('scm.supply.show', s.id)" class="rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Detail</Link></td>
</tr>
</tbody>
</table>
</div>
<div class="border-t px-4 py-3"><Pagination :current-page="supplies.current_page" :last-page="supplies.last_page" :total="supplies.total" :per-page="supplies.per_page" @page="goPage" /></div>
</div>
</div>
</AuthenticatedLayout>
</template>
