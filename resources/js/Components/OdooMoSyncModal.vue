<script setup lang="ts">
import axios from 'axios';
import { computed, ref, watch } from 'vue';

interface OdooMoItem {
    odoo_mo_id: number;
    name: string;
    origin: string;
    odoo_product_id: number;
    product_name: string;
    exists_in_local: boolean;
    local_product_id: number | null;
    local_product_name: string | null;
    batch_number: string;
    proses?: string;
    target_output: number;
    tanggal: string;
    state: string;
    mo_status: string;
    plan_exists: boolean;
    plan_status: string | null;
    can_sync: boolean;
    sync_status: 'ready' | 'exists' | 'missing_product' | 'cancelled';
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
            result: {
                created: number;
                updated: number;
                cancelled: number;
                skipped: number;
                errors: string[];
            };
        },
    ): void;
}>();

const isLoading = ref(false);
const isSyncing = ref(false);
const errorMessage = ref<string | null>(null);
const mos = ref<OdooMoItem[]>([]);
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
        const res = await axios.get(route('ppic.odoo.mo-list'));
        if (res.data.success) {
            mos.value = res.data.data || [];
            // By default, select all items that CAN be synced and are not cancelled
            selectedIds.value = mos.value
                .filter((m) => m.can_sync && m.state !== 'cancel' && m.state !== 'cancelled')
                .map((m) => m.odoo_mo_id);
        } else {
            errorMessage.value = res.data.message || 'Gagal memuat daftar MO dari Odoo.';
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
const totalCount = computed(() => mos.value.length);
const readyCount = computed(() => mos.value.filter((m) => m.can_sync && !m.plan_exists && m.state !== 'cancel' && m.state !== 'cancelled').length);
const existsCount = computed(() => mos.value.filter((m) => m.plan_exists).length);
const missingCount = computed(() => mos.value.filter((m) => !m.exists_in_local && !m.plan_exists).length);
const selectedCount = computed(() => selectedIds.value.length);

// Filtered list
const filteredMos = computed(() => {
    let list = mos.value;

    if (activeFilter.value === 'ready') {
        list = list.filter((m) => m.can_sync && !m.plan_exists && m.state !== 'cancel' && m.state !== 'cancelled');
    } else if (activeFilter.value === 'missing') {
        list = list.filter((m) => !m.exists_in_local && !m.plan_exists);
    } else if (activeFilter.value === 'exists') {
        list = list.filter((m) => m.plan_exists);
    } else if (activeFilter.value === 'selected') {
        const set = new Set(selectedIds.value);
        list = list.filter((m) => set.has(m.odoo_mo_id));
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim();
        list = list.filter(
            (m) =>
                m.name.toLowerCase().includes(q) ||
                m.product_name.toLowerCase().includes(q) ||
                (m.batch_number && m.batch_number.toLowerCase().includes(q)) ||
                (m.origin && m.origin.toLowerCase().includes(q)) ||
                (m.local_product_name && m.local_product_name.toLowerCase().includes(q)),
        );
    }

    return list;
});

const isAllFilteredSelected = computed(() => {
    if (filteredMos.value.length === 0) return false;
    const syncableFiltered = filteredMos.value.filter((m) => m.can_sync);
    if (syncableFiltered.length === 0) return false;
    const set = new Set(selectedIds.value);
    return syncableFiltered.every((m) => set.has(m.odoo_mo_id));
});

function toggleSelectAllFiltered() {
    const set = new Set(selectedIds.value);
    const syncableFiltered = filteredMos.value.filter((m) => m.can_sync);
    
    if (isAllFilteredSelected.value) {
        syncableFiltered.forEach((m) => set.delete(m.odoo_mo_id));
    } else {
        syncableFiltered.forEach((m) => set.add(m.odoo_mo_id));
    }
    selectedIds.value = Array.from(set);
}

function selectOnlyReady() {
    selectedIds.value = mos.value
        .filter((m) => m.can_sync && m.state !== 'cancel' && m.state !== 'cancelled')
        .map((m) => m.odoo_mo_id);
}

function clearSelection() {
    selectedIds.value = [];
}

function selectAll() {
    selectedIds.value = mos.value
        .filter((m) => m.can_sync)
        .map((m) => m.odoo_mo_id);
}

function toggleMo(mo: OdooMoItem) {
    if (!mo.can_sync) return;
    const idx = selectedIds.value.indexOf(mo.odoo_mo_id);
    if (idx > -1) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(mo.odoo_mo_id);
    }
}

async function handleSyncSubmit() {
    if (isSyncing.value) return;
    if (selectedIds.value.length === 0) {
        alert('Pilih setidaknya satu MO untuk disinkronkan.');
        return;
    }

    isSyncing.value = true;
    errorMessage.value = null;
    try {
        const res = await axios.post(route('ppic.odoo.mo-sync'), {
            selected_ids: Array.from(new Set(selectedIds.value)),
        });

        if (res.data.success) {
            emit('synced', res.data);
            emit('close');
        } else {
            errorMessage.value = res.data.message || 'Gagal sinkronisasi MO.';
        }
    } catch (err: any) {
        errorMessage.value =
            err.response?.data?.message ||
            err.message ||
            'Terjadi kesalahan saat sinkronisasi MO.';
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

        <div
            class="flex min-h-screen items-center justify-center p-3 text-center sm:p-4"
        >
            <!-- Modal Dialog -->
            <div
                class="relative flex max-h-[92vh] w-full max-w-5xl transform flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white text-left shadow-2xl transition-all dark:border-slate-800 dark:bg-slate-900"
                @click.stop
            >
                <!-- HEADER -->
                <div
                    class="flex items-center justify-between border-b border-slate-100 bg-slate-50/70 px-6 py-4 dark:border-slate-800/80 dark:bg-slate-900/70"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600 dark:bg-indigo-950/80 dark:text-indigo-400"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3"
                                />
                            </svg>
                        </div>
                        <div>
                            <h3
                                class="text-base font-bold text-slate-900 dark:text-slate-100"
                                id="modal-title"
                            >
                                Preview & Seleksi Sync Odoo Manufacturing Orders (MO)
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Pilih Manufacturing Order dari Odoo ERP yang ingin dimasukkan atau diperbarui ke Plan Produksi (WPS).
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
                <div
                    class="grid grid-cols-2 gap-2 border-b border-slate-100 bg-slate-50/40 p-3 sm:grid-cols-5 dark:border-slate-800/60 dark:bg-slate-900/40"
                >
                    <div class="rounded-xl border border-slate-200/80 bg-white p-2.5 text-center dark:border-slate-800 dark:bg-slate-800/60">
                        <span class="block text-[11px] font-medium text-slate-500 dark:text-slate-400">Total di Odoo</span>
                        <span class="text-lg font-extrabold text-slate-900 dark:text-slate-100">{{ totalCount }}</span>
                    </div>
                    <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/50 p-2.5 text-center dark:border-emerald-900/40 dark:bg-emerald-950/20">
                        <span class="block text-[11px] font-medium text-emerald-700 dark:text-emerald-400">Siap Sync (Baru)</span>
                        <span class="text-lg font-extrabold text-emerald-700 dark:text-emerald-400">{{ readyCount }}</span>
                    </div>
                    <div class="rounded-xl border border-sky-200/80 bg-sky-50/50 p-2.5 text-center dark:border-sky-900/40 dark:bg-sky-950/20">
                        <span class="block text-[11px] font-medium text-sky-700 dark:text-sky-400">Sudah Ada di WPS</span>
                        <span class="text-lg font-extrabold text-sky-700 dark:text-sky-400">{{ existsCount }}</span>
                    </div>
                    <div class="rounded-xl border border-amber-200/80 bg-amber-50/50 p-2.5 text-center dark:border-amber-900/40 dark:bg-amber-950/20">
                        <span class="block text-[11px] font-medium text-amber-700 dark:text-amber-400">Produk Belum Ada</span>
                        <span class="text-lg font-extrabold text-amber-700 dark:text-amber-400">{{ missingCount }}</span>
                    </div>
                    <div class="col-span-2 rounded-xl border border-indigo-200/80 bg-indigo-50/50 p-2.5 text-center sm:col-span-1 dark:border-indigo-900/40 dark:bg-indigo-950/20">
                        <span class="block text-[11px] font-medium text-indigo-700 dark:text-indigo-400">Terpilih untuk Sync</span>
                        <span class="text-lg font-extrabold text-indigo-700 dark:text-indigo-400">{{ selectedCount }}</span>
                    </div>
                </div>

                <!-- CONTROLS & FILTER -->
                <div class="border-b border-slate-100 p-4 dark:border-slate-800/80">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <!-- Search Box -->
                        <div class="relative flex-1">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Cari No MO, Produk, Batch..."
                                class="w-full rounded-xl border-slate-200 bg-slate-50/60 py-2 pl-9 pr-4 text-xs text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-1 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-200 dark:placeholder-slate-500"
                            />
                        </div>

                        <!-- Filter Tabs -->
                        <div class="flex flex-wrap items-center gap-1.5 text-xs">
                            <button
                                type="button"
                                class="rounded-lg px-2.5 py-1.5 font-medium transition"
                                :class="activeFilter === 'all' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
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
                                Produk Belum Ada ({{ missingCount }})
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-2.5 py-1.5 font-medium transition"
                                :class="activeFilter === 'exists' ? 'bg-sky-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                                @click="activeFilter = 'exists'"
                            >
                                Di WPS ({{ existsCount }})
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

                    <!-- Action Helpers -->
                    <div class="mt-2.5 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                @click="selectOnlyReady"
                            >
                                Pilih yang Siap Sync
                            </button>
                            <span>·</span>
                            <button
                                type="button"
                                class="font-medium text-slate-600 hover:underline dark:text-slate-300"
                                @click="selectAll"
                            >
                                Pilih Semua
                            </button>
                            <span>·</span>
                            <button
                                type="button"
                                class="font-medium text-rose-600 hover:underline dark:text-rose-400"
                                @click="clearSelection"
                            >
                                Batalkan Pilihan
                            </button>
                        </div>
                        <div>
                            Menampilkan {{ filteredMos.length }} data MO
                        </div>
                    </div>
                </div>

                <!-- ERROR / WARNING BOX -->
                <div v-if="errorMessage" class="border-b border-rose-100 bg-rose-50 px-6 py-3 text-xs text-rose-700 dark:border-rose-900/30 dark:bg-rose-950/30 dark:text-rose-400">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ errorMessage }}</span>
                    </div>
                </div>

                <div v-if="missingCount > 0" class="border-b border-amber-100 bg-amber-50/60 px-6 py-2.5 text-xs text-amber-800 dark:border-amber-900/30 dark:bg-amber-950/30 dark:text-amber-300">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Terdapat <strong>{{ missingCount }} MO</strong> yang produknya belum ada di database lokal (tidak dapat dicentang). Silakan sinkronkan Master Produk terlebih dahulu jika ingin mengimpor MO ini.</span>
                        </div>
                    </div>
                </div>

                <!-- TABLE CONTENT -->
                <div class="flex-1 overflow-y-auto p-0">
                    <!-- Loading State -->
                    <div v-if="isLoading" class="flex flex-col items-center justify-center py-16">
                        <svg class="h-8 w-8 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="mt-3 text-xs font-medium text-slate-500 dark:text-slate-400">Mengambil data Manufacturing Orders dari Odoo ERP...</p>
                    </div>

                    <!-- Empty State -->
                    <div v-else-if="filteredMos.length === 0" class="py-16 text-center">
                        <svg class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p class="mt-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Tidak ada data MO yang sesuai filter</p>
                        <p class="text-xs text-slate-400">Coba ubah kata kunci pencarian atau tab filter di atas.</p>
                    </div>

                    <!-- Table -->
                    <table v-else class="w-full border-collapse text-left text-xs">
                        <thead class="sticky top-0 z-10 border-b border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-200">
                            <tr>
                                <th class="w-10 px-4 py-3 text-center">
                                    <input
                                        type="checkbox"
                                        :checked="isAllFilteredSelected"
                                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900"
                                        @change="toggleSelectAllFiltered"
                                    />
                                </th>
                                <th class="px-3 py-3 font-semibold">No MO / Ref</th>
                                <th class="px-3 py-3 font-semibold">Produk (Odoo & Lokal)</th>
                                <th class="px-3 py-3 font-semibold">Proses</th>
                                <th class="px-3 py-3 font-semibold">No Batch</th>
                                <th class="px-3 py-3 font-semibold">Target Qty</th>
                                <th class="px-3 py-3 font-semibold">Tanggal</th>
                                <th class="px-3 py-3 font-semibold">Status Odoo</th>
                                <th class="px-4 py-3 font-semibold">Status Sync WPS</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                            <tr
                                v-for="mo in filteredMos"
                                :key="mo.odoo_mo_id"
                                class="transition"
                                :class="[
                                    !mo.can_sync ? 'bg-slate-50/50 opacity-60 dark:bg-slate-950/30' : 'hover:bg-slate-50/80 dark:hover:bg-slate-800/40 cursor-pointer',
                                    selectedIds.includes(mo.odoo_mo_id) ? 'bg-indigo-50/40 dark:bg-indigo-950/20' : ''
                                ]"
                                @click="toggleMo(mo)"
                            >
                                <td class="px-4 py-3 text-center" @click.stop>
                                    <input
                                        type="checkbox"
                                        :checked="selectedIds.includes(mo.odoo_mo_id)"
                                        :disabled="!mo.can_sync"
                                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-30 dark:border-slate-700 dark:bg-slate-900"
                                        @change="toggleMo(mo)"
                                    />
                                </td>
                                <td class="px-3 py-3">
                                    <div class="font-bold text-slate-900 dark:text-slate-100">{{ mo.name }}</div>
                                    <div v-if="mo.origin && mo.origin !== '-'" class="text-[11px] text-slate-400">
                                        Ref: {{ mo.origin }}
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="font-medium text-slate-800 dark:text-slate-200">
                                        {{ mo.product_name }}
                                    </div>
                                    <div v-if="mo.exists_in_local" class="mt-0.5 flex items-center gap-1 text-[11px] text-emerald-600 dark:text-emerald-400">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span>Tersambung ke: {{ mo.local_product_name }}</span>
                                    </div>
                                    <div v-else class="mt-0.5 flex items-center gap-1 text-[11px] font-semibold text-amber-600 dark:text-amber-400">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <span>Produk belum ada di lokal</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3">
                                    <span
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide"
                                        :class="
                                            mo.proses === 'mixing'
                                                ? 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200'
                                                : mo.proses === 'filling'
                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                                                : 'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-200'
                                        "
                                    >
                                        {{ mo.proses || 'mixing' }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 font-mono text-[11px] text-slate-700 dark:text-slate-300">
                                    {{ mo.batch_number || '-' }}
                                </td>
                                <td class="px-3 py-3 font-semibold text-slate-900 dark:text-slate-100">
                                    {{ mo.target_output.toLocaleString('id-ID') }}
                                </td>
                                <td class="px-3 py-3 text-slate-600 dark:text-slate-400">
                                    {{ mo.tanggal }}
                                </td>
                                <td class="px-3 py-3">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold capitalize"
                                        :class="{
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': mo.state === 'done',
                                            'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300': mo.state === 'progress',
                                            'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300': mo.state === 'confirmed',
                                            'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300': mo.state === 'cancel' || mo.state === 'cancelled',
                                            'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300': !['done', 'progress', 'confirmed', 'cancel', 'cancelled'].includes(mo.state),
                                        }"
                                    >
                                        {{ mo.state }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="mo.plan_exists"
                                        class="inline-flex items-center gap-1 rounded-full bg-sky-100 px-2.5 py-0.5 text-[10px] font-semibold text-sky-800 dark:bg-sky-950 dark:text-sky-300"
                                    >
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        Update Plan ({{ mo.plan_status }})
                                    </span>
                                    <span
                                        v-else-if="mo.can_sync"
                                        class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                                    >
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        Buat Plan Baru
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300"
                                    >
                                        Perlu Sync Produk
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- FOOTER -->
                <div
                    class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/70 px-6 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800/80 dark:bg-slate-900/70"
                >
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        <span v-if="selectedCount > 0" class="font-medium text-slate-800 dark:text-slate-200">
                            <strong>{{ selectedCount }} MO</strong> dipilih untuk dimasukkan/diperbarui ke WPS.
                        </span>
                        <span v-else class="text-amber-600 dark:text-amber-400">
                            Belum ada MO yang dipilih.
                        </span>
                    </div>
                    <div class="flex items-center justify-end gap-2.5">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                            :disabled="isSyncing"
                            @click="emit('close')"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none disabled:opacity-50 dark:bg-indigo-500 dark:hover:bg-indigo-600"
                            :disabled="isSyncing || selectedCount === 0"
                            @click="handleSyncSubmit"
                        >
                            <svg v-if="isSyncing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ isSyncing ? 'Menyinkronkan...' : `Sinkronkan (${selectedCount}) MO Terpilih` }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
