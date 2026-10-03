<script setup lang="ts">
import axios from 'axios';
import { computed, ref, watch } from 'vue';

interface OdooStockItem {
    odoo_id: number;
    name: string;
    code: string;
    item_type: string;
    uom: string | null;
    odoo_qty: number;
    local_qty: number;
    difference: number;
    exists_in_local: boolean;
    local_id: number | null;
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
const stocks = ref<OdooStockItem[]>([]);
const selectedIds = ref<number[]>([]);
const searchQuery = ref('');
const activeTypeFilter = ref<string>('all');
const diffOnly = ref(false);

watch(
    () => props.show,
    (val) => {
        if (val) {
            fetchPreview();
        } else {
            errorMessage.value = null;
            searchQuery.value = '';
            activeTypeFilter.value = 'all';
            diffOnly.value = false;
        }
    },
);

async function fetchPreview() {
    isLoading.value = true;
    errorMessage.value = null;
    try {
        const res = await axios.get(route('scm.saldo-stok.preview-odoo'));
        if (res.data.success) {
            stocks.value = res.data.data || [];
            selectedIds.value = stocks.value.map((s) => s.odoo_id);
        } else {
            errorMessage.value = res.data.message || 'Gagal memuat saldo stok dari Odoo.';
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
const totalCount = computed(() => stocks.value.length);
const diffCount = computed(() => stocks.value.filter((s) => Math.abs(s.difference) > 0.001).length);
const selectedCount = computed(() => selectedIds.value.length);

// Filtered list
const filteredStocks = computed(() => {
    let list = stocks.value;

    if (activeTypeFilter.value !== 'all') {
        list = list.filter((s) => s.item_type === activeTypeFilter.value);
    }

    if (diffOnly.value) {
        list = list.filter((s) => Math.abs(s.difference) > 0.001);
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim();
        list = list.filter(
            (s) =>
                s.name.toLowerCase().includes(q) ||
                s.code.toLowerCase().includes(q) ||
                (s.uom && s.uom.toLowerCase().includes(q)),
        );
    }

    return list;
});

const isAllFilteredSelected = computed(() => {
    if (filteredStocks.value.length === 0) return false;
    const set = new Set(selectedIds.value);
    return filteredStocks.value.every((s) => set.has(s.odoo_id));
});

function toggleSelectAllFiltered() {
    const set = new Set(selectedIds.value);
    if (isAllFilteredSelected.value) {
        filteredStocks.value.forEach((s) => set.delete(s.odoo_id));
    } else {
        filteredStocks.value.forEach((s) => set.add(s.odoo_id));
    }
    selectedIds.value = Array.from(set);
}

function selectOnlyDiff() {
    selectedIds.value = stocks.value
        .filter((s) => Math.abs(s.difference) > 0.001)
        .map((s) => s.odoo_id);
}

function clearSelection() {
    selectedIds.value = [];
}

function toggleStock(stock: OdooStockItem) {
    const idx = selectedIds.value.indexOf(stock.odoo_id);
    if (idx > -1) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(stock.odoo_id);
    }
}

async function handleSyncSubmit() {
    if (isSyncing.value) return;
    if (selectedIds.value.length === 0) {
        alert('Pilih setidaknya satu item stok untuk disinkronkan.');
        return;
    }

    isSyncing.value = true;
    errorMessage.value = null;
    try {
        const res = await axios.post(route('scm.saldo-stok.sync-odoo'), {
            selected_ids: Array.from(new Set(selectedIds.value)),
        });

        if (res.data.success) {
            emit('synced', res.data);
            emit('close');
        } else {
            errorMessage.value = res.data.message || 'Gagal sinkronisasi saldo stok.';
        }
    } catch (err: any) {
        errorMessage.value =
            err.response?.data?.message ||
            err.message ||
            'Terjadi kesalahan saat sinkronisasi saldo stok.';
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
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-950/80 dark:text-emerald-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100" id="modal-title">
                                Preview & Seleksi Sync Saldo Stok dari Odoo
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Bandingkan stok real-time Odoo ERP dengan database lokal dan pilih item yang ingin disinkronkan.
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
                        <span class="block text-[11px] font-medium text-slate-500 dark:text-slate-400">Total Item Produk</span>
                        <span class="text-lg font-extrabold text-slate-900 dark:text-slate-100">{{ totalCount }}</span>
                    </div>
                    <div class="rounded-xl border border-amber-200/80 bg-amber-50/50 p-2.5 text-center dark:border-amber-900/40 dark:bg-amber-950/20">
                        <span class="block text-[11px] font-medium text-amber-700 dark:text-amber-400">Ada Selisih Qty</span>
                        <span class="text-lg font-extrabold text-amber-700 dark:text-amber-400">{{ diffCount }}</span>
                    </div>
                    <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/50 p-2.5 text-center dark:border-emerald-900/40 dark:bg-emerald-950/20">
                        <span class="block text-[11px] font-medium text-emerald-700 dark:text-emerald-400">Terpilih untuk Sync</span>
                        <span class="text-lg font-extrabold text-emerald-700 dark:text-emerald-400">{{ selectedCount }}</span>
                    </div>
                    <div class="rounded-xl border border-slate-200/80 bg-white p-2.5 text-center dark:border-slate-800 dark:bg-slate-800/60">
                        <span class="block text-[11px] font-medium text-slate-500 dark:text-slate-400">Mode Filter</span>
                        <label class="mt-0.5 inline-flex cursor-pointer items-center gap-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200">
                            <input v-model="diffOnly" type="checkbox" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                            <span>Hanya Selisih</span>
                        </label>
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
                                placeholder="Cari Kode, Nama Barang, Satuan..."
                                class="w-full rounded-xl border-slate-200 bg-slate-50/60 py-2 pl-9 pr-4 text-xs text-slate-800 placeholder-slate-400 focus:border-emerald-500 focus:bg-white focus:ring-1 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-200 dark:placeholder-slate-500"
                            />
                        </div>

                        <!-- Type Filters -->
                        <div class="flex flex-wrap items-center gap-1.5 text-xs">
                            <button
                                type="button"
                                class="rounded-lg px-2.5 py-1.5 font-medium transition"
                                :class="activeTypeFilter === 'all' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                                @click="activeTypeFilter = 'all'"
                            >
                                Semua
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-2.5 py-1.5 font-medium transition"
                                :class="activeTypeFilter === 'fg' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                                @click="activeTypeFilter === 'fg'"
                            >
                                FG (Finish Goods)
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-2.5 py-1.5 font-medium transition"
                                :class="activeTypeFilter === 'rm' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                                @click="activeTypeFilter === 'rm'"
                            >
                                RM (Bahan Baku)
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-2.5 py-1.5 font-medium transition"
                                :class="activeTypeFilter === 'pm' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                                @click="activeTypeFilter === 'pm'"
                            >
                                PM (Kemas)
                            </button>
                        </div>
                    </div>

                    <!-- Helpers -->
                    <div class="mt-2.5 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                        <div class="flex items-center gap-2">
                            <button type="button" class="font-medium text-emerald-600 hover:underline dark:text-emerald-400" @click="selectOnlyDiff">
                                Pilih yang Ada Selisih
                            </button>
                            <span>·</span>
                            <button type="button" class="font-medium text-slate-600 hover:underline dark:text-slate-300" @click="selectedIds = stocks.map(s => s.odoo_id)">
                                Pilih Semua
                            </button>
                            <span>·</span>
                            <button type="button" class="font-medium text-rose-600 hover:underline dark:text-rose-400" @click="clearSelection">
                                Batalkan Pilihan
                            </button>
                        </div>
                        <div>Menampilkan {{ filteredStocks.length }} item</div>
                    </div>
                </div>

                <!-- TABLE CONTENT -->
                <div class="flex-1 overflow-y-auto p-0">
                    <div v-if="isLoading" class="flex flex-col items-center justify-center py-16">
                        <svg class="h-8 w-8 animate-spin text-emerald-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="mt-3 text-xs font-medium text-slate-500 dark:text-slate-400">Mengambil saldo stok dari Odoo ERP...</p>
                    </div>

                    <div v-else-if="filteredStocks.length === 0" class="py-16 text-center">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">Tidak ada data stok yang cocok</p>
                    </div>

                    <table v-else class="w-full border-collapse text-left text-xs">
                        <thead class="sticky top-0 z-10 border-b border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-200">
                            <tr>
                                <th class="w-10 px-4 py-3 text-center">
                                    <input
                                        type="checkbox"
                                        :checked="isAllFilteredSelected"
                                        class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-900"
                                        @change="toggleSelectAllFiltered"
                                    />
                                </th>
                                <th class="px-3 py-3 font-semibold">Kode / SKU</th>
                                <th class="px-3 py-3 font-semibold">Nama Barang</th>
                                <th class="px-3 py-3 font-semibold">Kategori</th>
                                <th class="px-3 py-3 text-right font-semibold">Stok di Odoo</th>
                                <th class="px-3 py-3 text-right font-semibold">Stok Lokal Saat Ini</th>
                                <th class="px-4 py-3 text-right font-semibold">Selisih</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                            <tr
                                v-for="stock in filteredStocks"
                                :key="stock.odoo_id"
                                class="transition cursor-pointer hover:bg-slate-50/80 dark:hover:bg-slate-800/40"
                                :class="selectedIds.includes(stock.odoo_id) ? 'bg-emerald-50/30 dark:bg-emerald-950/20' : ''"
                                @click="toggleStock(stock)"
                            >
                                <td class="px-4 py-3 text-center" @click.stop>
                                    <input
                                        type="checkbox"
                                        :checked="selectedIds.includes(stock.odoo_id)"
                                        class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-900"
                                        @change="toggleStock(stock)"
                                    />
                                </td>
                                <td class="px-3 py-3 font-mono text-[11px] text-slate-700 dark:text-slate-300">
                                    {{ stock.code }}
                                </td>
                                <td class="px-3 py-3 font-medium text-slate-900 dark:text-slate-100">
                                    {{ stock.name }}
                                </td>
                                <td class="px-3 py-3">
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                        {{ stock.item_type }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-right font-semibold text-slate-900 dark:text-slate-100">
                                    {{ stock.odoo_qty.toLocaleString('id-ID') }} {{ stock.uom || '' }}
                                </td>
                                <td class="px-3 py-3 text-right text-slate-600 dark:text-slate-400">
                                    {{ stock.local_qty.toLocaleString('id-ID') }} {{ stock.uom || '' }}
                                </td>
                                <td class="px-4 py-3 text-right font-bold">
                                    <span
                                        v-if="Math.abs(stock.difference) > 0.001"
                                        :class="stock.difference > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
                                    >
                                        {{ stock.difference > 0 ? '+' : '' }}{{ stock.difference.toLocaleString('id-ID') }}
                                    </span>
                                    <span v-else class="text-slate-400">
                                        Sama
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- FOOTER -->
                <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/70 px-6 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800/80 dark:bg-slate-900/70">
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        <strong>{{ selectedCount }} item</strong> dipilih untuk disinkronkan.
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
                            class="flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-50 dark:bg-emerald-500 dark:hover:bg-emerald-600"
                            :disabled="isSyncing || selectedCount === 0"
                            @click="handleSyncSubmit"
                        >
                            <svg v-if="isSyncing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ isSyncing ? 'Menyinkronkan...' : `Sinkronkan (${selectedCount}) Item Terpilih` }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
