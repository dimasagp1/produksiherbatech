<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

interface RecipeBreakdownItem {
    name: string;
    percentage: number;
    qty_per_unit_g: number;
    total_kg: number;
    total_g: number;
    display_qty: string;
}

interface PrimerPackagingItem {
    name: string;
    qty_per_unit: number;
    total_batch_qty: number;
    uom: string;
}

interface RecipeBreakdown {
    is_ruahan: boolean;
    base_product_name: string;
    ruahan_bom_name: string;
    ruahan_dose_per_unit_g: number;
    total_batch_ruahan_kg: number;
    total_batch_ruahan_g: number;
    target_output_units: number;
    raw_materials_count: number;
    raw_materials: RecipeBreakdownItem[];
    primer_packaging: PrimerPackagingItem[];
}

interface Item {
    id: number;
    material_name: string;
    material_code?: string;
    item_type?: string;
    quantity_used: number;
    quantity_standard: number;
    variance: number;
    ratio_persen?: number | null;
    uom_code?: string;
    recipe_breakdown?: RecipeBreakdown | null;
    uom?: { code?: string; name?: string } | null;
    produk?: {
        id: number;
        kode_produk: string;
        nama_produk: string;
        item_type: string;
        odoo_uom?: string;
        uom?: { code: string };
    } | null;
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
        tanggal?: string;
        line?: { nama_line: string };
        produk?: {
            id: number;
            kode_produk: string;
            nama_produk: string;
            item_type?: string;
            odoo_uom?: string;
            uom?: { code: string };
        };
    } | null;
    user?: { name: string };
}

const props = defineProps<{
    usage: Usage;
    items: Item[];
    summary: {
        total_items_count: number;
        rm_count: number;
        pm_count: number;
        within_target_count: number;
        compliance_rate: number;
        ratio_target: number;
        rm_efficient_count: number;
        pm_efficient_count: number;
        rm_used: number;
        pm_used: number;
        total_standard: number;
        total_used: number;
        total_variance: number;
        overall_ratio: number | null;
    };
}>();

const activeTab = ref<'all' | 'rm' | 'pm'>('all');
const expandedItems = ref<Record<number, boolean>>({});

// Auto expand items with recipe breakdown on load
onMounted(() => {
    props.items.forEach((it) => {
        if (it.recipe_breakdown && it.recipe_breakdown.raw_materials.length > 0) {
            expandedItems.value[it.id] = true;
        }
    });
});

function toggleExpand(itemId: number) {
    expandedItems.value[itemId] = !expandedItems.value[itemId];
}

function getItemUom(item: Item): string {
    return item.uom_code || item.uom?.code || item.produk?.uom?.code || item.produk?.odoo_uom || 'Unit';
}

function getItemTypeLabel(item: Item): { label: string; class: string } {
    if (item.recipe_breakdown || item.item_type === 'wip' || item.material_name.toLowerCase().includes('primer') || item.material_name.toLowerCase().includes('ruahan')) {
        return {
            label: 'Bahan Baku (Ruahan/WIP)',
            class: 'bg-amber-100 text-amber-900 border-amber-300 dark:bg-amber-950/70 dark:text-amber-200 dark:border-amber-700/80',
        };
    }
    const type = item.item_type || item.produk?.item_type;
    if (type === 'rm' || type === 'bahan_baku') {
        return {
            label: 'Bahan Baku (RM)',
            class: 'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800',
        };
    }
    if (type === 'pm' || type === 'bahan_kemas') {
        return {
            label: 'Bahan Kemas (PM)',
            class: 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-950 dark:text-blue-300 dark:border-blue-800',
        };
    }
    return {
        label: 'Komponen',
        class: 'bg-slate-100 text-slate-800 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
    };
}

function getEfficiencyStatus(item: Item): { text: string; class: string } {
    if (item.variance === 0) {
        return { text: 'Sesuai Standar', class: 'bg-slate-100 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700' };
    }
    if (item.variance < 0) {
        return { text: 'Efisien / Hemat', class: 'bg-emerald-100 text-emerald-800 border border-emerald-200 font-semibold dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800' };
    }
    if (item.ratio_persen !== null && item.ratio_persen !== undefined && item.ratio_persen > props.summary.ratio_target) {
        return { text: 'Over Target Pemakaian', class: 'bg-rose-100 text-rose-800 border border-rose-200 font-bold dark:bg-rose-950 dark:text-rose-300 dark:border-rose-800' };
    }
    return { text: 'Toleransi Wajar', class: 'bg-amber-100 text-amber-800 border border-amber-200 font-semibold dark:bg-amber-950 dark:text-amber-300 dark:border-amber-800' };
}

const filteredItems = computed(() => {
    if (activeTab.value === 'rm') {
        return props.items.filter((it) => (it.item_type === 'rm' || it.item_type === 'wip' || it.produk?.item_type === 'rm' || it.produk?.item_type === 'wip'));
    }
    if (activeTab.value === 'pm') {
        return props.items.filter((it) => (it.item_type === 'pm' || it.produk?.item_type === 'pm'));
    }
    return props.items;
});
</script>

<template>
    <Head :title="`Detail Material Usage: ${usage.usage_number}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100 font-mono">
                            {{ usage.usage_number }}
                        </h2>
                        <span class="rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-bold text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300">
                            {{ items.length }} Komponen Terpakai
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Laporan Penggunaan Material · {{ usage.usage_date }} · Dibuat oleh {{ usage.user?.name ?? 'System' }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a
                        :href="route('scm.material-usage.export', 'pdf')"
                        class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300"
                    >
                        Export PDF
                    </a>
                    <Link
                        :href="route('scm.material-usage.index')"
                        class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300"
                    >
                        Kembali
                    </Link>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-5 px-4 sm:px-6 lg:px-8 pb-10">
            <!-- FINISHED GOOD PRODUCT HERO BANNER -->
            <div class="overflow-hidden rounded-2xl border border-indigo-200/80 bg-gradient-to-r from-indigo-50/80 via-white to-slate-50 p-6 shadow-xs dark:border-indigo-900/50 dark:from-slate-900 dark:via-slate-900 dark:to-indigo-950/40">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div class="space-y-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                            Produk Jadi (Finished Good) Yang Diproduksi
                        </span>
                        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-slate-100">
                            {{ usage.weekly_plan?.produk?.nama_produk ?? 'Tanpa Produk Terkait' }}
                        </h1>
                        <div class="flex flex-wrap items-center gap-2 text-xs pt-1">
                            <span
                                v-if="usage.weekly_plan?.produk?.kode_produk"
                                class="rounded-lg bg-indigo-100 px-2.5 py-1 font-mono font-bold text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800"
                            >
                                SKU: {{ usage.weekly_plan.produk.kode_produk }}
                            </span>
                            <span class="rounded-lg bg-amber-100 px-2.5 py-1 font-mono font-bold text-amber-800 dark:bg-amber-950 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                Batch: {{ usage.weekly_plan?.batch_number ?? '-' }}
                            </span>
                            <span v-if="usage.weekly_plan?.line" class="rounded-lg bg-slate-100 px-2.5 py-1 font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                Line: {{ usage.weekly_plan.line.nama_line }}
                            </span>
                            <span v-if="usage.weekly_plan?.proses" class="rounded-lg bg-slate-100 px-2.5 py-1 font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300 capitalize">
                                Proses: {{ usage.weekly_plan.proses }}
                            </span>
                            <span v-if="usage.shift" class="rounded-lg bg-purple-100 px-2.5 py-1 font-semibold text-purple-800 dark:bg-purple-950 dark:text-purple-300">
                                Shift: {{ usage.shift }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 lg:border-l lg:border-indigo-100 lg:pl-6 dark:lg:border-slate-800">
                        <div class="text-left lg:text-right">
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Target Output Produksi</span>
                            <div class="text-2xl font-black text-indigo-700 dark:text-indigo-300 font-mono">
                                {{ usage.weekly_plan?.target_output ? usage.weekly_plan.target_output.toLocaleString('id-ID') : '-' }}
                                <span class="text-xs font-medium text-slate-500">Unit</span>
                            </div>
                            <span class="text-[11px] text-slate-400">
                                Tanggal Plan: {{ usage.weekly_plan?.tanggal ?? usage.usage_date }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Notes bar if available -->
                <div v-if="usage.notes" class="mt-4 rounded-xl border border-slate-200/60 bg-white/80 p-3 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-800/80 dark:text-slate-300">
                    <span class="font-bold text-slate-500">Catatan Pemakaian:</span> {{ usage.notes }}
                </div>
            </div>

            <!-- KPI SUMMARY CARDS (Revamped & Categorized) -->
            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Card 1: Total Komponen Digunakan -->
                <div class="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs transition hover:shadow-md dark:border-slate-700/80 dark:bg-slate-800/90">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold tracking-tight text-slate-500 dark:text-slate-400">Total Komponen Resep</span>
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-700/80 dark:text-slate-300">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2.5 flex items-baseline gap-1.5">
                        <span class="text-2xl sm:text-3xl font-black font-mono text-slate-900 dark:text-white">
                            {{ summary.total_items_count }}
                        </span>
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Item Material</span>
                    </div>
                    <div class="mt-3 flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300">
                        <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 font-bold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-100 dark:border-emerald-800/60">
                            {{ summary.rm_count }} Bahan Baku (RM)
                        </span>
                        <span class="text-slate-300 dark:text-slate-600">•</span>
                        <span class="inline-flex items-center gap-1 rounded-md bg-sky-50 px-2 py-0.5 font-bold text-sky-700 dark:bg-sky-950/60 dark:text-sky-300 border border-sky-100 dark:border-sky-800/60">
                            {{ summary.pm_count }} Kemas (PM)
                        </span>
                    </div>
                </div>

                <!-- Card 2: Efisiensi Bahan Baku (RM) -->
                <div class="flex flex-col justify-between rounded-2xl border border-emerald-200/70 bg-gradient-to-br from-emerald-50/60 to-white p-4 shadow-xs transition hover:shadow-md dark:border-emerald-900/60 dark:from-emerald-950/30 dark:to-slate-800">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold tracking-tight text-emerald-800 dark:text-emerald-300">Efisiensi Bahan Baku (RM)</span>
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                        </div>
                    </div>
                    <div v-if="summary.rm_count > 0" class="mt-2.5 flex items-baseline gap-2">
                        <span class="text-2xl sm:text-3xl font-black font-mono text-emerald-700 dark:text-emerald-300">
                            {{ summary.rm_efficient_count }}<span class="text-base font-bold text-slate-400 dark:text-slate-500">/{{ summary.rm_count }}</span>
                        </span>
                        <span
                            :class="summary.rm_efficient_count === summary.rm_count ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200 border-emerald-200 dark:border-emerald-700' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200 border-amber-200 dark:border-amber-700'"
                            class="rounded-full border px-2 py-0.5 text-[10px] font-bold"
                        >
                            {{ summary.rm_efficient_count === summary.rm_count ? '100% Sesuai' : `${summary.rm_count - summary.rm_efficient_count} Lebih` }}
                        </span>
                    </div>
                    <div v-else class="mt-2.5 flex items-baseline gap-2">
                        <span class="text-2xl font-black text-slate-400 dark:text-slate-500 font-mono">-</span>
                        <span class="rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                            Hanya Bahan Kemas
                        </span>
                    </div>
                    <div class="mt-3 text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                        {{ summary.rm_count === 0 ? 'Tidak ada bahan baku langsung' : (summary.rm_count === summary.rm_efficient_count ? '✓ Semua bahan baku efisien' : `${summary.rm_count - summary.rm_efficient_count} item melebihi kuota standar`) }}
                    </div>
                </div>

                <!-- Card 3: Efisiensi Bahan Kemas (PM) -->
                <div class="flex flex-col justify-between rounded-2xl border border-sky-200/70 bg-gradient-to-br from-sky-50/60 to-white p-4 shadow-xs transition hover:shadow-md dark:border-sky-900/60 dark:from-sky-950/30 dark:to-slate-800">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold tracking-tight text-sky-800 dark:text-sky-300">Efisiensi Kemasan (PM)</span>
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-sky-100 text-sky-700 dark:bg-sky-900/50 dark:text-sky-300">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                    </div>
                    <div v-if="summary.pm_count > 0" class="mt-2.5 flex items-baseline gap-2">
                        <span class="text-2xl sm:text-3xl font-black font-mono text-sky-700 dark:text-sky-300">
                            {{ summary.pm_efficient_count }}<span class="text-base font-bold text-slate-400 dark:text-slate-500">/{{ summary.pm_count }}</span>
                        </span>
                        <span
                            :class="summary.pm_efficient_count === summary.pm_count ? 'bg-sky-100 text-sky-800 dark:bg-sky-900/60 dark:text-sky-200 border-sky-200 dark:border-sky-700' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200 border-amber-200 dark:border-amber-700'"
                            class="rounded-full border px-2 py-0.5 text-[10px] font-bold"
                        >
                            {{ summary.pm_efficient_count === summary.pm_count ? '100% Sesuai' : `${summary.pm_count - summary.pm_efficient_count} Lebih` }}
                        </span>
                    </div>
                    <div v-else class="mt-2.5 flex items-baseline gap-2">
                        <span class="text-2xl font-black text-slate-400 dark:text-slate-500 font-mono">0</span>
                        <span class="rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                            Proses Mixing
                        </span>
                    </div>
                    <div class="mt-3 text-xs font-semibold text-sky-700 dark:text-sky-400">
                        {{ summary.pm_count === 0 ? 'Tanpa kemasan pada tahap ini' : (summary.pm_count === summary.pm_efficient_count ? '✓ Semua kemasan efisien' : `${summary.pm_count - summary.pm_efficient_count} item melebihi kuota standar`) }}
                    </div>
                </div>

                <!-- Card 4: Sasaran Mutu (Target Deviasi) -->
                <div class="flex flex-col justify-between rounded-2xl border border-indigo-200/70 bg-gradient-to-br from-indigo-50/60 to-white p-4 shadow-xs transition hover:shadow-md dark:border-indigo-900/60 dark:from-indigo-950/30 dark:to-slate-800">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold tracking-tight text-indigo-800 dark:text-indigo-300">Kepatuhan Sasaran Mutu</span>
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2.5 flex items-baseline gap-2">
                        <span
                            :class="summary.compliance_rate >= 100 ? 'text-emerald-600 dark:text-emerald-400' : (summary.compliance_rate >= 80 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400')"
                            class="text-2xl sm:text-3xl font-black font-mono"
                        >
                            {{ summary.compliance_rate }}%
                        </span>
                        <span
                            :class="summary.compliance_rate >= 100 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200 border-emerald-200 dark:border-emerald-700' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200 border-amber-200 dark:border-amber-700'"
                            class="rounded-full border px-2 py-0.5 text-[10px] font-bold"
                        >
                            {{ summary.compliance_rate >= 100 ? 'Sesuai Target' : 'Deviasi > 0.5%' }}
                        </span>
                    </div>
                    <div class="mt-2">
                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700/60">
                            <div
                                :class="summary.compliance_rate >= 100 ? 'bg-emerald-500' : (summary.compliance_rate >= 80 ? 'bg-amber-500' : 'bg-rose-500')"
                                class="h-full rounded-full transition-all duration-500"
                                :style="{ width: `${Math.min(100, summary.compliance_rate)}%` }"
                            ></div>
                        </div>
                        <div class="mt-1 flex items-center justify-between text-[11px] font-medium text-slate-500 dark:text-slate-400">
                            <span>{{ summary.within_target_count }}/{{ summary.total_items_count }} item patuh</span>
                            <span class="font-semibold text-indigo-600 dark:text-indigo-400">Batas: ≤ {{ summary.ratio_target }}%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DETAILED MATERIAL ITEMS BREAKDOWN -->
            <div class="rounded-2xl border border-slate-200 bg-white shadow-xs dark:border-slate-700 dark:bg-slate-800 overflow-hidden">
                <!-- Tabs Header -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 bg-slate-50/70 px-5 py-3.5 dark:border-slate-700 dark:bg-slate-900/60">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">
                            Daftar Lengkap Material & Bahan Yang Digunakan
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Klik pada item ruahan/setengah jadi untuk melihat rincian komposisi bahan baku penyusunnya
                        </p>
                    </div>

                    <!-- Category Filter Tabs -->
                    <div class="mt-2 sm:mt-0 flex items-center gap-1.5 text-xs">
                        <button
                            type="button"
                            class="rounded-xl px-3 py-1.5 font-bold transition"
                            :class="activeTab === 'all' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700'"
                            @click="activeTab = 'all'"
                        >
                            Semua Bahan ({{ items.length }})
                        </button>
                        <button
                            type="button"
                            class="rounded-xl px-3 py-1.5 font-bold transition"
                            :class="activeTab === 'rm' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700'"
                            @click="activeTab = 'rm'"
                        >
                            Bahan Baku / RM ({{ summary.rm_count }})
                        </button>
                        <button
                            type="button"
                            class="rounded-xl px-3 py-1.5 font-bold transition"
                            :class="activeTab === 'pm' ? 'bg-blue-600 text-white shadow-xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700'"
                            @click="activeTab = 'pm'"
                        >
                            Bahan Kemas / PM ({{ summary.pm_count }})
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs min-w-[750px]">
                        <thead class="border-b border-slate-200 bg-slate-50/50 text-slate-600 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-300">
                            <tr>
                                <th class="w-10 px-3 py-3 text-center font-bold uppercase tracking-wider text-[11px]">No</th>
                                <th class="px-4 py-3 font-bold uppercase tracking-wider text-[11px]">Kode & Nama Material / Bahan</th>
                                <th class="px-4 py-3 font-bold uppercase tracking-wider text-[11px]">Kategori</th>
                                <th class="px-4 py-3 font-bold uppercase tracking-wider text-[11px] text-right">Standar (Plan)</th>
                                <th class="px-4 py-3 font-bold uppercase tracking-wider text-[11px] text-right">Aktual (Terpakai)</th>
                                <th class="px-4 py-3 font-bold uppercase tracking-wider text-[11px] text-right">Selisih</th>
                                <th class="px-4 py-3 font-bold uppercase tracking-wider text-[11px] text-right">Deviasi %</th>
                                <th class="px-4 py-3 font-bold uppercase tracking-wider text-[11px] text-center">Status Efisiensi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                            <template v-for="(it, idx) in filteredItems" :key="it.id">
                                <tr
                                    class="transition-colors"
                                    :class="it.recipe_breakdown ? 'bg-amber-50/20 hover:bg-amber-50/50 dark:bg-amber-950/10 dark:hover:bg-amber-950/30' : 'hover:bg-slate-50/70 dark:hover:bg-slate-700/30'"
                                >
                                    <td class="px-3 py-3.5 text-center font-mono text-slate-400">
                                        {{ idx + 1 }}
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="font-bold text-slate-900 dark:text-slate-100 text-sm">
                                            {{ it.material_name }}
                                        </div>
                                        <div v-if="it.material_code && it.material_code !== '-'" class="font-mono text-[11px] text-slate-500">
                                            SKU: {{ it.material_code }}
                                        </div>

                                        <!-- Toggle Button for Recipe Breakdown -->
                                        <div v-if="it.recipe_breakdown" class="mt-1.5">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-900 shadow-2xs transition hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/70 dark:text-amber-200 dark:hover:bg-amber-900/80"
                                                @click="toggleExpand(it.id)"
                                            >
                                                <svg
                                                    class="h-3.5 w-3.5 transition-transform"
                                                    :class="{ 'rotate-180': expandedItems[it.id] }"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                                <span>{{ expandedItems[it.id] ? 'Tutup Rincian Formula' : `Lihat Rincian Formula Bahan Baku (${it.recipe_breakdown.raw_materials_count} Bahan)` }}</span>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span
                                            class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[10px] font-bold"
                                            :class="getItemTypeLabel(it).class"
                                        >
                                            {{ getItemTypeLabel(it).label }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-mono text-slate-700 dark:text-slate-300">
                                        {{ it.quantity_standard.toLocaleString('id-ID') }} <span class="text-[10px] text-slate-400">{{ getItemUom(it) }}</span>
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-mono font-bold text-slate-900 dark:text-slate-100">
                                        {{ it.quantity_used.toLocaleString('id-ID') }} <span class="text-[10px] text-slate-400">{{ getItemUom(it) }}</span>
                                    </td>
                                    <td
                                        class="px-4 py-3.5 text-right font-mono font-semibold"
                                        :class="it.variance > 0 ? 'text-rose-600 dark:text-rose-400' : (it.variance < 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-600')"
                                    >
                                        {{ it.variance > 0 ? '+' : '' }}{{ it.variance.toLocaleString('id-ID') }}
                                    </td>
                                    <td
                                        class="px-4 py-3.5 text-right font-bold"
                                        :class="it.ratio_persen !== null && it.ratio_persen !== undefined && it.ratio_persen > summary.ratio_target ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'"
                                    >
                                        {{ it.ratio_persen !== null && it.ratio_persen !== undefined ? `${(it.ratio_persen ?? 0) > 0 ? '+' : ''}${it.ratio_persen}%` : '-' }}
                                    </td>
                                    <td class="px-4 py-3.5 text-center">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-semibold"
                                            :class="getEfficiencyStatus(it).class"
                                        >
                                            {{ getEfficiencyStatus(it).text }}
                                        </span>
                                    </td>
                                </tr>

                                <!-- EXPANDABLE SUB-RECIPE BREAKDOWN ACCORDION -->
                                <tr v-if="expandedItems[it.id] && it.recipe_breakdown" class="bg-amber-50/40 dark:bg-amber-950/20">
                                    <td colspan="8" class="p-4 sm:p-5">
                                        <div class="rounded-xl border border-amber-200 bg-white p-4 shadow-sm dark:border-amber-800/80 dark:bg-slate-900">
                                            <!-- Sub-Header & Metadata -->
                                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-3 border-b border-amber-100 dark:border-amber-900/50 gap-2">
                                                <div>
                                                    <div class="flex items-center gap-2">
                                                        <span class="rounded-md bg-amber-500 px-2 py-0.5 text-[10px] font-black uppercase text-white">
                                                            Formula Mixing
                                                        </span>
                                                        <h4 class="font-bold text-slate-900 dark:text-slate-100 text-sm">
                                                            Komposisi Bahan Baku: {{ it.recipe_breakdown.ruahan_bom_name }}
                                                        </h4>
                                                    </div>
                                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                                        Daftar bahan baku murni hasil formulasi mixing untuk kebutuhan batch ini (Proporsi resep Odoo)
                                                    </p>
                                                </div>

                                                <div class="flex flex-wrap items-center gap-2 text-xs">
                                                    <div class="rounded-lg bg-amber-50 px-2.5 py-1 text-amber-900 font-semibold border border-amber-200 dark:bg-amber-950/60 dark:text-amber-200 dark:border-amber-800">
                                                        Takaran: <strong>{{ it.recipe_breakdown.ruahan_dose_per_unit_g }} g / Botol</strong>
                                                    </div>
                                                    <div class="rounded-lg bg-indigo-50 px-2.5 py-1 text-indigo-900 font-semibold border border-indigo-200 dark:bg-indigo-950/60 dark:text-indigo-200 dark:border-indigo-800">
                                                        Total Ruahan: <strong>{{ it.recipe_breakdown.total_batch_ruahan_kg.toLocaleString('id-ID') }} Kg</strong> ({{ it.recipe_breakdown.target_output_units.toLocaleString('id-ID') }} Unit)
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Raw Materials Nested Table -->
                                            <div class="mt-3 overflow-x-auto">
                                                <table class="w-full text-left text-xs min-w-[550px]">
                                                    <thead class="bg-amber-50/60 text-slate-700 dark:bg-amber-950/40 dark:text-slate-200 border-b border-amber-100 dark:border-amber-900/40">
                                                        <tr>
                                                            <th class="w-8 px-3 py-2 text-center font-bold">#</th>
                                                            <th class="px-3 py-2 font-bold">Nama Bahan Baku Penyusun (Raw Material)</th>
                                                            <th class="px-3 py-2 font-bold text-right">Persentase Formula (%)</th>
                                                            <th class="px-3 py-2 font-bold text-right">Kebutuhan / Unit (g)</th>
                                                            <th class="px-3 py-2 font-bold text-right">Total Kebutuhan Batch</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                                        <tr
                                                            v-for="(rm, rmIdx) in it.recipe_breakdown.raw_materials"
                                                            :key="rm.name"
                                                            class="hover:bg-amber-50/40 dark:hover:bg-amber-950/30 transition-colors"
                                                        >
                                                            <td class="px-3 py-2 text-center font-mono text-slate-400 text-[11px]">
                                                                {{ rmIdx + 1 }}
                                                            </td>
                                                            <td class="px-3 py-2 font-semibold text-slate-900 dark:text-slate-100">
                                                                {{ rm.name }}
                                                            </td>
                                                            <td class="px-3 py-2 text-right font-mono text-slate-600 dark:text-slate-300">
                                                                {{ rm.percentage }}%
                                                            </td>
                                                            <td class="px-3 py-2 text-right font-mono text-slate-600 dark:text-slate-300">
                                                                {{ rm.qty_per_unit_g }} g
                                                            </td>
                                                            <td class="px-3 py-2 text-right font-mono font-bold text-indigo-700 dark:text-indigo-300">
                                                                {{ rm.display_qty }}
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <!-- Packaging Primer Components if any -->
                                            <div v-if="it.recipe_breakdown.primer_packaging.length > 0" class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800">
                                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                                    Komponen Kemasan Primer Terkait:
                                                </span>
                                                <div class="mt-1.5 flex flex-wrap gap-2">
                                                    <div
                                                        v-for="pm in it.recipe_breakdown.primer_packaging"
                                                        :key="pm.name"
                                                        class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs text-slate-700 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700"
                                                    >
                                                        <span class="font-semibold">{{ pm.name }}</span>:
                                                        <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400 ml-1">{{ pm.total_batch_qty.toLocaleString('id-ID') }} {{ pm.uom }}</span>
                                                        <span class="text-[10px] text-slate-400 ml-1">({{ pm.qty_per_unit }} / unit)</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr v-if="filteredItems.length === 0">
                                <td colspan="8" class="px-4 py-8 text-center text-slate-400">
                                    Tidak ada material pada kategori yang dipilih
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
