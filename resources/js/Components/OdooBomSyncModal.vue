<script setup lang="ts">
import axios from 'axios';
import { computed, ref, watch } from 'vue';

interface OdooBomItem {
    odoo_bom_id: number;
    name: string;
    version: string;
    line_count: number;
    odoo_product_tmpl_id: number | null;
    exists_in_local: boolean;
    local_produk_id: number | null;
    local_produk_name: string | null;
    bom_exists: boolean;
    can_sync: boolean;
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
const boms = ref<OdooBomItem[]>([]);
const selectedIds = ref<number[]>([]);
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
        }
    },
);

async function fetchPreview() {
    isLoading.value = true;
    errorMessage.value = null;
    try {
        const res = await axios.get(route('scm.bom.preview-odoo'));
        if (res.data.success) {
            boms.value = res.data.data || [];
            selectedIds.value = boms.value.filter((b) => b.can_sync).map((b) => b.odoo_bom_id);
        } else {
            errorMessage.value = res.data.message || 'Gagal memuat daftar BOM dari Odoo.';
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
const totalCount = computed(() => boms.value.length);
const readyCount = computed(() => boms.value.filter((b) => b.can_sync && !b.bom_exists).length);
const existsCount = computed(() => boms.value.filter((b) => b.bom_exists).length);
const missingCount = computed(() => boms.value.filter((b) => !b.can_sync).length);
const selectedCount = computed(() => selectedIds.value.length);

// Filtered list
const filteredBoms = computed(() => {
    let list = boms.value;

    if (activeFilter.value === 'ready') {
        list = list.filter((b) => b.can_sync && !b.bom_exists);
    } else if (activeFilter.value === 'missing') {
        list = list.filter((b) => !b.can_sync);
    } else if (activeFilter.value === 'exists') {
        list = list.filter((b) => b.bom_exists);
    } else if (activeFilter.value === 'selected') {
        const set = new Set(selectedIds.value);
        list = list.filter((b) => set.has(b.odoo_bom_id));
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim();
        list = list.filter(
            (b) =>
                b.name.toLowerCase().includes(q) ||
                b.version.toLowerCase().includes(q) ||
                (b.local_produk_name && b.local_produk_name.toLowerCase().includes(q)),
        );
    }

    return list;
});

const isAllFilteredSelected = computed(() => {
    if (filteredBoms.value.length === 0) return false;
    const syncableFiltered = filteredBoms.value.filter((b) => b.can_sync);
    if (syncableFiltered.length === 0) return false;
    const set = new Set(selectedIds.value);
    return syncableFiltered.every((b) => set.has(b.odoo_bom_id));
});

function toggleSelectAllFiltered() {
    const set = new Set(selectedIds.value);
    const syncableFiltered = filteredBoms.value.filter((b) => b.can_sync);
    
    if (isAllFilteredSelected.value) {
        syncableFiltered.forEach((b) => set.delete(b.odoo_bom_id));
    } else {
        syncableFiltered.forEach((b) => set.add(b.odoo_bom_id));
    }
    selectedIds.value = Array.from(set);
}

function selectOnlyReady() {
    selectedIds.value = boms.value.filter((b) => b.can_sync).map((b) => b.odoo_bom_id);
}

function clearSelection() {
    selectedIds.value = [];
}

function toggleBom(bom: OdooBomItem) {
    if (!bom.can_sync) return;
    const idx = selectedIds.value.indexOf(bom.odoo_bom_id);
    if (idx > -1) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(bom.odoo_bom_id);
    }
}

async function handleSyncSubmit() {
    if (isSyncing.value) return;
    if (selectedIds.value.length === 0) {
        alert('Pilih setidaknya satu BOM untuk disinkronkan.');
        return;
    }

    isSyncing.value = true;
    errorMessage.value = null;
    try {
        const res = await axios.post(route('scm.bom.sync-odoo'), {
            selected_ids: Array.from(new Set(selectedIds.value)),
        });

        if (res.data.success) {
            emit('synced', res.data);
            emit('close');
        } else {
            errorMessage.value = res.data.message || 'Gagal sinkronisasi BOM.';
        }
    } catch (err: any) {
        errorMessage.value =
            err.response?.data?.message ||
            err.message ||
            'Terjadi kesalahan saat sinkronisasi BOM.';
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
                class="relative flex max-h-[92vh] w-full max-w-4xl transform flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white text-left shadow-2xl transition-all dark:border-slate-800 dark:bg-slate-900"
                @click.stop
            >
                <!-- HEADER -->
                <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/70 px-6 py-4 dark:border-slate-800/80 dark:bg-slate-900/70">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-100 text-purple-600 dark:bg-purple-950/80 dark:text-purple-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100" id="modal-title">
                                Preview & Seleksi Sync BOM (Bill of Materials) dari Odoo
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Pilih formula / resep BOM dari Odoo ERP untuk disinkronkan ke database lokal.
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
                        <span class="block text-[11px] font-medium text-slate-500 dark:text-slate-400">Total BOM di Odoo</span>
                        <span class="text-lg font-extrabold text-slate-900 dark:text-slate-100">{{ totalCount }}</span>
                    </div>
                    <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/50 p-2.5 text-center dark:border-emerald-900/40 dark:bg-emerald-950/20">
                        <span class="block text-[11px] font-medium text-emerald-700 dark:text-emerald-400">Siap Sync</span>
                        <span class="text-lg font-extrabold text-emerald-700 dark:text-emerald-400">{{ readyCount }}</span>
                    </div>
                    <div class="rounded-xl border border-amber-200/80 bg-amber-50/50 p-2.5 text-center dark:border-amber-900/40 dark:bg-amber-950/20">
                        <span class="block text-[11px] font-medium text-amber-700 dark:text-amber-400">Produk Belum Ada</span>
                        <span class="text-lg font-extrabold text-amber-700 dark:text-amber-400">{{ missingCount }}</span>
                    </div>
                    <div class="rounded-xl border border-purple-200/80 bg-purple-50/50 p-2.5 text-center dark:border-purple-900/40 dark:bg-purple-950/20">
                        <span class="block text-[11px] font-medium text-purple-700 dark:text-purple-400">Terpilih untuk Sync</span>
                        <span class="text-lg font-extrabold text-purple-700 dark:text-purple-400">{{ selectedCount }}</span>
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
                                placeholder="Cari BOM, Produk, Versi..."
                                class="w-full rounded-xl border-slate-200 bg-slate-50/60 py-2 pl-9 pr-4 text-xs text-slate-800 placeholder-slate-400 focus:border-purple-500 focus:bg-white focus:ring-1 focus:ring-purple-500 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-200 dark:placeholder-slate-500"
                            />
                        </div>

                        <!-- Filter Tabs -->
                        <div class="flex flex-wrap items-center gap-1.5 text-xs">
                            <button
                                type="button"
                                class="rounded-lg px-2.5 py-1.5 font-medium transition"
                                :class="activeFilter === 'all' ? 'bg-purple-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
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
                                Belum Ada di Lokal ({{ missingCount }})
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-2.5 py-1.5 font-medium transition"
                                :class="activeFilter === 'selected' ? 'bg-purple-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                                @click="activeFilter = 'selected'"
                            >
                                Terpilih ({{ selectedCount }})
                            </button>
                        </div>
                    </div>

                    <!-- Helpers -->
                    <div class="mt-2.5 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                        <div class="flex items-center gap-2">
                            <button type="button" class="font-medium text-purple-600 hover:underline dark:text-purple-400" @click="selectOnlyReady">
                                Pilih yang Siap Sync
                            </button>
                            <span>·</span>
                            <button type="button" class="font-medium text-rose-600 hover:underline dark:text-rose-400" @click="clearSelection">
                                Batalkan Pilihan
                            </button>
                        </div>
                        <div>Menampilkan {{ filteredBoms.length }} BOM</div>
                    </div>
                </div>

                <!-- TABLE CONTENT -->
                <div class="flex-1 overflow-y-auto p-0">
                    <div v-if="isLoading" class="flex flex-col items-center justify-center py-16">
                        <svg class="h-8 w-8 animate-spin text-purple-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="mt-3 text-xs font-medium text-slate-500 dark:text-slate-400">Mengambil formula BOM dari Odoo ERP...</p>
                    </div>

                    <div v-else-if="filteredBoms.length === 0" class="py-16 text-center">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">Tidak ada data BOM</p>
                    </div>

                    <table v-else class="w-full border-collapse text-left text-xs">
                        <thead class="sticky top-0 z-10 border-b border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-200">
                            <tr>
                                <th class="w-10 px-4 py-3 text-center">
                                    <input
                                        type="checkbox"
                                        :checked="isAllFilteredSelected"
                                        class="rounded border-slate-300 text-purple-600 focus:ring-purple-500 dark:border-slate-700 dark:bg-slate-900"
                                        @change="toggleSelectAllFiltered"
                                    />
                                </th>
                                <th class="px-3 py-3 font-semibold">Produk Odoo</th>
                                <th class="px-3 py-3 font-semibold">Versi / Kode BOM</th>
                                <th class="px-3 py-3 font-semibold">Komponen Bahan</th>
                                <th class="px-4 py-3 font-semibold">Status Relasi Lokal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                            <tr
                                v-for="bom in filteredBoms"
                                :key="bom.odoo_bom_id"
                                class="transition"
                                :class="[
                                    !bom.can_sync ? 'bg-slate-50/50 opacity-60 dark:bg-slate-950/30' : 'hover:bg-slate-50/80 dark:hover:bg-slate-800/40 cursor-pointer',
                                    selectedIds.includes(bom.odoo_bom_id) ? 'bg-purple-50/40 dark:bg-purple-950/20' : ''
                                ]"
                                @click="toggleBom(bom)"
                            >
                                <td class="px-4 py-3 text-center" @click.stop>
                                    <input
                                        type="checkbox"
                                        :checked="selectedIds.includes(bom.odoo_bom_id)"
                                        :disabled="!bom.can_sync"
                                        class="rounded border-slate-300 text-purple-600 focus:ring-purple-500 disabled:opacity-30 dark:border-slate-700 dark:bg-slate-900"
                                        @change="toggleBom(bom)"
                                    />
                                </td>
                                <td class="px-3 py-3 font-medium text-slate-900 dark:text-slate-100">
                                    {{ bom.name }}
                                </td>
                                <td class="px-3 py-3 font-mono text-[11px] text-slate-600 dark:text-slate-400">
                                    {{ bom.version }}
                                </td>
                                <td class="px-3 py-3 font-semibold text-slate-700 dark:text-slate-300">
                                    {{ bom.line_count }} item material
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="bom.exists_in_local"
                                        class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                                    >
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Cocok: {{ bom.local_produk_name }}
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300"
                                    >
                                        Produk belum ada di lokal
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- FOOTER -->
                <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/70 px-6 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800/80 dark:bg-slate-900/70">
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        <strong>{{ selectedCount }} BOM</strong> dipilih untuk disinkronkan.
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
                            class="flex items-center gap-2 rounded-xl bg-purple-600 px-5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-purple-700 disabled:opacity-50 dark:bg-purple-500 dark:hover:bg-purple-600"
                            :disabled="isSyncing || selectedCount === 0"
                            @click="handleSyncSubmit"
                        >
                            <svg v-if="isSyncing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ isSyncing ? 'Menyinkronkan...' : `Sinkronkan (${selectedCount}) BOM Terpilih` }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
