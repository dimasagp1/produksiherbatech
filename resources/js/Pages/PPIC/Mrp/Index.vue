<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type Row = {
    id: number;
    mps_plan_id: number;
    material_name: string;
    needed_qty: number;
    needed_uom: string;
    beginning_stock: number;
    incoming_qty: number;
    available_qty: number;
    shortage_qty: number;
    rop: number;
    status: 'critical' | 'warning' | 'ok';
    calculated_at: string | null;
    materialProduk?: { id: number; kode_produk: string; nama_produk: string } | null;
    mpsPlan?: { id: number; month_year: string; produk?: { kode_produk: string; nama_produk: string } } | null;
    mps_plan?: { id: number; month_year: string; produk?: { kode_produk: string; nama_produk: string } } | null;
};

const props = defineProps<{
    rows: { data: Row[]; current_page?: number; last_page?: number; total: number; per_page?: number; links?: any[] };
    plans: { id: number; month_year: string; status: string; produk?: { kode_produk: string; nama_produk: string } }[];
    mpsPlanId: number | null;
    status: string;
    summary: { critical: number; warning: number; ok: number };
}>();

const flash = computed(() => (usePage().props as any).flash ?? {});
const mpsFilter = ref(props.mpsPlanId ? String(props.mpsPlanId) : '');
const statusFilter = ref(props.status ?? 'all');
const q = ref('');

watch([mpsFilter, statusFilter], () => apply());

function apply(page = 1) {
    router.get(route('ppic.mrp.index'), {
        page: page > 1 ? page : undefined,
        mps_plan_id: mpsFilter.value || undefined,
        status: statusFilter.value,
    }, { preserveScroll: true, preserveState: true });
}

function goPage(page:number){ apply(page); }

function recalc() {
    router.post(route('ppic.mrp.recalculate'), { mps_plan_id: mpsFilter.value || undefined });
}

function fmt(n: any) { return Number(n ?? 0).toLocaleString('id-ID'); }

function statusMeta(s: string) {
    if (s === 'critical') return { label: 'Critical', cls: 'bg-rose-100 text-rose-800 border-rose-200 dark:bg-rose-900/50 dark:text-rose-300 dark:border-rose-800', dot: 'bg-rose-600', sub: 'Stok < ROP' };
    if (s === 'warning') return { label: 'Warning', cls: 'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-900/50 dark:text-amber-300 dark:border-amber-800', dot: 'bg-amber-500', sub: 'Need+ROP > Available' };
    return { label: 'OK', cls: 'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-900/50 dark:text-emerald-300 dark:border-emerald-800', dot: 'bg-emerald-600', sub: 'Aman' };
}

const filtered = computed(() => {
    const term = q.value.trim().toLowerCase();
    if (!term) return props.rows.data;
    return props.rows.data.filter(r => r.material_name.toLowerCase().includes(term) || (r.materialProduk?.kode_produk ?? '').toLowerCase().includes(term));
});

function coverage(r: Row) {
    const need = Number(r.needed_qty) + Number(r.rop);
    const avail = Number(r.available_qty);
    if (need <= 0) return 100;
    return Math.min(100, Math.max(0, (avail / need) * 100));
}

function formatMonth(ym?: string) {
    if (!ym) return '-';
    const [y, m] = ym.split('-'); const names = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']; return `${names[parseInt(m) - 1]} ${y}`;
}

const total = computed(() => props.summary.critical + props.summary.warning + props.summary.ok);
</script>
<template>

    <Head title="MRP & Red Warning" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-gray-100">MRP & Red Warning</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        MPS × BOM → <span class="font-semibold">Need</span> vs <span class="font-semibold">Stock
                            OH</span> + <span class="font-semibold">ROP</span> · Shortage = max(0, Need+ROP − Available)
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <a :href="route('ppic.mrp.export', { mps_plan_id: mpsFilter || undefined })"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-2 text-xs font-bold text-emerald-800 shadow-sm hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">Export
                        Excel</a>
                    <PrimaryButton @click="recalc" class="whitespace-nowrap">Recalculate</PrimaryButton>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-3 sm:px-6 lg:px-8 space-y-4">
            <div v-if="flash.success"
                class="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-sm dark:border-emerald-800/80 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ flash.success }}</div>
            <div v-if="flash.error"
                class="flex items-center justify-between rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-sm dark:border-rose-800/80 dark:bg-rose-950/40 dark:text-rose-200">
                {{ flash.error }}</div>

            <!-- KPI cards -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                <div
                    class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Total
                        Material</div>
                    <div class="mt-1 text-2xl font-extrabold text-gray-900 dark:text-gray-100">{{ total }}</div>
                    <div class="text-[11px] text-gray-500">dari {{ props.plans.length }} MPS plan</div>
                </div>
                <div
                    class="rounded-2xl border border-rose-200 bg-rose-50 p-4 shadow-sm dark:border-rose-800/60 dark:bg-rose-950/30">
                    <div
                        class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wide text-rose-700 dark:text-rose-300">
                        <span class="h-2 w-2 rounded-full bg-rose-600"></span> Critical
                    </div>
                    <div class="mt-1 text-2xl font-extrabold text-rose-800 dark:text-rose-200">{{ summary.critical }}
                    </div>
                    <div class="text-[11px] text-rose-700/70 dark:text-rose-300/70">Stok &lt; ROP & shortage &gt; 0
                    </div>
                </div>
                <div
                    class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm dark:border-amber-800/60 dark:bg-amber-950/30">
                    <div
                        class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wide text-amber-700 dark:text-amber-300">
                        <span class="h-2 w-2 rounded-full bg-amber-500"></span> Warning
                    </div>
                    <div class="mt-1 text-2xl font-extrabold text-amber-800 dark:text-amber-200">{{ summary.warning }}
                    </div>
                    <div class="text-[11px] text-amber-700/70 dark:text-amber-300/70">Need+ROP &gt; Available</div>
                </div>
                <div
                    class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm dark:border-emerald-800/60 dark:bg-emerald-950/30">
                    <div
                        class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">
                        <span class="h-2 w-2 rounded-full bg-emerald-600"></span> OK
                    </div>
                    <div class="mt-1 text-2xl font-extrabold text-emerald-800 dark:text-emerald-200">{{ summary.ok }}
                    </div>
                    <div class="text-[11px] text-emerald-700/70 dark:text-emerald-300/70">Aman</div>
                </div>
            </div>

            <!-- Filters -->
            <div
                class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div class="sm:col-span-2">
                        <InputLabel value="Filter MPS Plan" />
                        <select v-model="mpsFilter"
                            class="mt-1.5 block w-full rounded-xl border-gray-300 py-1.5 text-xs shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                            <option value="">Semua MPS Plan</option>
                            <option v-for="p in plans" :key="p.id" :value="p.id">{{ formatMonth(p.month_year) }} · {{
                                p.produk?.kode_produk ?? '-' }} — {{ p.produk?.nama_produk ?? '-' }} ({{ p.status }})
                            </option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Status" />
                        <select v-model="statusFilter"
                            class="mt-1.5 block w-full rounded-xl border-gray-300 py-1.5 text-xs shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                            <option value="all">Semua Status</option>
                            <option value="critical">Critical</option>
                            <option value="warning">Warning</option>
                            <option value="ok">OK</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Cari Material" />
                        <input v-model="q" type="text" placeholder="Nama / kode material…"
                            class="mt-1.5 block w-full rounded-xl border-gray-300 px-3 py-1.5 text-xs shadow-sm placeholder:text-gray-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400">
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 dark:bg-gray-700">Need = MPS(adjusted) ×
                        BOM/qty_per_unit
                        (base g/ml)</span>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 dark:bg-gray-700">Stock ternormalisasi KG→g,
                        L→ml</span>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 dark:bg-gray-700">Available = Stock OH +
                        incoming(0)</span>
                </div>
            </div>

            <!-- Table -->
            <div
                class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-sm dark:divide-gray-700">
                        <thead
                            class="bg-gray-50 font-semibold uppercase tracking-wider text-gray-600 dark:bg-gray-900/60 dark:text-gray-400">
                            <tr>
                                <th class="px-3 py-3 text-[11px]">MPS</th>
                                <th class="px-3 py-3 text-[11px]">Material</th>
                                <th class="px-3 py-3 text-right text-[11px]">Need</th>
                                <th class="px-3 py-3 text-right text-[11px]">Stock / Available</th>
                                <th class="px-3 py-3 text-right text-[11px]">ROP</th>
                                <th class="px-3 py-3 text-right text-[11px]">Shortage</th>
                                <th class="px-3 py-3 text-[11px]">Coverage</th>
                                <th class="px-3 py-3 text-[11px]">Status</th>
                                <th class="px-3 py-3 text-right text-[11px]">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <tr v-if="filtered.length === 0" class="text-center">
                                <td colspan="9" class="py-12 text-gray-400 dark:text-gray-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="h-10 w-10 text-gray-300 dark:text-gray-600" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <p class="mt-2 text-sm font-semibold">Tidak ada data MRP</p>
                                        <p class="text-xs text-gray-400">Pilih MPS Plan lalu klik Recalculate, atau buat
                                            MPS +
                                            BOM terlebih dahulu</p>
                                    </div>
                                </td>
                            </tr>
                            <tr v-for="r in filtered" :key="r.id" class="hover:bg-gray-50/70 dark:hover:bg-gray-700/40"
                                :class="r.status === 'critical' ? 'bg-rose-50/60 dark:bg-rose-950/20' : r.status === 'warning' ? 'bg-amber-50/60 dark:bg-amber-950/20' : ''">
                                <td class="px-3 py-2.5">
                                    <div class="text-xs font-bold text-gray-900 dark:text-gray-100">{{
                                        formatMonth(r.mps_plan?.month_year ?? r.mpsPlan?.month_year) }}</div>
                                    <div class="font-mono text-[11px] text-gray-500">{{ r.mps_plan?.produk?.kode_produk
                                        ??
                                        r.mpsPlan?.produk?.kode_produk ?? '-' }}</div>
                                </td>
                                <td class="px-3 py-2.5">
                                    <div class="font-semibold text-gray-900 dark:text-gray-100">{{ r.material_name }}
                                    </div>
                                    <div class="font-mono text-[11px] text-gray-500">{{ r.materialProduk?.kode_produk ??
                                        '-' }}
                                        · {{ r.materialProduk?.nama_produk ?? '' }}</div>
                                </td>
                                <td class="px-3 py-2.5 text-right font-mono text-xs text-gray-900 dark:text-gray-100">
                                    {{ fmt(r.needed_qty) }} <span class="text-[11px] text-gray-400">{{ r.needed_uom
                                        }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <div class="font-mono text-xs text-gray-900 dark:text-gray-100">{{
                                        fmt(r.available_qty) }}
                                        <span class="text-[11px] text-gray-400">{{ r.needed_uom }}</span>
                                    </div>
                                    <div class="text-[11px] text-gray-400">Stock {{ fmt(r.beginning_stock) }}</div>
                                </td>
                                <td class="px-3 py-2.5 text-right font-mono text-xs text-gray-700 dark:text-gray-300">{{
                                    fmt(r.rop) }} <span class="text-[11px] text-gray-400">{{ r.needed_uom }}</span></td>
                                <td class="px-3 py-2.5 text-right">
                                    <span class="font-mono text-xs font-bold"
                                        :class="Number(r.shortage_qty) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'">{{
                                        fmt(r.shortage_qty) }}</span>
                                    <span class="ml-1 text-[11px] text-gray-400">{{ r.needed_uom }}</span>
                                </td>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="h-1.5 w-16 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                            <div class="h-full rounded-full transition-all"
                                                :class="r.status === 'critical' ? 'bg-rose-600' : r.status === 'warning' ? 'bg-amber-500' : 'bg-emerald-600'"
                                                :style="{ width: coverage(r) + '%' }"></div>
                                        </div>
                                        <span class="text-[11px] font-mono text-gray-600 dark:text-gray-400">{{
                                            Math.round(coverage(r)) }}%</span>
                                    </div>
                                    <div class="text-[10px] text-gray-400">{{ Number(r.available_qty) < Number(r.rop)
                                            ? 'di bawah ROP' : 'di atas ROP' }}</div>
                                </td>
                                <td class="px-3 py-2.5">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] font-bold"
                                        :class="statusMeta(r.status).cls">
                                        <span class="h-1.5 w-1.5 rounded-full" :class="statusMeta(r.status).dot"></span>
                                        {{ statusMeta(r.status).label }}
                                    </span>
                                    <div class="mt-0.5 text-[10px] text-gray-400">{{ statusMeta(r.status).sub }}</div>
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <Link :href="route('ppic.mrp.detail', r.id)"
                                        class="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:text-indigo-300">
                                        Detail</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="(props.rows.total ?? 0) > 0" class="border-t border-gray-200 px-4 py-3 dark:border-gray-700">
                    <Pagination
                        :current-page="props.rows.current_page ?? 1"
                        :last-page="props.rows.last_page ?? 1"
                        :total="props.rows.total"
                        :per-page="props.rows.per_page ?? 50"
                        @page="goPage"
                    />
                </div>
            </div>

            <p class="text-center text-[11px] text-gray-400">Base unit: g/ml (KG→g, L→ml) · Tersisa = Stock − Need ·
                Shortage
                sudah termasuk ROP</p>
        </div>
    </AuthenticatedLayout>
</template>
