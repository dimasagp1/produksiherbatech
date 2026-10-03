<script setup lang="ts">
import axios from 'axios';
import { computed, ref, watch } from 'vue';

interface UsageItemPreview {
    material_name: string;
    odoo_product_id: number | null;
    quantity_used: number;
    quantity_standard: number;
    variance: number;
    ratio_persen: number;
    uom: string | null;
}

interface OdooMaterialUsagePreview {
    odoo_mo_id: number;
    mo_name: string;
    plan_exists: boolean;
    batch_number: string;
    product_name: string;
    product_code: string;
    target_output: number;
    item_count: number;
    total_qty_standard: number;
    total_qty_used: number;
    overall_ratio: number;
    usage_exists: boolean;
    can_sync: boolean;
    items: UsageItemPreview[];
}

const props = defineProps<{
    show: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (
        e: 'synced',
        result: {
            message: string;
            summary: {
                created: number;
                updated: number;
                skipped: number;
                errors: string[];
            };
        },
    ): void;
}>();

const isLoading = ref(false);
const isSyncing = ref(false);
const errorMessage = ref<string | null>(null);
const usages = ref<OdooMaterialUsagePreview[]>([]);
const selectedIds = ref<number[]>([]);
const expandedMoIds = ref<number[]>([]);
const searchQuery = ref('');
const activeFilter = ref<'all' | 'ready' | 'missing' | 'exists' | 'selected'>('all');

watch(
    () => props.show,
    (val) => {
        if (val) {
            fetchPreview();
        } else {
            errorMessage.value = null;
            searchQuery.value = '';
            activeFilter.value = 'all';
            expandedMoIds.value = [];
        }
    },
);

async function fetchPreview() {
    isLoading.value = true;
    errorMessage.value = null;
    try {
        const res = await axios.get(route('scm.material-usage.preview-odoo'));
        if (res.data.success) {
            usages.value = res.data.data || [];
            selectedIds.value = usages.value.filter((u) => u.can_sync).map((u) => u.odoo_mo_id);
        } else {
            errorMessage.value = res.data.message || 'Gagal memuat daftar Material Usage dari Odoo.';
        }
    } catch (err: any) {
        errorMessage.value =
            err.response?.data?.message ||
            err.message ||
            'Gagal menghubungi server Odoo.';
    } finally {
        isLoading.value = false;
    }
}

// Counts
const totalCount = computed(() => usages.value.length);
const readyCount = computed(() => usages.value.filter((u) => u.can_sync && !u.usage_exists).length);
const existsCount = computed(() => usages.value.filter((u) => u.usage_exists).length);
const missingCount = computed(() => usages.value.filter((u) => !u.can_sync).length);
const selectedCount = computed(() => selectedIds.value.length);

// Filtered list
const filteredUsages = computed(() => {
    let list = usages.value;

    if (activeFilter.value === 'ready') {
        list = list.filter((u) => u.can_sync && !u.usage_exists);
    } else if (activeFilter.value === 'missing') {
        list = list.filter((u) => !u.can_sync);
    } else if (activeFilter.value === 'exists') {
        list = list.filter((u) => u.usage_exists);
    } else if (activeFilter.value === 'selected') {
        const set = new Set(selectedIds.value);
        list = list.filter((u) => set.has(u.odoo_mo_id));
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim();
        list = list.filter(
            (u) =>
                u.mo_name.toLowerCase().includes(q) ||
                u.product_name.toLowerCase().includes(q) ||
                u.product_code.toLowerCase().includes(q) ||
                u.batch_number.toLowerCase().includes(q) ||
                u.items.some((it) => it.material_name.toLowerCase().includes(q)),
        );
    }

    return list;
});

const isAllFilteredSelected = computed(() => {
    if (filteredUsages.value.length === 0) return false;
    const syncable = filteredUsages.value.filter((u) => u.can_sync);
    if (syncable.length === 0) return false;
    const set = new Set(selectedIds.value);
    return syncable.every((u) => set.has(u.odoo_mo_id));
});

function toggleSelectAllFiltered() {
    const set = new Set(selectedIds.value);
    const syncable = filteredUsages.value.filter((u) => u.can_sync);

    if (isAllFilteredSelected.value) {
        syncable.forEach((u) => set.delete(u.odoo_mo_id));
    } else {
        syncable.forEach((u) => set.add(u.odoo_mo_id));
    }
    selectedIds.value = Array.from(set);
}

function selectOnlyReady() {
    selectedIds.value = usages.value.filter((u) => u.can_sync).map((u) => u.odoo_mo_id);
}

function clearSelection() {
    selectedIds.value = [];
}

function toggleUsage(u: OdooMaterialUsagePreview) {
    if (!u.can_sync) return;
    const idx = selectedIds.value.indexOf(u.odoo_mo_id);
    if (idx > -1) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(u.odoo_mo_id);
    }
}

function toggleExpand(moId: number) {
    const idx = expandedMoIds.value.indexOf(moId);
    if (idx > -1) {
        expandedMoIds.value.splice(idx, 1);
    } else {
        expandedMoIds.value.push(moId);
    }
}

async function handleSyncSubmit() {
    if (isSyncing.value) return;
    if (selectedIds.value.length === 0) {
        alert('Pilih setidaknya satu MO untuk disinkronkan pemakaian materialnya.');
        return;
    }

    isSyncing.value = true;
    errorMessage.value = null;
    try {
        const res = await axios.post(route('scm.material-usage.sync-odoo'), {
            selected_ids: Array.from(new Set(selectedIds.value)),
        });

        if (res.data?.success !== false) {
            emit('synced', res.data);
            emit('close');
            window.location.reload();
        } else {
            errorMessage.value = res.data.message || 'Gagal sinkronisasi Material Usage.';
        }
    } catch (err: any) {
        errorMessage.value =
            err.response?.data?.message ||
            err.message ||
            'Terjadi kesalahan saat sinkronisasi Material Usage.';
    } finally {
        isSyncing.value = false;
    }
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="modal-title"
        role="dialog"
        aria-modal="true"
    >
        <!-- Backdrop -->
        <div
            class="fixed inset-0 bg-slate-900/75 backdrop-blur-sm transition-opacity"
            @click="!isSyncing && emit('close')"
        ></div>

        <div class="flex min-h-screen items-center justify-center p-3 text-center sm:p-4">
            <!-- Modal Dialog -->
            <div
                class="relative flex max-h-[92vh] w-full max-w-5xl transform flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white text-left shadow-2xl transition-all dark:border-slate-800 dark:bg-slate-900"
                @click.stop
            >
                <!-- HEADER -->
                <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/70 px-6 py-4 dark:border-slate-800/80 dark:bg-slate-900/70">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-950/80 dark:text-amber-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100" id="modal-title">
                                Preview & Seleksi Sync Material Usage dari Odoo ERP
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Detail konsumsi bahan baku dan kemas per Produk Jadi & Batch dari Manufacturing Order (MO) Odoo.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300"
                        :disabled="isSyncing"
                        @click="emit('close')"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- STATS BAR -->
                <div class="grid grid-cols-2 gap-2 border-b border-slate-100 bg-slate-50/40 p-3 sm:grid-cols-4 dark:border-slate-800/60 dark:bg-slate-900/40">
                    <div class="rounded-xl border border-slate-200/80 bg-white p-2.5 text-center dark:border-slate-800 dark:bg-slate-800/60">
                        <span class="block text-[11px] font-medium text-slate-500 dark:text-slate-400">Total MO di Odoo</span>
                        <span class="text-lg font-extrabold text-slate-900 dark:text-slate-100">{{ totalCount }}</span>
                    </div>
                    <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/50 p-2.5 text-center dark:border-emerald-900/40 dark:bg-emerald-950/20">
                        <span class="block text-[11px] font-medium text-emerald-700 dark:text-emerald-400">Siap Sync (Plan Ada)</span>
                        <span class="text-lg font-extrabold text-emerald-700 dark:text-emerald-400">{{ readyCount }}</span>
                    </div>
                    <div class="rounded-xl border border-amber-200/80 bg-amber-50/50 p-2.5 text-center dark:border-amber-900/40 dark:bg-amber-950/20">
                        <span class="block text-[11px] font-medium text-amber-700 dark:text-amber-400">Belum di Weekly Plan</span>
                        <span class="text-lg font-extrabold text-amber-700 dark:text-amber-400">{{ missingCount }}</span>
                    </div>
                    <div class="rounded-xl border border-indigo-200/80 bg-indigo-50/50 p-2.5 text-center dark:border-indigo-900/40 dark:bg-indigo-950/20">
                        <span class="block text-[11px] font-medium text-indigo-700 dark:text-indigo-400">Terpilih untuk Sync</span>
                        <span class="text-lg font-extrabold text-indigo-700 dark:text-indigo-400">{{ selectedCount }}</span>
                    </div>
                </div>

                <!-- CONTROLS & FILTER -->
                <div class="border-b border-slate-100 p-4 dark:border-slate-800/80">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <!-- Search -->
                        <div class="relative flex-1">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Cari MO, Produk Jadi, Batch, atau Komponen Material..."
                                class="w-full rounded-xl border-slate-200 bg-slate-50/60 py-2 pl-9 pr-4 text-xs text-slate-800 placeholder-slate-400 focus:border-amber-500 focus:bg-white focus:ring-1 focus:ring-amber-500 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-200 dark:placeholder-slate-500"
                            />
                        </div>

                        <!-- Filter Tabs -->
                        <div class="flex flex-wrap items-center gap-1.5 text-xs">
                            <button
                                type="button"
                                class="rounded-lg px-2.5 py-1.5 font-medium transition"
                                :class="activeFilter === 'all' ? 'bg-amber-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                                @click="activeFilter = 'all'"
                            >
                                Semua ({{ totalCount }})
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-2.5 py-1.5 font-medium transition"
                                :class="activeFilter === 'ready' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                                @click="activeFilter = 'ready'"
                            >
                                Siap Sync ({{ readyCount }})
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-2.5 py-1.5 font-medium transition"
                                :class="activeFilter === 'missing' ? 'bg-amber-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                                @click="activeFilter = 'missing'"
                            >
                                Belum di Plan ({{ missingCount }})
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-2.5 py-1.5 font-medium transition"
                                :class="activeFilter === 'selected' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                                @click="activeFilter = 'selected'"
                            >
                                Terpilih ({{ selectedCount }})
                            </button>
                        </div>
                    </div>

                    <!-- Helpers -->
                    <div class="mt-2.5 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                        <div class="flex items-center gap-2">
                            <button type="button" class="font-medium text-amber-600 hover:underline dark:text-amber-400" @click="selectOnlyReady">
                                Pilih yang Siap Sync
                            </button>
                            <span>·</span>
                            <button type="button" class="font-medium text-rose-600 hover:underline dark:text-rose-400" @click="clearSelection">
                                Batalkan Pilihan
                            </button>
                        </div>
                        <div>Menampilkan {{ filteredUsages.length }} MO</div>
                    </div>
                </div>

                <!-- TABLE CONTENT -->
                <div class="flex-1 overflow-y-auto p-0">
                    <div v-if="isLoading" class="flex flex-col items-center justify-center py-16">
                        <svg class="h-8 w-8 animate-spin text-amber-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="mt-3 text-xs font-medium text-slate-500 dark:text-slate-400">Memuat rincian pemakaian material dari Odoo ERP...</p>
                    </div>

                    <div v-else-if="filteredUsages.length === 0" class="py-16 text-center">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">Tidak ada data pemakaian material</p>
                    </div>

                    <table v-else class="w-full border-collapse text-left text-xs">
                        <thead class="sticky top-0 z-10 border-b border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-200">
                            <tr>
                                <th class="w-10 px-4 py-3 text-center">
                                    <input
                                        type="checkbox"
                                        :checked="isAllFilteredSelected"
                                        class="rounded border-slate-300 text-amber-600 focus:ring-amber-500 dark:border-slate-700 dark:bg-slate-900"
                                        @change="toggleSelectAllFiltered"
                                    />
                                </th>
                                <th class="px-3 py-3 font-semibold">Nomor MO</th>
                                <th class="px-3 py-3 font-semibold">Produk Jadi (Finished Good) & Batch</th>
                                <th class="px-3 py-3 font-semibold">Komponen Bahan</th>
                                <th class="px-3 py-3 text-right font-semibold">Standar vs Aktual</th>
                                <th class="px-4 py-3 font-semibold">Status Plan</th>
                                <th class="w-10 px-3 py-3 text-center">Rincian</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                            <template v-for="u in filteredUsages" :key="u.odoo_mo_id">
                                <tr
                                    class="transition"
                                    :class="[
                                        !u.can_sync ? 'bg-slate-50/50 opacity-60 dark:bg-slate-950/30' : 'hover:bg-slate-50/80 dark:hover:bg-slate-800/40 cursor-pointer',
                                        selectedIds.includes(u.odoo_mo_id) ? 'bg-amber-50/40 dark:bg-amber-950/20' : ''
                                    ]"
                                    @click="toggleUsage(u)"
                                >
                                    <td class="px-4 py-3 text-center" @click.stop>
                                        <input
                                            type="checkbox"
                                            :checked="selectedIds.includes(u.odoo_mo_id)"
                                            :disabled="!u.can_sync"
                                            class="rounded border-slate-300 text-amber-600 focus:ring-amber-500 disabled:opacity-30 dark:border-slate-700 dark:bg-slate-900"
                                            @change="toggleUsage(u)"
                                        />
                                    </td>
                                    <td class="px-3 py-3 font-mono font-bold text-slate-900 dark:text-slate-100">
                                        {{ u.mo_name }}
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="font-semibold text-slate-900 dark:text-slate-100">
                                            {{ u.product_name }}
                                        </div>
                                        <div class="mt-0.5 flex items-center gap-1.5 font-mono text-[11px] text-slate-500">
                                            <span class="rounded bg-slate-100 px-1 py-0.5 font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                Batch: {{ u.batch_number }}
                                            </span>
                                            <span v-if="u.target_output > 0" class="text-slate-400">
                                                · Target: {{ u.target_output }} unit
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 font-semibold text-slate-700 dark:text-slate-300">
                                        {{ u.item_count }} material terpakai
                                    </td>
                                    <td class="px-3 py-3 text-right font-mono">
                                        <div class="text-slate-900 dark:text-slate-100 font-semibold">
                                            {{ u.total_qty_used }} / {{ u.total_qty_standard }}
                                        </div>
                                        <div
                                            class="text-[11px] font-bold"
                                            :class="u.overall_ratio > 0.5 ? 'text-rose-600' : 'text-emerald-600'"
                                        >
                                            Rasio: {{ u.overall_ratio }}%
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            v-if="u.plan_exists"
                                            class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                                        >
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            {{ u.usage_exists ? 'Sudah Ada Usage' : 'Plan Siap' }}
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300"
                                        >
                                            Belum sync di Weekly Plan
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-center" @click.stop>
                                        <button
                                            type="button"
                                            class="rounded-lg p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-700 dark:hover:bg-slate-700"
                                            @click="toggleExpand(u.odoo_mo_id)"
                                        >
                                            <svg
                                                class="h-4 w-4 transition-transform duration-200"
                                                :class="{ 'rotate-180': expandedMoIds.includes(u.odoo_mo_id) }"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                                <!-- EXPANDED MATERIAL ITEMS BREAKDOWN -->
                                <tr v-if="expandedMoIds.includes(u.odoo_mo_id)" class="bg-slate-50/80 dark:bg-slate-800/40">
                                    <td colspan="7" class="p-3 pl-12">
                                        <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-xs dark:border-slate-700 dark:bg-slate-900">
                                            <div class="mb-2 font-semibold text-slate-700 dark:text-slate-300">
                                                Rincian Komponen Bahan untuk Produk: <span class="text-amber-600 dark:text-amber-400 font-bold">{{ u.product_name }}</span> (Batch {{ u.batch_number }})
                                            </div>
                                            <table class="w-full text-[11px]">
                                                <thead class="border-b border-slate-100 text-slate-500 dark:border-slate-800">
                                                    <tr>
                                                        <th class="py-1.5 text-left">Nama Material / Bahan</th>
                                                        <th class="py-1.5 text-right">Standar</th>
                                                        <th class="py-1.5 text-right">Aktual</th>
                                                        <th class="py-1.5 text-right">Selisih</th>
                                                        <th class="py-1.5 text-right">Deviasi %</th>
                                                        <th class="py-1.5 text-left pl-3">Satuan</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/40">
                                                    <tr v-for="(it, iIdx) in u.items" :key="iIdx">
                                                        <td class="py-1 font-medium text-slate-800 dark:text-slate-200">
                                                            {{ it.material_name }}
                                                        </td>
                                                        <td class="py-1 text-right font-mono">{{ it.quantity_standard }}</td>
                                                        <td class="py-1 text-right font-mono font-semibold">{{ it.quantity_used }}</td>
                                                        <td
                                                            class="py-1 text-right font-mono"
                                                            :class="it.variance > 0 ? 'text-rose-600' : 'text-emerald-600'"
                                                        >
                                                            {{ it.variance > 0 ? '+' : '' }}{{ it.variance }}
                                                        </td>
                                                        <td
                                                            class="py-1 text-right font-semibold"
                                                            :class="it.ratio_persen > 0.5 ? 'text-rose-600' : 'text-emerald-600'"
                                                        >
                                                            {{ it.ratio_persen }}%
                                                        </td>
                                                        <td class="py-1 pl-3 text-slate-400">{{ it.uom ?? '-' }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- FOOTER -->
                <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/70 px-6 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800/80 dark:bg-slate-900/70">
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        <strong>{{ selectedCount }} MO</strong> dipilih untuk sinkronisasi pemakaian material.
                    </div>
                    <div class="flex items-center justify-end gap-2.5">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                            :disabled="isSyncing"
                            @click="emit('close')"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-xl bg-amber-600 px-5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-amber-700 disabled:opacity-50 dark:bg-amber-500 dark:hover:bg-amber-600"
                            :disabled="isSyncing || selectedCount === 0"
                            @click="handleSyncSubmit"
                        >
                            <svg v-if="isSyncing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ isSyncing ? 'Menyinkronkan...' : `Sinkronkan (${selectedCount}) Material Usage` }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
