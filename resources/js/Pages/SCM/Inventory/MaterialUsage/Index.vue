<script setup lang="ts">
import OdooMaterialUsageSyncModal from '@/Components/OdooMaterialUsageSyncModal.vue';
import Pagination from '@/Components/Pagination.vue';
import SearchInput from '@/Components/SearchInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface MaterialItem {
    id: number;
    material_name: string;
    quantity_used: number;
    quantity_standard: number;
    variance: number;
    ratio_persen?: number | null;
    produk?: {
        id: number;
        kode_produk: string;
        nama_produk: string;
        item_type: string;
        odoo_uom?: string;
        uom?: { code: string; name: string };
    } | null;
    uom?: { code: string; name: string } | null;
}

interface Usage {
    id: number;
    usage_number: string;
    usage_date: string;
    shift?: string | null;
    notes?: string | null;
    weekly_plan?: {
        id: number;
        batch_number: string;
        target_output?: number;
        proses?: string;
        line?: { nama_line: string };
        produk?: {
            id: number;
            kode_produk: string;
            nama_produk: string;
            odoo_uom?: string;
            uom?: { code: string };
        };
    } | null;
    user?: { name: string };
    items: MaterialItem[];
}

const props = defineProps<{
    usages: {
        data: Usage[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    search: string;
    ratioTarget: number;
}>();

const flash = computed(() => (usePage().props as any).flash ?? {});
const search = ref(props.search);
const showOdooModal = ref(false);
const expandedUsageIds = ref<number[]>([]);

function toggleExpandUsage(id: number) {
    const idx = expandedUsageIds.value.indexOf(id);
    if (idx > -1) {
        expandedUsageIds.value.splice(idx, 1);
    } else {
        expandedUsageIds.value.push(id);
    }
}

function getItemUom(item: MaterialItem): string {
    return item.uom?.code || item.produk?.uom?.code || item.produk?.odoo_uom || 'Unit';
}

function ratioColor(r: number | null | undefined): string {
    if (r === null || r === undefined) return 'text-slate-400';
    if (r <= props.ratioTarget) return 'text-emerald-600 dark:text-emerald-400';
    return 'text-rose-600 dark:text-rose-400';
}

function ratioBadgeClass(r: number | null | undefined): string {
    if (r === null || r === undefined) return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400';
    if (r <= props.ratioTarget) return 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800';
    return 'bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800';
}

function overallRatio(u: Usage): number | null {
    const std = u.items.reduce((s, i) => s + Number(i.quantity_standard || 0), 0);
    const used = u.items.reduce((s, i) => s + Number(i.quantity_used || 0), 0);
    return std > 0 ? Number((((used - std) / std) * 100).toFixed(2)) : null;
}

function goToPage(page: number) {
    router.get(
        route('scm.material-usage.index'),
        { page, search: search.value },
        { preserveState: true },
    );
}

function onOdooSynced(res: any) {
    router.reload();
}
</script>

<template>
    <Head title="Material Usage (Pemakaian Material)" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">
                        Material Usage (Pemakaian Bahan)
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Monitoring konsumsi bahan baku (RM) & kemas (PM) per Produk Jadi & Batch · Target Deviasi ≤ {{ ratioTarget }}%
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        @click="showOdooModal = true"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-amber-300/80 bg-amber-50 px-3.5 py-2 text-xs font-bold text-amber-900 shadow-xs transition hover:bg-amber-100 active:scale-95 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-200 dark:hover:bg-amber-900/60"
                    >
                        <svg class="h-4 w-4 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>Sync dari Odoo MO</span>
                    </button>
                    <a
                        :href="route('scm.material-usage.export', 'pdf')"
                        class="inline-flex items-center gap-1 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                    >
                        <svg class="h-3.5 w-3.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Export PDF
                    </a>
                    <a
                        :href="route('scm.material-usage.export', 'excel')"
                        class="inline-flex items-center gap-1 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                    >
                        <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Export Excel
                    </a>
                    <Link
                        :href="route('scm.material-usage.create')"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-indigo-700 active:scale-95"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        + Input Pemakaian
                    </Link>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            <!-- Flash Message -->
            <div
                v-if="flash.success"
                class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800/80 dark:bg-emerald-950/40 dark:text-emerald-200"
            >
                <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ flash.success }}</span>
            </div>

            <!-- Search & Controls -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <SearchInput
                    v-model="search"
                    placeholder="Cari no usage, nama produk, kode produk, batch, atau material..."
                    class="max-w-md w-full"
                    @search="goToPage(1)"
                />
                <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 font-medium">
                    <span>Total: <strong class="text-slate-800 dark:text-slate-200">{{ usages.total }}</strong> Transaksi Pemakaian</span>
                </div>
            </div>

            <!-- Main Table Card -->
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs dark:border-slate-700 dark:bg-slate-800">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-slate-200 bg-slate-50/80 text-slate-600 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-300">
                            <tr>
                                <th class="px-4 py-3.5 font-bold uppercase tracking-wider text-[11px]">No. Usage & Tanggal</th>
                                <th class="px-4 py-3.5 font-bold uppercase tracking-wider text-[11px]">Produk Jadi (Finished Good) & Batch</th>
                                <th class="px-4 py-3.5 font-bold uppercase tracking-wider text-[11px]">Rincian Material & Qty (Aktual / Standar)</th>
                                <th class="px-4 py-3.5 font-bold uppercase tracking-wider text-[11px] text-right">Rasio Deviasi</th>
                                <th class="px-4 py-3.5 font-bold uppercase tracking-wider text-[11px] text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                            <template v-for="u in usages.data" :key="u.id">
                                <tr class="hover:bg-slate-50/70 transition-colors dark:hover:bg-slate-700/30">
                                    <!-- No Usage & Date -->
                                    <td class="px-4 py-3.5 align-top">
                                        <div class="font-mono font-bold text-slate-900 dark:text-slate-100">
                                            {{ u.usage_number }}
                                        </div>
                                        <div class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                            {{ u.usage_date }}
                                        </div>
                                        <div class="mt-1 flex items-center gap-1.5">
                                            <span v-if="u.shift" class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                                {{ u.shift }}
                                            </span>
                                            <span v-if="u.user" class="text-[10px] text-slate-400">
                                                oleh {{ u.user.name }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Finished Good Product & Batch -->
                                    <td class="px-4 py-3.5 align-top">
                                        <div class="font-bold text-slate-900 dark:text-slate-100">
                                            {{ u.weekly_plan?.produk?.nama_produk ?? 'Tanpa Produk Terkait' }}
                                        </div>
                                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                            <span
                                                v-if="u.weekly_plan?.produk?.kode_produk"
                                                class="rounded bg-indigo-50 px-1.5 py-0.5 font-mono text-[10px] font-bold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300"
                                            >
                                                {{ u.weekly_plan.produk.kode_produk }}
                                            </span>
                                            <span class="rounded bg-amber-50 px-1.5 py-0.5 font-mono text-[10px] font-bold text-amber-700 dark:bg-amber-950/60 dark:text-amber-300">
                                                Batch: {{ u.weekly_plan?.batch_number ?? '-' }}
                                            </span>
                                        </div>
                                        <div class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                                            <span v-if="u.weekly_plan?.line">{{ u.weekly_plan.line.nama_line }} · </span>
                                            <span v-if="u.weekly_plan?.proses">{{ u.weekly_plan.proses }}</span>
                                            <span v-if="u.weekly_plan?.target_output"> (Target: {{ u.weekly_plan.target_output.toLocaleString() }})</span>
                                        </div>
                                    </td>

                                    <!-- Material Items Details -->
                                    <td class="px-4 py-3.5 align-top">
                                        <div class="space-y-1.5">
                                            <!-- Shown Items: 2 items initially or all if expanded -->
                                            <div
                                                v-for="(it, idx) in (expandedUsageIds.includes(u.id) ? u.items : u.items.slice(0, 2))"
                                                :key="it.id"
                                                class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50/60 px-2.5 py-1 text-[11px] dark:border-slate-700/60 dark:bg-slate-900/40"
                                            >
                                                <div class="flex items-center gap-1.5 truncate">
                                                    <span
                                                        v-if="it.produk?.item_type === 'rm'"
                                                        class="rounded bg-emerald-100 px-1 text-[9px] font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 shrink-0"
                                                    >
                                                        RM
                                                    </span>
                                                    <span
                                                        v-else-if="it.produk?.item_type === 'pm'"
                                                        class="rounded bg-blue-100 px-1 text-[9px] font-bold text-blue-800 dark:bg-blue-950 dark:text-blue-300 shrink-0"
                                                    >
                                                        PM
                                                    </span>
                                                    <span class="font-medium text-slate-800 dark:text-slate-200 truncate">
                                                        {{ it.material_name }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-2 shrink-0 font-mono text-[11px]">
                                                    <span class="font-bold text-slate-900 dark:text-slate-100">
                                                        {{ it.quantity_used }}
                                                    </span>
                                                    <span class="text-slate-400">/ std {{ it.quantity_standard }} {{ getItemUom(it) }}</span>
                                                    <span
                                                        v-if="it.ratio_persen !== null && it.ratio_persen !== undefined"
                                                        class="font-semibold"
                                                        :class="ratioColor(it.ratio_persen)"
                                                    >
                                                        ({{ (it.ratio_persen ?? 0) > 0 ? '+' : '' }}{{ it.ratio_persen }}%)
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Toggle more items -->
                                            <button
                                                v-if="u.items.length > 2"
                                                type="button"
                                                @click="toggleExpandUsage(u.id)"
                                                class="text-[11px] font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 hover:underline inline-flex items-center gap-1 mt-1"
                                            >
                                                <span>{{ expandedUsageIds.includes(u.id) ? 'Sembunyikan' : `+ ${u.items.length - 2} material lainnya...` }}</span>
                                            </button>
                                        </div>
                                    </td>

                                    <!-- Overall Ratio % -->
                                    <td class="px-4 py-3.5 align-top text-right">
                                        <div class="inline-flex flex-col items-end">
                                            <span
                                                class="rounded-full px-2.5 py-1 text-xs font-bold"
                                                :class="ratioBadgeClass(overallRatio(u))"
                                            >
                                                {{ overallRatio(u) !== null ? `${overallRatio(u)}%` : '-' }}
                                            </span>
                                            <span class="mt-1 text-[10px] font-medium text-slate-400">
                                                {{ (overallRatio(u) ?? 0) <= ratioTarget ? 'Sesuai Target' : 'Over Target' }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-4 py-3.5 align-top text-center">
                                        <Link
                                            :href="route('scm.material-usage.show', u.id)"
                                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-bold text-indigo-600 shadow-2xs transition hover:border-indigo-300 hover:bg-indigo-50 dark:border-slate-700 dark:bg-slate-800 dark:text-indigo-400 dark:hover:bg-slate-700"
                                        >
                                            <span>Detail</span>
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </Link>
                                    </td>
                                </tr>
                            </template>

                            <tr v-if="usages.data.length === 0">
                                <td colspan="5" class="px-4 py-12 text-center text-slate-400 dark:text-slate-500">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <svg class="h-8 w-8 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                        </svg>
                                        <p class="text-sm font-semibold">Belum ada data Material Usage</p>
                                        <p class="text-xs text-slate-400">Sinkronkan dari Odoo MO atau lakukan input pemakaian manual.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30">
                    <Pagination
                        :current-page="usages.current_page"
                        :last-page="usages.last_page"
                        :total="usages.total"
                        :per-page="usages.per_page"
                        @page="goToPage"
                    />
                </div>
            </div>
        </div>

        <!-- ODOO SYNC MODAL -->
        <OdooMaterialUsageSyncModal
            :show="showOdooModal"
            @close="showOdooModal = false"
            @synced="onOdooSynced"
        />
    </AuthenticatedLayout>
</template>

